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
use App\Notifications\AccountDetailsChanged;
use App\Rules\NationalIdNumberRule;
use App\Rules\NigerianMobileNumber;
use App\Support\NationalIdNumber;
use App\Support\PhoneNumber;
use App\Support\SpecialPermissions;
use App\Services\UserAnonymizer;
use App\Services\UserFilterService;
use App\Services\UserSearchNotice;
use App\Support\Filters\UserFilterDescriber;
use App\Traits\HandlesImageUploads;
use Carbon\Carbon; // Ensure Log facade is imported
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Import Validator
use Illuminate\Database\QueryException;
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
     * The Authorizations form's "-- No Role --" value. Only this removes a role in
     * updateRoles(); an empty or missing role means "no change", so a field the
     * browser leaves out (e.g. the disabled "keep current" option) never removes one.
     */
    public const REMOVE_ROLE = '__remove__';

    /**
     * Display a listing of users.
     */
    public function index(Request $request, UserFilterService $userFilterService, UserSearchNotice $userSearchNotice): View
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
        $with = ['branch', 'division', 'redCrossUnit', 'organisations'];
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
        // Division staff have no organisation access, and representatives are
        // placed by their organisation (who may live elsewhere): no
        // Org Representatives filter for them.
        $filters = $request->all();
        if ($accessLevel === 'division') {
            unset($filters['org_representatives']);
        }

        $query = $userFilterService->apply(
            $query,
            $filters,
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
        // $filters: the request's filters, as applied above (without
        // org_representatives for division staff).
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
                'age_bracket',
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
                'nin_filter',
                'verification_filter',
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

        // Why a search found nobody, where that is safe to say (see UserSearchNotice).
        $searchNotice = $users->total() === 0 && $request->filled('search')
            ? $userSearchNotice->for($user, $accessLevel, $scopedId, (string) $request->input('search'))
            : null;

        return view('users.index', compact(
            'searchNotice',
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

        // Division staff register people in their own division only (store() enforces it).
        if ($accessLevel === 'division') {
            $divisions = Division::whereKey($scopedId)->get();
            $oldDivisionId = $oldDivisionId ?: $scopedId;
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
        $request->merge(PhoneNumber::stripFormattingFields($request->all()));

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'title' => 'nullable|in:Mr,Mrs,Ms,Miss,Prof.,Chief,Dr.,Hon.',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'gender' => 'required|in:male,female', // Changed to required
            'birth_year' => 'required|integer|min:1900|max:'.date('Y'),
            'marital_status' => 'nullable|in:single,married,other', // Changed allowed values
            'national_id_number' => ['nullable', new NationalIdNumberRule],
            'red_cross_id_number' => 'nullable|string|max:50',
            'organisation' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'residential_address' => 'nullable|string|max:500', // Added max:500
            'workplace_address' => 'nullable|string|max:500', // Added max:500
            'telephone1' => ['required', 'string', 'max:20', new NigerianMobileNumber],
            'telephone2' => ['nullable', 'string', 'max:20', new NigerianMobileNumber],
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
            'red_cross_unit_id' => [
                'nullable',
                'required_if:contribution_type,volunteering',
                'exists:red_cross_units,id,is_active,1',
                function ($attribute, $value, $fail) use ($request) {
                    $contributionType = $request->input('contribution_type');
                    if ($contributionType === 'member' && ! empty($value)) {
                        $fail('Members should not be assigned to a Red Cross Unit.');
                    }
                },
            ],
            'contribution_type' => 'required|in:volunteering,member',
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'captured_photo' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:1024'],
            'captured_signature' => ['nullable', 'string'],
            'admin_consent_confirmed' => 'accepted',
            'admin_consent_form' => 'accepted',
            'consent_notes' => 'nullable|string|max:255',
        ], [
            'red_cross_unit_id.required_if' => 'A Red Cross Unit must be selected for volunteers.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Staff register people only inside their own area. The form already limits
        // the choices; a posted form must not get around that.
        $registrar = Auth::user();
        $scopeError = match ($registrar->getAccessLevel()) {
            'branch' => (int) $validated['branch_id'] !== (int) $registrar->getScopedId()
                ? ['branch_id' => 'You can only register people in your own branch.'] : null,
            'division' => (int) $validated['division_id'] !== (int) $registrar->getScopedId()
                ? ['division_id' => 'You can only register people in your own division.'] : null,
            'national' => null,
            default => ['branch_id' => 'You are not allowed to register people.'],
        };
        if ($scopeError === null && ! empty($validated['red_cross_unit_id'])
            && (int) RedCrossUnit::whereKey($validated['red_cross_unit_id'])->value('division_id') !== (int) $validated['division_id']) {
            $scopeError = ['red_cross_unit_id' => 'The selected Red Cross Unit does not belong to the selected division.'];
        }
        if ($scopeError !== null) {
            return redirect()->back()->withErrors($scopeError)->withInput();
        }

        // Photos/signatures sent with the form are kept only from staff allowed to
        // upload images (division assistants need their own tick box for that).
        $mayUploadImages = $registrar->can('edit_user') || $registrar->can('upload_user_images');

        // Handle checkboxes (redundant with nullable|boolean, but keeping for safety if input is 'on'/'off')
        $validated['can_contribute_volunteering'] = $request->input('contribution_type') === 'volunteering';
        $validated['can_contribute_member'] = $request->input('contribution_type') === 'member';
        $validated['title'] = $request->input('title');

        // Hash password
        $validated['password'] = Hash::make($validated['password']);

        // Mark email as verified since admin is entering it on the user's behalf
        // — only applies if an email was actually provided.
        if (!empty($validated['email'])) {
            $validated['email_verified_at'] = now();
        }

        // Never store the raw upload fields; only the processed filenames below.
        unset($validated['picture'], $validated['captured_photo'], $validated['signature_file'], $validated['captured_signature']);

        if ($mayUploadImages) {
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
        }

        // --- System flags: admin form registration ---
        $validated['is_form_registration'] = true;
        $validated['form_reg_id'] = auth()->id();

        // Explicit (matches the column default) so promoteFromPendingIfQualified()
        // below sees it on the in-memory model.
        $validated['lifecycle_status'] = 'pending_engagement';

        try {
            $user = User::create($validated);
        } catch (QueryException $e) {
            NationalIdNumberRule::rethrowIfDuplicate($e);
            throw $e;
        }

        $user->last_activity_at = now();
        $user->consent_obtained_at = now();
        $user->consent_obtained_by_id = auth()->id();
        $user->consent_notes = $request->input('consent_notes') ?? 'admin-registered, consent attested via form checkboxes';
        $user->save();

        if ($user->red_cross_unit_id !== null) {
            $user->assigned_rcu_date = now();
            $user->assigned_rcu_by_id = Auth::id();
            $user->save();
        }

        $user->promoteFromPendingIfQualified(touchActivity: true);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User created successfully.')
            ->with('just_registered', true);
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
            'organisations',
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
            ->personal()
            ->withAnyApprovalStatus()
            ->whereIn('approval_status', ['approved', 'pending'])
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
                'approval_status' => $payment->approval_status,
                'reference_link' => $payment->payment_reference_link,
            ];
        });

        // A pending (or rejected) payment must never be picked as the "current
        // membership" even if it happens to carry a future expiry_date — only an
        // approved record represents confirmed membership.
        $currentMembership = $processedPayments->first(
            fn ($payment) => $payment['is_valid'] && $payment['approval_status'] === 'approved'
        );
        $membershipPayments = $processedPayments;

        // Process Donations
        // Organisation donations (this person as contact) are hidden from
        // division-level viewers (Donation::scopeExceptOrganisationalFor()).
        $allDonations = $user->donations()
            ->exceptOrganisationalFor(Auth::user())
            ->withAnyApprovalStatus()
            ->whereIn('approval_status', ['approved', 'pending'])
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
                'approval_status' => $donation->approval_status,
                'reference_link' => $donation->donation_reference_link,
            ];
        });

        // Process Trainings
        $allTrainings = $user->trainings()
            ->withAnyApprovalStatus()
            ->whereIn('approval_status', ['approved', 'pending'])
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
                'approval_status' => $training->approval_status,
                'reference_link' => $training->training_reference_link,
            ];
        });

        // Process Activities (Volunteering)
        $allActivities = $user->activities()
            ->withAnyApprovalStatus()
            ->whereIn('approval_status', ['approved', 'pending'])
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
                'reference_link' => $activity->activity_reference_link,
                'id' => $activity->id ?? null,
                'approval_status' => $activity->approval_status,
            ];
        });

        // Pending activities must appear in the table above but must NOT inflate
        // the hours/count summary, which is meant to represent confirmed
        // (approved) volunteering only.
        $approvedActivities = $activities->where('approval_status', 'approved');
        $totalHours = $approvedActivities->sum('hours');
        $activitiesLoggedCount = $approvedActivities->count();

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
            'totalHours',
            'activitiesLoggedCount',
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

        $lockedFields = $user->staffLockedFields($actingUser);
        $correctionFields = $user->staffCorrectionFields($actingUser);

        return view('users.edit', compact('user', 'branches', 'divisions', 'redCrossUnits', 'lockedFields', 'correctionFields'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        // An anonymized account is permanent: no edits, no restore.
        if ($user->anonymized_at !== null) {
            return redirect()->route('users.show', $user);
        }

        // The "Anonymize" button submits the whole edit form; nothing else in
        // it is saved in that request.
        if ($request->input('action') === 'anonymize') {
            return $this->anonymizeFromEdit($request, $user);
        }

        $request->merge(PhoneNumber::stripFormattingFields($request->all()));

        // Self-managed account: personal details belong to the person (My
        // Profile). Locked fields are not on the form; a submitted value that
        // differs from the stored one is refused, not ignored.
        $lockedFields = $user->staffLockedFields(Auth::user());
        $correctionFields = $user->staffCorrectionFields(Auth::user());

        $rules = [
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
            'national_id_number' => ['nullable', new NationalIdNumberRule($user->id)],
            'red_cross_id_number' => 'nullable|string|max:50',
            'organisation' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'residential_address' => 'nullable|string|max:500',
            'workplace_address' => 'nullable|string|max:500',
            'telephone1' => ['nullable', 'string', 'max:20', new NigerianMobileNumber($user->telephone1)],
            'telephone2' => ['nullable', 'string', 'max:20', new NigerianMobileNumber($user->telephone2)],
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
            'correction_reason' => 'nullable|string|max:500',
        ];

        foreach ($lockedFields as $field) {
            unset($rules[$field]);
        }

        $validator = Validator::make($request->all(), $rules);

        $correctedFields = [];

        $validator->after(function ($validator) use ($request, $user, $lockedFields, $correctionFields, &$correctedFields) {
            foreach ($lockedFields as $field) {
                if ($request->exists($field) && $this->personalFieldChanged($user, $field, $request->input($field))) {
                    $validator->errors()->add($field, Str::ucfirst(str_replace('_', ' ', $field))
                        .' cannot be changed here. This person manages their own details in My Profile.');
                }
            }

            $correctedFields = array_values(array_filter(
                $correctionFields,
                fn ($field) => $request->exists($field) && $this->personalFieldChanged($user, $field, $request->input($field))
            ));

            if ($correctedFields && blank($request->input('correction_reason'))) {
                $validator->errors()->add('correction_reason',
                    'Give a reason for correcting the name, gender or birth year of a person who manages their own details.');
            }
        });

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
        $lifecycleBefore = $user->lifecycle_status;
        $wasArchived = ($lifecycleBefore === 'archived');
        $wantsArchived = $request->boolean('is_inactive'); // checkbox = "Archived"
        $lifecycleChanging = ($wantsArchived !== $wasArchived);

        if ($lifecycleChanging) {

            // Safety: prevent archiving yourself
            if ($wantsArchived && Auth::id() === $user->id) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['is_inactive' => 'You cannot archive your own account.']);
            }

            // Same rule as self-archive (ProfileController::selfArchive): the
            // role must be removed first.
            if ($wantsArchived && $user->getRoleNames()->isNotEmpty()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['is_inactive' => 'This person holds an administrative role. Ask another administrator to remove it before archiving this account.']);
            }

            if ($wantsArchived) {
                $user->markArchived(Auth::user());
            }
            // Reactivation target is decided below, once $newUnitId (this
            // request's RCU assignment) is known — see "Red Cross Unit
            // assignment logic".
        }

        // Never mass-assign archive controls through $validated
        unset($validated['is_inactive'], $validated['lifecycle_status']);

        // Capture opt-out state before update; handle timestamps explicitly below
        $wasEmailOptOut = (bool) $user->email_opt_out;
        $wasSmsOptOut = (bool) $user->sms_opt_out;
        $newEmailOptOut = $request->boolean('email_opt_out');
        $newSmsOptOut = $request->boolean('sms_opt_out');
        unset($validated['email_opt_out'], $validated['sms_opt_out']);

        // Reset email verification only when the address itself changes. A
        // password change says nothing about the email, and clearing it there
        // stranded users on servers with no email provider. An address changed
        // to empty ends up null/null, which EnsureEmailIsVerifiedOrAbsent lets through.
        $emailChanged = array_key_exists('email', $validated) && $validated['email'] !== $user->email;
        if ($emailChanged) {
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
        if (! in_array('title', $lockedFields, true)) {
            $validated['title'] = $request->input('title');
        }

        // Login details staff may always change — logged in the Audit Log. A
        // self-managed person is told by email of an email or password change
        // (after the save, below); phone changes are only logged.
        $contactOld = [];
        $contactNew = [];
        foreach (['email', 'telephone1', 'telephone2'] as $field) {
            if (array_key_exists($field, $validated)
                && (string) ($validated[$field] ?? '') !== (string) ($user->$field ?? '')) {
                $contactOld[$field] = $user->$field;
                $contactNew[$field] = $validated[$field];
            }
        }
        $passwordSet = $request->filled('password');

        // Unit, opt-outs and contribution type — one Audit Log entry with the
        // fields that changed (written after the save, below).
        $detailsOld = [];
        $detailsNew = [];
        // As ?int: the stored id is an int, the submitted one a string.
        $unitIdBefore = blank($user->red_cross_unit_id) ? null : (int) $user->red_cross_unit_id;
        $unitIdAfter = blank($validated['red_cross_unit_id'] ?? null) ? null : (int) $validated['red_cross_unit_id'];
        if ($unitIdBefore !== $unitIdAfter) {
            $detailsOld += ['red_cross_unit_id' => $unitIdBefore, 'red_cross_unit' => $user->redCrossUnit?->name];
            $detailsNew += ['red_cross_unit_id' => $unitIdAfter, 'red_cross_unit' => $unitIdAfter ? RedCrossUnit::find($unitIdAfter)?->name : null];
        }
        if ($wasEmailOptOut !== $newEmailOptOut) {
            $detailsOld['email_opt_out'] = $wasEmailOptOut;
            $detailsNew['email_opt_out'] = $newEmailOptOut;
        }
        if ($wasSmsOptOut !== $newSmsOptOut) {
            $detailsOld['sms_opt_out'] = $wasSmsOptOut;
            $detailsNew['sms_opt_out'] = $newSmsOptOut;
        }
        $contributionBefore = $user->can_contribute_member
            ? ($user->can_contribute_volunteering ? 'volunteering and member' : 'member')
            : ($user->can_contribute_volunteering ? 'volunteering' : null);
        if ($contributionBefore !== $request->input('contribution_type')) {
            $detailsOld['contribution_type'] = $contributionBefore;
            $detailsNew['contribution_type'] = $request->input('contribution_type');
        }

        // A national DB administrator's correction of a self-managed person's
        // name, gender or birth year (the reason was required above).
        $correctionOld = [];
        $correctionNew = [];
        foreach ($correctedFields as $field) {
            $correctionOld[$field] = $user->$field;
            $correctionNew[$field] = $validated[$field] ?? null;
        }
        $correctionReason = trim((string) $request->input('correction_reason'));
        unset($validated['correction_reason']);

        // -------------------------------
        // Red Cross Unit assignment logic
        // -------------------------------
        $oldUnitId = $unitIdBefore;
        $newUnitId = $unitIdAfter;

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
        // Reactivation target status
        // -------------------------------
        // Decided here (not above) so a same-request RCU change is judged on
        // $newUnitId, not a stale $user->red_cross_unit_id. Same rule as
        // User::promoteFromPendingIfQualified(): an RCU assignment or a
        // current personal fee of any type. $newUnitId is already validated
        // as an active unit above, so its presence alone is sufficient.
        // Neither basis → back to pending_engagement instead of being
        // blindly marked active.
        $reactivatingToActive = false;

        if ($lifecycleChanging && ! $wantsArchived) {
            if ($newUnitId !== null || $user->hasCurrentPersonalFee()) {
                $user->lifecycle_status = 'active';
                $reactivatingToActive = true;
            } else {
                $user->lifecycle_status = 'pending_engagement';
            }
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

        // Block branch change if user holds a branch- or division-scoped role
        // — role must be removed first to avoid branch/role mismatch. National
        // roles are exempt: their authority isn't derived from branch_id/
        // division_id (see User::getScopedId()), so moving them is harmless.
        if ($branchChanged && $user->hasAnyRole(array_merge(User::BRANCH_ROLES, User::DIVISION_ROLES))) {
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
        try {
            $user->update($validated);
        } catch (QueryException $e) {
            NationalIdNumberRule::rethrowIfDuplicate($e);
            throw $e;
        }

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

        $logContext = ['branch_id' => $user->branch_id, 'division_id' => $user->division_id];

        if ($contactNew) {
            AuditLog::write(
                'user_contact_changed',
                $user,
                $logContext,
                $contactOld,
                $contactNew,
                "DB-{$user->id} login details changed on users/edit by DB-".Auth::id().' ('.implode(', ', array_keys($contactNew)).').'
            );
        }

        if ($detailsNew) {
            AuditLog::write(
                'user_details_changed',
                $user,
                $logContext,
                $detailsOld,
                $detailsNew,
                "DB-{$user->id} unit, opt-out or contribution type changed on users/edit by DB-".Auth::id()
                    .' ('.implode(', ', array_keys($detailsNew)).').'
            );
        }

        if ($passwordSet) {
            AuditLog::write(
                'user_password_set_by_staff',
                $user,
                $logContext,
                null,
                null,
                "DB-{$user->id} password set on users/edit by DB-".Auth::id().'.'
            );
        }

        if ($correctionNew) {
            AuditLog::write(
                'personal_details_corrected',
                $user,
                $logContext,
                $correctionOld,
                $correctionNew + ['reason' => $correctionReason],
                "DB-{$user->id} manages their own details; ".implode(', ', array_keys($correctionNew))
                    .' corrected by DB-'.Auth::id().'. Reason: '.$correctionReason
            );
        }

        // Restores are audited by User's updating hook (user_unarchived).
        if ($lifecycleChanging && $wantsArchived) {
            AuditLog::write(
                'user_archived',
                $user,
                ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
                ['lifecycle_status' => $lifecycleBefore],
                ['lifecycle_status' => 'archived', 'archived_by_id' => $user->archived_by_id],
                "DB-{$user->id} archived on users/edit by DB-".Auth::id().'.'
            );
        }

        // Reactivated straight to 'active' above? Re-check staleness now
        // (activity recency / membership validity) instead of leaving a
        // possibly-stale user sitting as active until the next 03:00
        // lifecycle:reconcile run. Mirrors the lift-then-recompute pattern
        // used for archived-member reactivation via record approval
        // (Approvable::approve()).
        if ($reactivatingToActive) {
            $user->recalculateLifecycle();
        }

        // 👉 Mark user active if assigned to a unit while pending_engagement (not on
        // unassignment, and not for an already-active/dormant user being reassigned —
        // that would silently override a dormant user's staleness status with no
        // real activity signal behind it)
        if ($unitChanged && $newUnitId !== null) {
            $user->promoteFromPendingIfQualified(touchActivity: true);
        }

        // 👉 Touch admin activity separately
        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        // Send a fresh verification email to the new address, as
        // ProfileController does. Sent last, and a mail failure (e.g. a server
        // with no mail provider) is only logged — the update is already saved.
        if ($emailChanged && $user->email) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::warning('UserController@update: verification email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $noticeSentTo = null;
        if ($user->isSelfManaged() && (array_key_exists('email', $contactNew) || $passwordSet)) {
            $noticeSentTo = $this->notifyAccountDetailsChanged($user, $contactOld, $contactNew, $passwordSet);
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User updated successfully.'
                .($noticeSentTo ? ' A notice was sent to '.PhoneNumber::maskEmail($noticeSentTo).'.' : ''));
    }

    /**
     * Whether a submitted value for a personal field differs from the stored
     * one, ignoring blank-vs-null, surrounding spaces and NIN formatting.
     */
    private function personalFieldChanged(User $user, string $field, mixed $submitted): bool
    {
        $normalize = fn ($value) => $field === 'national_id_number'
            ? NationalIdNumber::normalize($value === null ? null : (string) $value)
            : trim((string) ($value ?? ''));

        return $normalize($submitted) !== $normalize($user->$field);
    }

    /**
     * Email a self-managed person that staff changed their email address or
     * set a password: to the old address when the email changed (so a wrong
     * change is noticed), otherwise to the current one. Phone changes are only
     * in the Audit Log. No email on file → nothing sent. A mail failure is
     * only logged.
     * Returns the address the notice went to, or null if none was sent.
     */
    private function notifyAccountDetailsChanged(User $user, array $contactOld, array $contactNew, bool $passwordSet): ?string
    {
        $to = array_key_exists('email', $contactOld) ? ($contactOld['email'] ?: $user->email) : $user->email;
        if (! $to) {
            return null;
        }

        $changes = [];
        if (array_key_exists('email', $contactNew)) {
            $changes[] = blank($contactNew['email'])
                ? 'Email address removed'
                : "Email address changed to {$contactNew['email']}";
        }
        if ($passwordSet) {
            $changes[] = 'A new password was set';
        }

        try {
            $user->notify(new AccountDetailsChanged($to, $changes));
        } catch (\Throwable $e) {
            Log::warning('UserController@update: account-details notice failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $to;
    }

    /**
     * Manual anonymization from users/edit (National DB admin, anonymize_user).
     * Requires both confirmation boxes and the admin's current password, as
     * a re-authentication: routes/web.php has no separate route for this, so
     * the password.confirm middleware cannot be used.
     */
    private function anonymizeFromEdit(Request $request, User $user)
    {
        abort_unless($request->user()->can('anonymize_user'), 403);

        $validator = Validator::make($request->all(), [
            'anonymize_confirm' => 'accepted',
            'anonymize_dpo_consulted' => 'accepted',
            'anonymize_password' => ['required', 'current_password'],
        ], [
            'anonymize_confirm.accepted' => 'Tick "Anonymize this account" to confirm.',
            'anonymize_dpo_consulted.accepted' => 'Confirm that you have consulted the Data Protection Officer.',
            'anonymize_password.required' => 'Enter your password to confirm.',
            'anonymize_password.current_password' => 'The password is incorrect.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('anonymize_password'));
        }

        try {
            app(UserAnonymizer::class)->anonymize($user, $request->user(), 'manual');
        } catch (\DomainException $e) {
            return redirect()->back()->withErrors(['anonymize' => $e->getMessage()]);
        }

        $request->user()->touchLastAdminActivity();

        return redirect()
            ->route('users.show', $user)
            ->with('success', "DB-{$user->id} has been anonymized.");
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

        $selectedUser = null;
        $userRole = null;
        // What the role dropdown may offer for the selected user — the same rules
        // updateRoles() enforces (blockedRoleRemovals()), so nothing shown is refused.
        $roleOptions = collect();
        $currentRoleAssignable = true;
        $canRemoveCurrentRole = true;
        // A current role the actor cannot assign may still be kept (submitted
        // unchanged) when the actor may change its special permissions — e.g. a
        // national admin setting a division assistant's tick boxes.
        $canKeepCurrentRole = false;
        // Special-permission tick boxes, per role the dropdown can end up on.
        $specialSections = [];
        $otherFinancialApprovers = null;

        if ($request->filled('user_id')) {
            $selectedUser = User::with('roles', 'permissions')->find($request->user_id);
            if ($selectedUser) {
                $userRole = $selectedUser->getRoleNames()->first();

                $currentRoles = $selectedUser->getRoleNames();
                $currentRoleAssignable = $userRole === null || $roles->contains('name', $userRole);
                $canRemoveCurrentRole = $this->blockedRoleRemovals($assigningUser, $currentRoles, '')->isEmpty();
                $roleOptions = $roles->filter(fn ($role) => $role->name === $userRole
                    || $this->blockedRoleRemovals($assigningUser, $currentRoles, $role->name)->isEmpty())->values();
                $canKeepCurrentRole = ! $currentRoleAssignable && SpecialPermissions::canSetAny($assigningUser, $userRole);

                $previousKeys = array_keys(SpecialPermissions::forRole($userRole));
                $heldKeys = SpecialPermissions::heldKeys($selectedUser, $userRole);
                $sectionRoles = $roleOptions->pluck('name')->push($userRole)->filter()->unique();
                foreach ($sectionRoles as $sectionRole) {
                    $boxes = [];
                    foreach (SpecialPermissions::forRole($sectionRole) as $key => $option) {
                        $boxes[$key] = $option + [
                            'checked' => in_array($key, $previousKeys, true)
                                ? in_array($key, $heldKeys, true)
                                : $option['default'],
                            'editable' => SpecialPermissions::canSet($assigningUser, $sectionRole, $key),
                        ];
                    }
                    if ($boxes !== []) {
                        $specialSections[$sectionRole] = $boxes;
                    }
                }

                if (isset($specialSections['branch_db_administrator'])) {
                    $otherFinancialApprovers = SpecialPermissions::otherBranchFinancialApprovers($selectedUser);
                }
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

        return view('users.edit-roles', compact('roles', 'selectedUser', 'userRole', 'accessLevel', 'isSuperAdmin', 'usersForTable', 'roleOptions', 'currentRoleAssignable', 'canRemoveCurrentRole', 'canKeepCurrentRole', 'specialSections', 'otherFinancialApprovers'));
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
            'role' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== self::REMOVE_ROLE
                    && ! \Spatie\Permission\Models\Role::where('name', $value)->exists()) {
                    $fail('The selected role is invalid.');
                }
            }],
            // Special-permission tick box keys (SpecialPermissions), not raw permission names.
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(SpecialPermissions::allKeys())],
            // Sent by the form with the tick boxes; without it, unticked = "not sent".
            'special_permissions_shown' => 'nullable|boolean',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $assigningUser = Auth::user();

        // An empty or missing role is "no change" — nothing is touched, not even direct
        // permissions. Only REMOVE_ROLE removes a role.
        if (! $request->filled('role')) {
            return redirect()
                ->route('users.roles.edit', ['user_id' => $user->id])
                ->with('info', 'No change made.');
        }

        // Refusals below are validation errors on the form, not 403s. user_id errors
        // refuse the target outright (back to the search); role errors refuse the change.
        $refuseTarget = fn (string $message) => redirect()
            ->route('users.roles.edit')
            ->withErrors(['user_id' => $message]);
        $refuseRole = fn (string $message) => redirect()
            ->route('users.roles.edit', ['user_id' => $user->id])
            ->withErrors(['role' => $message]);

        // Security check: Prevent branch-level admins from editing national-level users.
        if ($assigningUser->getAccessLevel() === 'branch' && $user->getAccessLevel() === 'national') {
            return $refuseTarget('Branch-level administrators cannot modify the roles of national-level users.');
        }

        // Security check: branch-level admins only manage people in their own branch.
        // searchUsersForRoles() already limits the search; the form is reachable
        // directly via ?user_id=, so the change itself must refuse too.
        if ($assigningUser->getAccessLevel() === 'branch'
            && (int) $user->branch_id !== (int) $assigningUser->getScopedId()) {
            return $refuseTarget('Branch-level administrators can only modify users in their own branch.');
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

        // Security check: a super-admin's role is never changed here, by anyone. Before
        // this guard it was refused only because no role holds authorize_super-admin;
        // super-admins are also left out of searchUsersForRoles() (selectableForEntry).
        // Their own account is caught by the self-guard above.
        if ($user->is_super_admin || $user->hasRole('super-admin')) {
            return $refuseTarget('A super-admin\'s role cannot be changed on this page.');
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
            return $refuseTarget('Branch-level administrators cannot modify the role of another Branch Secretary or Branch DB Administrator. Contact a National DB Administrator.');
        }

        // From here on '' means "remove the role" (the form's REMOVE_ROLE).
        $incomingRole = $validated['role'] === self::REMOVE_ROLE ? '' : $validated['role'];

        // Never leave the system without a National DB Administrator.
        if ($user->hasRole('national_db_administrator')
            && $incomingRole !== 'national_db_administrator'
            && User::role('national_db_administrator')->count() <= 1) {
            return $refuseRole('This is the last National DB Administrator. Appoint another one before removing or changing this role.');
        }

        // Security check: appointing and removing are separate. The incoming role must
        // be one the actor may assign (authorize_*, getAssignableRoles()): a target not
        // caught above (e.g. a plain branch_db_assistant) cannot be promoted to a role —
        // such as national_db_administrator or super-admin — the actor holds no
        // authorize_* permission for. Every current role being removed or replaced must
        // be one the actor may remove (getRemovableRoles(), which adds revoke_*), apart
        // from super-admin's promotion rule (see blockedRoleRemovals()).
        // Keeping the target's current role is not assigning it (e.g. a national admin
        // changing a division assistant's tick boxes); blockedRoleRemovals() below
        // still governs any role that would be removed.
        $keepsCurrentRole = $incomingRole !== '' && $user->hasRole($incomingRole);
        if ($incomingRole !== '' && ! $keepsCurrentRole
            && ! $assigningUser->getAssignableRoles()->contains('name', $incomingRole)) {
            return $refuseRole(sprintf('You are not authorized to assign the role "%s".', $this->roleLabel($incomingRole)));
        }

        $blockedRoles = $this->blockedRoleRemovals($assigningUser, $user->getRoleNames(), $incomingRole);
        if ($blockedRoles->isNotEmpty()) {
            return $refuseRole(sprintf(
                'You are not authorized to %s the role "%s".',
                $incomingRole === '' ? 'remove' : 'change',
                $blockedRoles->map(fn ($role) => $this->roleLabel($role))->implode('", "')
            ));
        }

        // Special permissions (SpecialPermissions): a ticked box must belong to the
        // incoming role. Which boxes the actor may change, defaults for a new role,
        // and dropping boxes the new role does not offer (incl. "no role" = none)
        // are all decided in SpecialPermissions::resolve().
        $submittedKeys = array_values($validated['permissions'] ?? []);
        $foreignKeys = array_diff($submittedKeys, array_keys(SpecialPermissions::forRole($incomingRole)));
        if ($foreignKeys !== []) {
            return redirect()
                ->route('users.roles.edit', ['user_id' => $user->id])
                ->withErrors(['permissions' => 'These special permissions do not belong to the selected role: '
                    .implode(', ', $foreignKeys).'.']);
        }

        $sentTickBoxes = $request->boolean('special_permissions_shown') || $submittedKeys !== [];
        $incomingPermissions = SpecialPermissions::resolve(
            $assigningUser,
            $user,
            $incomingRole,
            $sentTickBoxes ? $submittedKeys : null
        );

        $oldRoles = $user->getRoleNames()->sort()->values()->all();
        $oldPermissions = $user->getPermissionNames()->sort()->values()->all();

        $user->syncRoles(! empty($incomingRole) ? [$incomingRole] : []);
        $user->syncPermissions(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $incomingPermissions));

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
     * The target's current roles that $actor may NOT remove when setting $incomingRole
     * ('' = no role). A role kept unchanged is not removed. Removable means in
     * getRemovableRoles(), or — super-admin promotion — the actor is a super-admin
     * making the target a national_db_administrator and the role being replaced is
     * neither super-admin nor national_db_administrator. Clearing to no role gets no
     * such exception. Shared by updateRoles() and editRoles() so the form offers only
     * what the server accepts.
     */
    private function blockedRoleRemovals(User $actor, \Illuminate\Support\Collection $currentRoles, string $incomingRole): \Illuminate\Support\Collection
    {
        $removableRoleNames = $actor->getRemovableRoles()->pluck('name');
        $isSuperAdminPromotion = $actor->hasRole('super-admin') && $incomingRole === 'national_db_administrator';

        return $currentRoles
            ->reject(fn ($role) => $role === $incomingRole)
            ->reject(fn ($role) => $removableRoleNames->contains($role))
            ->reject(fn ($role) => $isSuperAdminPromotion
                && ! in_array($role, ['super-admin', 'national_db_administrator'], true))
            ->values();
    }

    private function roleLabel(string $role): string
    {
        return Str::title(str_replace('_', ' ', $role));
    }

    /**
     * Search for users to manage their roles (for AJAX requests).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsersForRoles(Request $request, UserFilterService $userFilterService)
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

        // Same rules as the users list: a number is a DB code / phone / NIN,
        // '@' searches email from the start, other text names only.
        $users = $userFilterService->applySearch($query, (string) $search)
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

        $idCandidate = preg_replace('/^db-/i', '', trim($query));

        $usersQuery = User::selectableForEntry()->notInactive()->where(function ($q) use ($query, $idCandidate) {
            $q->where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->orWhere('middle_name', 'like', "%{$query}%")
                ->orWhere('user_code', 'like', "%{$query}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhere('id', is_numeric($idCandidate) ? (int)$idCandidate : -1);
        });

        // A person without an email cannot receive campaign messages or make
        // payments/donations online, so cannot usefully serve as an
        // organisation contact — this filter is opt-in via require_email,
        // scoped to the organisation-linking search only, so it doesn't
        // affect other callers of this shared endpoint.
        if ($request->boolean('require_email')) {
            $usersQuery->whereNotNull('email');
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
                'text' => 'Expired '.Carbon::parse($payment->expiry_date)->format('M d, Y'),
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
            return $donation->amount
                ? $donation->amount . ' x ' . ($donation->donation_item ?? 'items')
                : 'No quantity recorded';
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
            'text' => 'No expiry',
            'class' => 'bg-gray-100 text-gray-800',
        ];
    }

    /**
     * Get activity hours display text
     */
    private function getActivityHours($activity)
    {
        if ($activity->hours) {
            return $activity->hours.'h';
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
