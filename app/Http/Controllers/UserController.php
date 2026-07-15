<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\CampaignPurpose;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\Donation;
use App\Models\IdCardPrint;
use App\Models\Log as AuditLog;
use App\Models\MembershipFee;
use App\Models\RedCrossUnit; // Import the trait
use App\Models\TaskForce;
use App\Models\TrainingType;
use App\Models\User;
use App\Services\UserFilterService;
use App\Support\Filters\UserFilterDescriber;
use App\Traits\HandlesImageUploads;
use Carbon\Carbon; // Ensure Log facade is imported
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Import Validator
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    use AuthorizesRequests, HandlesImageUploads; // Use the trait

    /**
     * The specific permissions that can be assigned directly to a user.
     * This is the authoritative whitelist for direct-permission assignment.
     */
    private const DIRECT_PERMISSION_NAMES = [
        'send_bulk_messages',
        'print_idcards',
        'print_certificates',
        'campaign_request_approve',
    ];

    /**
     * Display a listing of users.
     */
    public function index(Request $request, UserFilterService $userFilterService): View
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        // --------------------------------------------------
        // View mode
        // --------------------------------------------------
        $viewMode = $request->input('view_mode', 'standard');

        // --------------------------------------------------
        // Display preference: show profile photos (per-browser cookie).
        // Default OFF when the cookie is absent. Set client-side by the
        // checkbox JS in users/index; read here so photos are never
        // rendered server-side when off.
        // --------------------------------------------------
        $showPhotos = $request->cookie('users_show_photos') === '1';

        // --------------------------------------------------
        // Base query (NO filters here)
        // --------------------------------------------------
        $with = ['branch', 'division', 'redCrossUnit'];
        if ($viewMode === 'certificates') {
            $with[] = 'certificatePrints.training.trainingType';
        } elseif ($viewMode === 'trainings') {
            $with[] = 'trainings.trainingType';
            $with[] = 'certificatePrints.training.trainingType';
        } elseif ($viewMode === 'id_cards') {
            $with['currentMembershipPayment'] = fn ($q) => $q->personal();
            $with[] = 'currentMembershipPayment.membershipFee';
            $with[] = 'idCardPrints';
        }

        $validViewModes = ['standard', 'certificates', 'trainings', 'campaigns', 'donations', 'id_cards', 'volunteering'];
        if (! in_array($viewMode, $validViewModes)) {
            $viewMode = 'standard';
        }

        $query = User::with($with)
            ->withCount(['taskForces as active_task_forces_count' => fn ($q) => $q->active()])
            ->where('is_super_admin', false)
            ->whereNull('organisation_id');

        $archivedFilter = $request->input('archived_filter', 'operational');

        // Default: hide archived
        if ($archivedFilter !== 'archived' && $archivedFilter !== 'all') {
            $query->notInactive();
        }

        // --------------------------------------------------
        // APPLY ALL FILTERS via service
        // --------------------------------------------------
        $query = $userFilterService->apply(
            $query,
            $request->all(),
            $accessLevel,
            $scopedId
        );

        // --------------------------------------------------
        // Pagination
        // --------------------------------------------------
        $users = $query->paginate(15)->withQueryString();

        if ($viewMode === 'campaigns') {
            $users->each(function ($user) {
                $user->setRelation('campaignRecipients',
                    $user->campaignRecipients()
                        ->with('campaign')
                        ->orderByDesc(
                            \App\Models\MessagingCampaign::select('send_completed_at')
                                ->whereColumn('messaging_campaigns.id', 'messaging_recipients.messaging_campaign_id')
                                ->limit(1)
                        )
                        ->limit(10)
                        ->get()
                );
                $user->campaign_recipients_total = $user->campaignRecipients()->count();
            });
        }

        if ($viewMode === 'volunteering') {
            $users->each(function ($user) {
                $allActivities = $user->activities()->with('activityType')->get();

                $user->setRelation('recentActivities',
                    $user->activities()
                        ->with('activityType')
                        ->orderByDesc('date')
                        ->limit(8)
                        ->get()
                );

                $user->volunteering_total_hours = $allActivities->sum('hours');
                $user->volunteering_total_count = $allActivities->count();

                $user->volunteering_main_activity = $allActivities
                    ->groupBy('activity_type_id')
                    ->map(fn ($group) => $group)
                    ->sortByDesc(fn ($group) => $group->count())
                    ->first()
                    ?->first()
                    ?->activityType
                    ?->name;
            });
        }

        if ($viewMode === 'donations') {
            $users->each(function ($user) {
                $user->setRelation('recentDonations',
                    $user->donations()
                        ->personal()
                        ->orderByDesc('date_donation')
                        ->limit(8)
                        ->get()
                );
                $allDonations = $user->donations()->personal()->get();
                $user->donations_cash_count = $allDonations->where('in_kind_donation', false)->count();
                $user->donations_inkind_count = $allDonations->where('in_kind_donation', true)->count();
                $user->donations_cash_total = $allDonations->where('in_kind_donation', false)->sum('amount');
                $user->donations_total_count = $allDonations->count();
            });
        }

        // --------------------------------------------------
        // Dropdown data for filters
        // --------------------------------------------------
        $branches = collect();
        $divisions = collect();

        switch ($accessLevel) {
            case 'national':
                $branches = Branch::orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)
                        ->orderBy('name')
                        ->get();
                }
                break;

            case 'branch':
                if ($scopedId) {
                    $branches = Branch::where('id', $scopedId)->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $scopedId)->orderBy('name')->get();
                }
                break;

            case 'division':
                if ($scopedId) {
                    $userDivision = Division::find($scopedId);
                    if ($userDivision) {
                        $branches = Branch::where('id', $userDivision->branch_id)->orderBy('name')->get();
                        $divisions = Division::where('id', $scopedId)->orderBy('name')->get();
                    }
                }
                break;
        }

        $trainingTypes = TrainingType::active()->orderByName()->get();
        $trainingTypesWithExpiry = TrainingType::active()->whereNotNull('validity_years_limit')->orderByName()->get();
        $redCrossUnits = RedCrossUnit::active()->orderBy('name')->get();
        $personFeeNames = MembershipFee::activePersonFeeNames();

        // --------------------------------------------------
        // Filter description
        // --------------------------------------------------
        $filters = $request->all();

        $filterDescriptionHtml = UserFilterDescriber::description(
            $filters,
            empty: 'Showing all users'
        );

        // --------------------------------------------------
        // Update admin activity timestamp (UNCHANGED)
        // --------------------------------------------------
        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        // --------------------------------------------------
        // Has filters? (UNCHANGED)
        // --------------------------------------------------
        $hasFilters =
            $request->anyFilled([
                'search',
                'branch_id',
                'division_id',
                'red_cross_unit_id',
                'gender',
                'age_min',
                'age_max',
                'person_type',
                'photo_signature_filter',
                'membership_filter',
                'volunteer_filter',
                'org_representatives',
                'team_leader_filter',
                'database_role_filter',
                'registration_filter',
                'dormancy_filter',
                'email_status',
                'training_filter',
                'training_expiry',
                'first_aid_refresher',
                'donation_filter',
                'campaign_msg',
                'donation_since_contact',
            ])
            || $request->input('archived_filter', 'operational') !== 'operational'
            || $request->input('sort_by', 'created_at_desc') !== 'created_at_desc';

        //  dd($query->toSql(), $query->getBindings());
        // --------------------------------------------------
        // View
        // --------------------------------------------------
        $campaignPurposes = CampaignPurpose::active()->orderBy('sort_order')->get();
        $currentYear = now()->year;

        return view('users.index', compact(
            'users',
            'branches',
            'divisions',
            'redCrossUnits',
            'accessLevel',
            'scopedId',
            'personFeeNames',
            'trainingTypes',
            'trainingTypesWithExpiry',
            'hasFilters',
            'filterDescriptionHtml',
            'viewMode',
            'campaignPurposes',
            'showPhotos',
            'currentYear',
        ));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $user = auth()->user();
        $accessLevel = $user?->getAccessLevel() ?? 'national';
        $scopedId = $user?->getScopedId();

        $branches = collect();
        // Determine the list of branches based on the user's access level
        switch ($accessLevel) {
            case 'national':
                $branches = Branch::orderBy('name')->get();
                break;
            case 'branch':
                if ($scopedId) {
                    $branches = Branch::where('id', $scopedId)->get();
                }
                break;
            case 'division':
                if ($scopedId) {
                    $userDivision = Division::find($scopedId);
                    if ($userDivision) {
                        $branches = Branch::where('id', $userDivision->branch_id)->get();
                    }
                }
                break;
        }

        // Keep these empty until a parent is chosen (or after validation error)
        $divisions = collect();
        $redCrossUnits = collect();

        $oldBranchId = old('branch_id');
        $oldDivisionId = old('division_id');

        // Pre-load divisions if a branch is already determined (non-national user) or from old input.
        if ($branches->count() === 1 && ! $oldBranchId) {
            $divisions = Division::where('branch_id', $branches->first()->id)->orderBy('name')->get();
        } elseif ($oldBranchId) {
            $divisions = Division::where('branch_id', $oldBranchId)->orderBy('name')->get();
        }

        if ($oldDivisionId) {
            $redCrossUnits = RedCrossUnit::active()
                ->where('division_id', $oldDivisionId)
                ->orderBy('name')
                ->get();
        }

        return view('users.create', compact(
            'branches',
            'divisions',
            'redCrossUnits',
            'accessLevel'
        ));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'title' => 'nullable|in:Mr,Mrs,Ms,Miss,Prof.,Chief,Dr.,Hon.',
            'email' => 'nullable|string|email|max:255|unique:users', // Changed to required
            'password' => 'nullable|string|min:8|confirmed',
            'gender' => 'required|in:male,female', // Changed to required
            'birth_year' => 'required|integer|min:1900|max:'.date('Y'),
            'marital_status' => 'nullable|in:single,married,other', // Changed allowed values
            'national_id_number' => 'required|string|max:255',
            'organisation' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'residential_address' => 'nullable|string|max:500', // Added max:500
            'workplace_address' => 'nullable|string|max:500', // Added max:500
            'telephone1' => 'required|string|max:20', // Changed to required
            'telephone2' => 'nullable|string|max:20',
            'disciplin' => 'nullable|string|max:255',
            'personal_info' => 'nullable|string|max:1000', // Added max:1000
            'branch_id' => 'required|exists:branches,id',
            'division_id' => [
                'required',
                'exists:divisions,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $request->has('branch_id')) {
                        $division = Division::find($value);
                        if ($division && $division->branch_id != $request->input('branch_id')) {
                            $fail('The selected division does not belong to the selected branch.');
                        }
                    }
                },
            ],
            'red_cross_unit_id' => 'nullable|exists:red_cross_units,id,is_active,1',
            'contribution_type' => 'required|in:volunteering,member',
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'captured_photo' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:1024'],
            'captured_signature' => ['nullable', 'string'],
            'admin_consent_confirmed' => 'accepted',
            'admin_consent_form' => 'accepted',
            'consent_notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Handle checkboxes (redundant with nullable|boolean, but keeping for safety if input is 'on'/'off')
        $validated['can_contribute_volunteering'] = $request->input('contribution_type') === 'volunteering';
        $validated['can_contribute_member'] = $request->input('contribution_type') === 'member';
        $validated['title'] = $request->input('title');

        // Hash password (only when one was actually submitted; it's optional)
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Mark email as verified since admin is creating this user
        $validated['email_verified_at'] = now();

        // Handle photo upload (either file upload or captured photo) using the trait method
        $pictureFilename = $this->processPhotoUpload($request, 'profile', 'picture', 'captured_photo');
        if ($pictureFilename) {
            $validated['picture'] = $pictureFilename;
            $validated['image_upload_date'] = now();   // ⬅️ set initial photo timestamp
        }

        // Handle signature upload
        $signatureFilename = $this->processPhotoUpload($request, 'signatures', 'signature_file', 'captured_signature');
        if ($signatureFilename) {
            $validated['signature'] = $signatureFilename;
        }

        // --- System flags: admin form registration ---
        $validated['is_form_registration'] = true;
        $validated['form_reg_id'] = auth()->id();

        $user = User::create($validated);

        $user->last_activity_at = now();
        $user->consent_obtained_at = now();
        $user->consent_obtained_by_id = auth()->id();
        $user->consent_notes = $request->input('consent_notes') ?? 'admin-registered, consent attested via form checkboxes';
        $user->save();

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User created successfully.');
    }

    /**
     * Get divisions for a given branch.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDivisionsByBranch(Request $request)
    {
        $branchId = $request->input('branch_id');
        $divisions = Division::where('branch_id', $branchId)->orderBy('name')->get();

        return response()->json($divisions);
    }

    /**
     * Get Red Cross units for a given division.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRedCrossUnitsByDivision(Request $request)
    {
        $divisionId = $request->input('division_id');
        $units = RedCrossUnit::active()->where('division_id', $divisionId)->orderBy('name')->get();

        return response()->json($units);
    }

    /**
     * Get active task forces for a given branch.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTaskForcesByBranch(Request $request)
    {
        $branchId = $request->input('branch_id');
        $taskForces = TaskForce::active()->where('branch_id', $branchId)->orderBy('name')->get();

        return response()->json($taskForces);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load([
            'branch',
            'division',
            'redCrossUnit',
            'membershipPayments.membershipFee',
            'donations',
            'activities.activityType',
            'activities.assignable',
            'trainings.trainingType',
            'taskForces',
        ]);

        if ($user->redCrossUnit) {
            $user->redCrossUnit->load(['teamLeader', 'assistantTeamLeader']);
        }

        $user->taskForces->each(fn ($tf) => $tf->load(['teamLeader', 'assistantTeamLeader', 'users']));

        // Initialize variables
        $membershipPayments = collect();
        $currentMembership = null;
        $showingLimitMessage = false;
        $donations = collect();
        $donationsLimitMessage = false;
        $trainings = collect();
        $trainingsLimitMessage = false;
        $activities = collect();
        $activitiesLimitMessage = false;

        $certificatePrints = collect();
        $certificatePrintsLimitMessage = false;

        $idCardPrints = collect();
        $idCardPrintsLimitMessage = false;

        $campaignRecipients = collect();
        $campaignRecipientsLimitMessage = false;

        // Process Membership Payments
        $allPayments = $user->membershipPayments()
            ->with('membershipFee')
            ->where('is_deleted', false)
            ->orderBy('payment_date', 'desc')
            ->get();

        $showingLimitMessage = $allPayments->count() > 5;

        $processedPayments = $allPayments->map(function ($payment) {
            $isValid = $payment->expiry_date && Carbon::parse($payment->expiry_date)->isFuture();
            $isExpired = $payment->expiry_date && Carbon::parse($payment->expiry_date)->isPast();

            return [
                'payment_date' => $payment->payment_date ? Carbon::parse($payment->payment_date)->format('M d, Y') : 'N/A',
                'membership_type' => $payment->membershipFee->name ?? 'N/A',
                'amount' => $payment->membershipFee->amount ?? 0,
                'formatted_amount' => '₦'.number_format($payment->membershipFee->amount ?? 0, 2),
                'status' => $this->getPaymentStatus($payment),
                'expiry_date' => $payment->expiry_date ? Carbon::parse($payment->expiry_date)->format('M d, Y') : 'N/A',
                'is_valid' => $isValid,
                'is_expired' => $isExpired,
            ];
        });

        $currentMembership = $processedPayments->firstWhere('is_valid', true);
        $membershipPayments = $processedPayments;

        // Process Donations
        $allDonations = $user->donations()
            ->where('is_deleted', false)
            ->orderBy('date_donation', 'desc')
            ->get();

        $donationsLimitMessage = $allDonations->count() > 5;

        $donations = $allDonations->map(function ($donation) {
            return [
                'date' => $donation->date_donation ? Carbon::parse($donation->date_donation)->format('M d, Y') : 'N/A',
                'item' => $this->getDonationItem($donation),
                'amount' => $this->getDonationAmount($donation),
                'type' => $donation->in_kind_donation ? 'in-kind' : 'cash',
                'purpose' => $donation->purpose ?? 'General',
            ];
        });

        // Process Trainings
        $allTrainings = $user->trainings()
            ->with('trainingType')
            ->where('is_deleted', false)
            ->orderBy('training_date', 'desc')
            ->get();

        $trainingsLimitMessage = $allTrainings->count() > 5;

        $trainings = $allTrainings->map(function ($training) {
            return [
                'date' => $training->training_date ? Carbon::parse($training->training_date)->format('M d, Y') : 'N/A',
                'activity' => $training->trainingType->name ?? 'Training Course',
                'hours' => $this->getTrainingDays($training),
                'status' => $this->getTrainingStatus($training),
                'duration' => $training->duration ?? 0,
                'training_type_id' => $training->training_type_id,
                'certificate_hq_only' => $training->trainingType->certificate_hq_only ?? false,
            ];
        });

        // Process Activities (Volunteering)
        $allActivities = $user->activities()
            ->with(['activityType', 'assignable'])
            ->where('is_deleted', false)
            ->orderBy('date', 'desc')
            ->get();

        $activitiesLimitMessage = $allActivities->count() > 5;

        $activities = $allActivities->map(function ($activity) {
            return [
                'date' => $activity->date ? Carbon::parse($activity->date)->format('M d, Y') : 'N/A',
                'activity' => $activity->activityType->name ?? 'Volunteer Activity',
                'hours' => $activity->hours ?? 0,
                'hours_display' => $this->getActivityHours($activity),
                'unit' => $this->getUnitName($activity),
                'unit_type' => $this->getUnitTypeName($activity),
                'reference' => $activity->reference ?? null,
                'id' => $activity->id ?? null,
            ];
        });

        // Printed certificates
        $allCertificatePrints = CertificatePrint::query()
            ->where('user_id', $user->id)
            ->with(['training.trainingType', 'printedBy'])
            ->orderByDesc('printed_at')
            ->get();

        $certificatePrintsLimitMessage = $allCertificatePrints->count() >= 6;

        $certificatePrints = $allCertificatePrints->map(function (CertificatePrint $print) {
            return [
                'printed_at' => $print->printed_at?->format('M d, Y') ?? '—',
                'certificate_type' => $print->certificate_type ?? '—',
                'training' => $print->training?->trainingType?->name
                    ?? $print->training?->title
                        ?? '—',
                'printed_by' => $print->printedBy?->full_name
                    ?? $print->printedBy?->email
                        ?? '—',
                'notes' => $print->notes ?: null,
            ];
        });

        // Printed ID cards
        $allIdCardPrints = IdCardPrint::query()
            ->where('user_id', $user->id)
            ->with('printedBy')
            ->orderByDesc('printed_at')
            ->get();

        $idCardPrintsLimitMessage = $allIdCardPrints->count() >= 6;

        $idCardPrints = $allIdCardPrints->map(function (IdCardPrint $print) {
            return [
                'printed_at' => $print->printed_at?->format('M d, Y') ?? '—',
                'status' => $print->status ?? '—',
                'validity_months' => $print->validity_months ?? null,
                'expiry_date' => $print->expiry_date?->format('M d, Y') ?? '—',
                'printed_by' => $print->printedBy?->full_name
                    ?? $print->printedBy?->email
                        ?? '—',
                'notes' => $print->notes ?: null,
            ];
        });

        // Campaign recipients (messages sent)
        $allCampaignRecipients = $user->campaignRecipients()
            ->with('campaign')
            ->orderByDesc('sent_at')
            ->get();

        $campaignRecipientsLimitMessage = $allCampaignRecipients->count() >= 6;

        $campaignRecipients = $allCampaignRecipients->map(fn ($r) => [
            'sent_at' => $r->sent_at?->format('M d, Y H:i') ?? null,
            'campaign_title' => $r->campaign?->title ?? '—',
            'channel' => $r->campaign?->channel ?? '—',
            'status' => $r->status ?? '—',
            'email' => $r->email,
            'phone' => $r->phone,
        ]);

        return view('users.show', compact(
            'user',
            'currentMembership',
            'membershipPayments',
            'showingLimitMessage',
            'donations',
            'donationsLimitMessage',
            'trainings',
            'trainingsLimitMessage',
            'activities',
            'activitiesLimitMessage',
            'certificatePrints',
            'certificatePrintsLimitMessage',
            'idCardPrints',
            'idCardPrintsLimitMessage',
            'campaignRecipients',
            'campaignRecipientsLimitMessage'
        ));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        // Policy-based authorization (uses UserPolicy@update)
        $this->authorize('update', $user);

        $user->load('organisations.users');

        $actingUser = Auth::user();
        $accessLevel = $actingUser->getAccessLevel();
        $scopedId = $actingUser->getScopedId();

        $branches = collect();
        $divisions = collect();

        switch ($accessLevel) {
            case 'national':
                $branches = Branch::orderBy('name')->get();
                $divisions = Division::orderBy('name')->get();
                break;

            case 'branch':
                if ($scopedId) {
                    $branches = Branch::where('id', $scopedId)->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $scopedId)->orderBy('name')->get();
                }
                break;

            // 'division'-level actors can never reach this page — UserPolicy::update()
            // is gated behind the edit_user permission, which neither division role
            // holds. No case needed; falls through to the empty collections above.
        }

        $redCrossUnits = RedCrossUnit::active()->orderBy('name')->get();

        // Reintroduce current unit if inactive — prevents validation failure
        // on save and keeps the existing assignment visible.
        if ($user->red_cross_unit_id) {
            $currentUnit = RedCrossUnit::find($user->red_cross_unit_id);
            if ($currentUnit && ! $redCrossUnits->contains('id', $currentUnit->id)) {
                $redCrossUnits->push($currentUnit);
            }
        }

        return view('users.edit', compact('user', 'branches', 'divisions', 'redCrossUnits'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'title' => 'nullable|in:Mr,Mrs,Ms,Miss,Prof.,Chief,Dr.,Hon.',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'gender' => 'required|in:male,female',
            'birth_year' => 'required|integer|min:1900|max:'.date('Y'),
            'marital_status' => 'nullable|in:single,married,other',
            'national_id_number' => 'nullable|string|max:255',
            'organisation' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'residential_address' => 'nullable|string|max:500',
            'workplace_address' => 'nullable|string|max:500',
            'telephone1' => 'nullable|string|max:20',
            'telephone2' => 'nullable|string|max:20',
            'disciplin' => 'nullable|string|max:255',
            'personal_info' => 'nullable|string|max:1000',
            'branch_id' => 'required|exists:branches,id',
            'division_id' => [
                'required',
                'exists:divisions,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $request->has('branch_id')) {
                        $division = Division::find($value);
                        if ($division && $division->branch_id != $request->input('branch_id')) {
                            $fail('The selected division does not belong to the selected branch.');
                        }
                    }
                },
            ],
            'red_cross_unit_id' => [
                'nullable',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value === null) {
                        return;
                    }
                    $unit = RedCrossUnit::find($value);
                    if (! $unit) {
                        $fail('The selected Red Cross Unit does not exist.');

                        return;
                    }
                    // Allow saving the existing assignment even if unit is now inactive.
                    // Changing TO a different inactive unit is still blocked.
                    if ((int) $value === (int) $user->red_cross_unit_id) {
                        return;
                    }
                    if (! $unit->is_active) {
                        $fail('The selected Red Cross Unit is not active.');
                    }
                },
            ],
            'contribution_type' => 'required|in:volunteering,member',
            'email_opt_out' => 'nullable|boolean',
            'sms_opt_out' => 'nullable|boolean',

            // Legacy form field name; interpreted as "Archived toggle".
            'is_inactive' => ['nullable', 'boolean'],

            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            Log::warning('UserController@update: Validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // ---------------------------------------------------------
        // Archive / Reactivate
        // Source of truth: lifecycle_status
        // Note: we DO NOT write legacy fields (is_inactive/deactivated_*)
        // ---------------------------------------------------------
        $wasArchived = ($user->lifecycle_status === 'archived');
        $wantsArchived = $request->boolean('is_inactive'); // checkbox = "Archived"

        if ($wantsArchived !== $wasArchived) {

            // Safety: prevent archiving yourself
            if ($wantsArchived && Auth::id() === $user->id) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['is_inactive' => 'You cannot archive your own account.']);
            }

            $user->lifecycle_status = $wantsArchived ? 'archived' : 'active';
        }

        // Never mass-assign archive controls through $validated
        unset($validated['is_inactive'], $validated['lifecycle_status']);

        // Capture opt-out state before update; handle timestamps explicitly below
        $wasEmailOptOut = (bool) $user->email_opt_out;
        $wasSmsOptOut = (bool) $user->sms_opt_out;
        $newEmailOptOut = $request->boolean('email_opt_out');
        $newSmsOptOut = $request->boolean('sms_opt_out');
        unset($validated['email_opt_out'], $validated['sms_opt_out']);

        // Reset email verification if email or password changed
        if ($request->email !== $user->email || $request->filled('password')) {
            $validated['email_verified_at'] = null;
        }

        // Handle password
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['can_contribute_volunteering'] = $request->input('contribution_type') === 'volunteering';
        $validated['can_contribute_member'] = $request->input('contribution_type') === 'member';
        $validated['title'] = $request->input('title');

        // -------------------------------
        // Red Cross Unit assignment logic
        // -------------------------------
        $oldUnitId = $user->red_cross_unit_id;
        $newUnitId = $validated['red_cross_unit_id'] ?? null;

        $unitChanged = ($oldUnitId !== $newUnitId);

        if ($unitChanged) {

            // First-ever assignment → set date
            if (is_null($user->assigned_rcu_date) && ! empty($newUnitId)) {
                $validated['assigned_rcu_date'] = now();
            }

            // Always track who assigned
            $validated['assigned_rcu_by_id'] = Auth::id();
        }

        // -------------------------------
        // Branch / Division audit logging
        // -------------------------------
        $branchChanged = array_key_exists('branch_id', $validated)
            && (int) $validated['branch_id'] !== (int) $user->branch_id;

        $divisionChanged = array_key_exists('division_id', $validated)
            && (int) $validated['division_id'] !== (int) $user->division_id;

        if ($branchChanged || $divisionChanged) {

            $old = [
                'branch_id' => $user->branch_id,
                'division_id' => $user->division_id,
            ];

            $new = [
                'branch_id' => $validated['branch_id'] ?? $user->branch_id,
                'division_id' => $validated['division_id'] ?? $user->division_id,
            ];

            $descriptionParts = [];

            if ($branchChanged) {
                $descriptionParts[] = sprintf(
                    'Branch: %s → %s',
                    optional($user->branch)->name ?: 'N/A',
                    optional(Branch::find($new['branch_id']))->name ?: 'N/A'
                );
            }

            if ($divisionChanged) {
                $descriptionParts[] = sprintf(
                    'Division: %s → %s',
                    optional($user->division)->name ?: 'N/A',
                    optional(Division::find($new['division_id']))->name ?: 'N/A'
                );
            }

            AuditLog::write(
                'member_branch_division_changed',
                $user,
                $new,
                $old,
                $new,
                'Member location updated ('.implode(', ', $descriptionParts).')'
            );
        }

        // Block branch change if user has any role — role must be
        // removed first to avoid branch/role mismatch.
        if ($branchChanged && $user->roles()->exists()) {
            return back()
                ->withInput()
                ->withErrors([
                    'branch_id' => 'This person has an administrative role ('.
                        $user->getRoleNames()->map(fn ($r) => Str::title(str_replace('_', ' ', $r))
                        )->join(', ').
                        '). Remove the role via Authorizations before '.
                        'moving them to a different branch.',
                ]);
        }

        // Prevent branch admin from moving a user to an out-of-scope branch
        if ($branchChanged) {
            $actingUser = Auth::user();
            if ($actingUser->getAccessLevel() === 'branch'
                && (int) $validated['branch_id'] !== (int) $actingUser->branch_id) {
                return back()->withInput()->withErrors([
                    'branch_id' => 'You can only move users within your own branch.',
                ]);
            }
        }

        // -------------------------------
        // Persist main update
        // -------------------------------
        // lifecycle_status change (if any) is on $user already, so update() will persist it too.
        $user->update($validated);

        // Handle opt-out timestamp logic separately to avoid mass-assignment of _at fields
        $user->email_opt_out = $newEmailOptOut;
        if (! $wasEmailOptOut && $newEmailOptOut) {
            $user->email_opt_out_at = now();
        } elseif ($wasEmailOptOut && ! $newEmailOptOut) {
            $user->email_opt_out_at = null;
        }
        $user->sms_opt_out = $newSmsOptOut;
        if (! $wasSmsOptOut && $newSmsOptOut) {
            $user->sms_opt_out_at = now();
        } elseif ($wasSmsOptOut && ! $newSmsOptOut) {
            $user->sms_opt_out_at = null;
        }
        $user->save();

        // 👉 Mark user active if unit changed
        if ($unitChanged && $user->lifecycle_status !== 'archived') {
            $user->markActive();
        }

        // 👉 Touch admin activity separately
        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Update the user's profile picture.
     * This method is specifically for updating *only* the profile picture.
     */
    public function updateProfilePicture(Request $request, User $user)
    {
        $request->validate([
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'captured_photo' => ['nullable', 'string'], // For base64 captured photos
        ]);

        // Use the trait's unified photo processing method
        $pictureFilename = $this->processPhotoUpload($request, 'profile', 'picture', 'captured_photo');

        if ($pictureFilename) {
            // Delete old photo files if a new one is successfully uploaded/captured
            if ($user->picture) {
                $this->deleteUserImage($user->picture, 'profile');
            }

            $user->picture = $pictureFilename;
            $user->image_upload_date = now();   // ⬅️ refresh timestamp on change
            $user->save();

            return back()->with('success', 'Profile picture updated successfully.');
        }

        // If no file was uploaded and no photo was captured, or if there was an error
        return back()->with('error', 'Failed to update profile picture. Please ensure you either uploaded a file or captured a photo, and try again.');
    }

    /**
     * Update the user's signature.
     * This method is specifically for updating *only* the signature.
     */
    public function updateSignature(Request $request, User $user)
    {
        $request->validate([
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:1024'],
            'captured_signature' => ['nullable', 'string'], // For base64 captured signatures
        ]);

        // Use the trait's unified photo processing method for signatures
        // Pass 'signatures' as the category for storage and 'signature_file'/'captured_signature' as input names
        $signatureFilename = $this->processPhotoUpload($request, 'signatures', 'signature_file', 'captured_signature');

        if ($signatureFilename) {
            // Delete old signature files if a new one is successfully uploaded/captured
            if ($user->signature) {
                $this->deleteUserImage($user->signature, 'signatures');
            }
            $user->signature = $signatureFilename;
            $user->save();

            return back()->with('success', 'Signature updated successfully.');
        }

        return back()->with('error', 'Failed to update signature. Please ensure you either uploaded a file or captured a signature, and try again.');
    }

    /**
     * Show the page for editing user roles and permissions.
     *
     * @return \Illuminate\View\View
     */
    public function editRoles(Request $request)
    {
        $this->authorize('manage_roles_and_permissions');

        $assigningUser = Auth::user();
        $roles = $assigningUser->getAssignableRoles();
        $accessLevel = $assigningUser->getAccessLevel();
        $isSuperAdmin = $assigningUser->hasRole('super-admin');

        // Define the specific permissions that can be assigned directly
        $directPermissionNames = self::DIRECT_PERMISSION_NAMES;
        $permissions = Permission::whereIn('name', $directPermissionNames)
            ->orderBy('name')
            ->get();

        $selectedUser = null;
        $userRole = null;
        $userPermissions = [];

        if ($request->filled('user_id')) {
            $selectedUser = User::with('roles', 'permissions')->find($request->user_id);
            if ($selectedUser) {
                $userRole = $selectedUser->getRoleNames()->first();
                $userPermissions = $selectedUser->getDirectPermissions()->pluck('name')->toArray();
            }
        }

        $usersForTableQuery = User::query()
            ->select('users.*', 'roles.name as role_name', 'roles.description as role_description')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', User::class)
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->with(['branch', 'division', 'redCrossUnit']) // Eager load relationships for the accessor
            ->orderBy('model_has_roles.role_id', 'asc')
            ->orderBy('users.first_name')
            ->orderBy('users.last_name');

        // Scoping based on the logged-in user's access level
        $scopedId = $assigningUser->getScopedId();
        if ($accessLevel === 'branch' && $scopedId) {
            $usersForTableQuery->where('users.branch_id', $scopedId);
        }

        // only users with roles that can be assigned by the current user
        $assignableRoleIds = $roles->pluck('id');

        // For national-level viewers, also show national_db_administrator rows (read-only in table)
        if ($accessLevel === 'national') {
            $nationalDbAdminRoleId = \Spatie\Permission\Models\Role::where('name', 'national_db_administrator')->value('id');
            $tableRoleIds = $nationalDbAdminRoleId
                ? $assignableRoleIds->push($nationalDbAdminRoleId)->unique()->values()
                : $assignableRoleIds;
            $usersForTableQuery->whereIn('roles.id', $tableRoleIds);
        } else {
            $usersForTableQuery->whereIn('roles.id', $assignableRoleIds);
        }

        $usersForTable = $usersForTableQuery->get();

        return view('users.edit-roles', compact('roles', 'permissions', 'selectedUser', 'userRole', 'userPermissions', 'accessLevel', 'isSuperAdmin', 'usersForTable'));
    }

    /**
     * Update roles and permissions for a user.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateRoles(Request $request)
    {
        $this->authorize('manage_roles_and_permissions');

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $assigningUser = Auth::user();

        // Security check: Prevent branch-level admins from editing national-level users.
        if ($assigningUser->getAccessLevel() === 'branch' && $user->getAccessLevel() === 'national') {
            abort(403, 'Branch-level administrators cannot modify the roles of national-level users.');
        }

        // Security check: no admin may ever modify their own role or permissions
        // through this form, at any access level. Defense in depth — the search
        // in searchUsersForRoles() already excludes self, but this form is
        // reachable directly via ?user_id=, so the mutation itself must also
        // refuse a self-target.
        if ((int) $assigningUser->id === (int) $user->id) {
            return redirect()
                ->route('users.roles.edit')
                ->withErrors(['user_id' => 'You cannot modify your own role or permissions.']);
        }

        // Security check: branch-level actors (branch_secretary, branch_db_administrator)
        // may never modify a target who currently holds branch_secretary or
        // branch_db_administrator — those two roles are only editable by a
        // national-level admin. This blocks lateral demotion between branch-level
        // peers, mirroring the self-guard above. Checked against the target's
        // CURRENT role, not the role being assigned, since the incident this
        // guards against was a downgrade to a role the actor was otherwise
        // permitted to assign.
        if ($assigningUser->getAccessLevel() === 'branch'
            && $user->hasAnyRole(['branch_secretary', 'branch_db_administrator'])) {
            abort(403, 'Branch-level administrators cannot modify the role of another branch_secretary or branch_db_administrator. Contact a National DB Administrator.');
        }

        $incomingRole = $validated['role'] ?? '';

        // Whitelist: only the four direct permissions may be synced
        $incomingPermissions = collect($validated['permissions'] ?? [])
            ->intersect(self::DIRECT_PERMISSION_NAMES)
            ->values()
            ->all();

        // Rule A: direct permissions are only meaningful on a national-level role.
        // The three direct permissions (send_bulk_messages, print_idcards,
        // print_certificates) are only shown in the form for non-branch admins,
        // but enforce server-side regardless.
        $nationalRoles = [
            'national_db_assistant',
        ];
        if (! empty($incomingPermissions) && ! in_array($incomingRole, $nationalRoles, true)) {
            return redirect()
                ->route('users.roles.edit', ['user_id' => $user->id])
                ->withErrors(['permissions' => 'Direct permissions can only be assigned to National DB Assistants. '.
                    'Administrators and Observers receive permissions via their role and '.
                    'do not need direct permissions assigned.',
                ]);
        }

        // Rule B: clearing a role also clears all direct permissions —
        // no orphaned permissions without a role.
        if (empty($incomingRole)) {
            $incomingPermissions = [];
        }

        $oldRoles = $user->getRoleNames()->sort()->values()->all();
        $oldPermissions = $user->getPermissionNames()->sort()->values()->all();

        $user->syncRoles(! empty($incomingRole) ? [$incomingRole] : []);
        $user->syncPermissions($incomingPermissions);

        $newRoles = $user->getRoleNames()->sort()->values()->all();
        $newPermissions = $user->getPermissionNames()->sort()->values()->all();

        if ($oldRoles !== $newRoles || $oldPermissions !== $newPermissions) {
            $oldRoleName = $oldRoles[0] ?? null;
            $newRoleName = $newRoles[0] ?? null;

            $roleClause = $newRoleName !== null
                ? sprintf('assigned role "%s"', $newRoleName)
                : 'removed all roles (no role assigned)';

            $previousRoleText = $oldRoleName !== null
                ? sprintf('previously: "%s"', $oldRoleName)
                : 'was: no role';

            $permissionsAdded = array_values(array_diff($newPermissions, $oldPermissions));
            $permissionsRemoved = array_values(array_diff($oldPermissions, $newPermissions));

            $permissionParts = [];
            if (! empty($permissionsAdded)) {
                $permissionParts[] = 'special permissions granted: '.implode(', ', $permissionsAdded);
            }
            if (! empty($permissionsRemoved)) {
                $permissionParts[] = 'special permissions revoked: '.implode(', ', $permissionsRemoved);
            }
            $permissionsSuffix = empty($permissionParts) ? '' : '; '.implode('; ', $permissionParts);

            $description = sprintf(
                '%s (%s)%s for %s by user #%s',
                $roleClause,
                $previousRoleText,
                $permissionsSuffix,
                $user->fullName,
                $assigningUser->id
            );

            AuditLog::write(
                'user_roles_updated',
                $user,
                ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
                ['roles' => $oldRoles, 'permissions' => $oldPermissions],
                ['roles' => $newRoles, 'permissions' => $newPermissions],
                $description
            );
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('users.roles.edit', ['user_id' => $user->id])
            ->with('success', "Successfully updated roles and permissions for {$user->fullName}.");
    }

    /**
     * Search for users to manage their roles (for AJAX requests).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsersForRoles(Request $request)
    {
        $this->authorize('manage_roles_and_permissions');

        $search = $request->input('search');
        if (empty($search)) {
            return response()->json([]);
        }

        $assigningUser = Auth::user();
        $accessLevel = $assigningUser->getAccessLevel();
        $scopedId = $assigningUser->getScopedId();

        // Eager load relationships needed for the userIdReference accessor
        $query = User::with(['branch', 'division', 'redCrossUnit'])->selectableForEntry();

        // Never allow an admin to select themselves for role editing.
        $query->where('id', '!=', $assigningUser->id);

        // Scope the search for branch-level users
        if ($accessLevel === 'branch') {
            if ($scopedId) {
                $query->where('branch_id', $scopedId);
            }
            // Also, exclude users with national-level roles from the search results
            $nationalRoles = User::NATIONAL_ROLES;

            $query->whereDoesntHave('roles', function ($q) use ($nationalRoles) {
                $q->whereIn('name', $nationalRoles);
            });
        }

        // Branch-level actors (branch_secretary, branch_db_administrator) must
        // never be able to select a peer holding an equal-or-senior branch role
        // (branch_secretary, branch_db_administrator) — only a national-level
        // admin may edit those two roles. This prevents lateral demotion between
        // branch-level peers, in addition to the self-exclusion above.
        if ($accessLevel === 'branch') {
            $query->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['branch_secretary', 'branch_db_administrator']);
            });
        }

        $users = $query->where(function ($query) use ($search) {
            $query->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$search}%"]);
            if (is_numeric($search)) {
                $query->orWhere('id', $search);
            }
        })
            ->take(10)
            ->get();

        // Transform the collection to include the accessor result
        $userData = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'user_id_reference' => $user->user_id_reference, // This will call the accessor
            ];
        });

        return response()->json($userData);
    }

    public function search(Request $request)
    {
        $query = trim($request->get('query', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $authUser = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId = $authUser->getScopedId();

        $idCandidate = preg_replace('/^db-/i', '', trim($query));

        $usersQuery = User::selectableForEntry()->where(function ($q) use ($query, $idCandidate) {
            $q->where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->orWhere('middle_name', 'like', "%{$query}%")
                ->orWhere('user_code', 'like', "%{$query}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhere('id', is_numeric($idCandidate) ? (int)$idCandidate : -1);
        });

        if ($accessLevel === 'branch' && $scopedId) {
            $usersQuery->where('branch_id', $scopedId);
        } elseif ($accessLevel === 'division' && $scopedId) {
            $usersQuery->where('division_id', $scopedId);
        }

        $users = $usersQuery
            ->select('id', 'first_name', 'middle_name', 'last_name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get();

        return response()->json($users);
    }

    // --- Original helper methods remain below ---
    /**
     * Get payment status with styling information
     */
    private function getPaymentStatus($payment)
    {
        if ($payment->expiry_date && Carbon::parse($payment->expiry_date)->isFuture()) {
            return [
                'type' => 'valid',
                'text' => 'Valid until '.Carbon::parse($payment->expiry_date)->format('M d, Y'),
                'class' => 'bg-green-100 text-green-800',
            ];
        } elseif ($payment->expiry_date && Carbon::parse($payment->expiry_date)->isPast()) {
            return [
                'type' => 'expired',
                'text' => 'Archived payment',
                'class' => 'bg-red-100 text-red-800',
            ];
        } else {
            return [
                'type' => 'unknown',
                'text' => 'Unknown',
                'class' => 'bg-gray-100 text-gray-800',
            ];
        }
    }

    /**
     * Get donation item display text
     */
    private function getDonationItem($donation)
    {
        if ($donation->in_kind_donation) {
            return $donation->donation_item ?? 'In-kind donation';
        } else {
            return 'Cash donation';
        }
    }

    /**
     * Get donation amount display text
     */
    private function getDonationAmount($donation)
    {
        if ($donation->in_kind_donation) {
            return $donation->donation_estimated_value
                ? '₦'.number_format($donation->donation_estimated_value, 2).' (est.)'
                : 'No value estimated';
        } else {
            return $donation->amount
                ? '₦'.number_format($donation->amount, 2)
                : '₦0.00';
        }
    }

    /**
     * Get training hours display text
     */
    private function getTrainingDays($training)
    {
        if ($training->duration) {
            return $training->duration.' '.($training->duration == 1 ? 'day' : 'days');
        }

        return 'Duration not specified';
    }

    /**
     * Get training status
     */
    private function getTrainingStatus($training)
    {
        if ($training->valid_years && $training->training_date) {
            $expiryDate = Carbon::parse($training->training_date)->addYears($training->valid_years);
            if ($expiryDate->isFuture()) {
                return [
                    'type' => 'valid',
                    'text' => 'Valid until '.$expiryDate->format('M d, Y'),
                    'class' => 'bg-green-100 text-green-800',
                ];
            } else {
                return [
                    'type' => 'expired',
                    'text' => 'Expired on '.$expiryDate->format('M d, Y'),
                    'class' => 'bg-red-100 text-red-800',
                ];
            }
        }

        return [
            'type' => 'unknown',
            'text' => 'No expiry date',
            'class' => 'bg-gray-100 text-gray-800',
        ];
    }

    /**
     * Get activity hours display text
     */
    private function getActivityHours($activity)
    {
        if ($activity->hours) {
            return $activity->hours.' hours';
        }

        return 'Duration not specified';
    }

    /**
     * Get Red Cross Unit name from activity
     */
    private function getUnitName($activity)
    {
        if ($activity->assignable) {
            return $activity->assignable->name ?? 'Unit not specified';
        }

        return 'Unit not specified';
    }

    private function getUnitTypeName($activity)
    {

        return $activity->unit_type ?? 'Not specified';
    }
}
