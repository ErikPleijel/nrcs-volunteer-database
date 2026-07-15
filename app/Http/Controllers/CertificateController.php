<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\SignatureTitle;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



class CertificateController extends Controller
{
    /**
     * Display a list of trainings to generate certificates for.
     * Includes filtering and searching based on user access level.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        $userDivisionId = null;
        $userDivision = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userDivisionId = $scopedId;
                $userBranchId = $userDivision->branch_id;
            }
        }

        // 1️⃣ Available certificate types
        $certificateTypes = [
            'training_competence' => 'Training – Competence',
            'training_attendance' => 'Training – Attendance',
            'membership' => 'Membership',
            'volunteering' => 'Volunteering',
            'donation' => 'Donation',
        ];

        // 2️⃣ Selected type (default = training competence)
        $certificateType = $request->input('certificate_type', 'training_competence');
        if (!array_key_exists($certificateType, $certificateTypes)) {
            $certificateType = 'training_competence';
        }

        // 3️⃣ Base query depends on certificate type
        switch ($certificateType) {
            case 'training_competence':
                $query = Training::query()
                    ->active()
                    ->whereHas('user');
                // Later, if needed:
                // ->where('certificate_mode', 'competence');
                break;

            case 'training_attendance':
                $query = Training::query()
                    ->active()
                    ->whereHas('user');
                // Later, if needed:
                // ->where('certificate_mode', 'attendance');
                break;

            case 'membership':
                $query = MembershipPayment::query()
                    ->valid()
                    ->personal()
                    ->whereHas('user');
                break;

            case 'volunteering':
                // The base query is now User, constrained to those with activities
                $query = User::query()
                    ->whereHas('activities', function ($q) {
                        $q->active();
                    })
                    ->whereHas('redCrossUnit', function ($rcuQuery) {
                        $rcuQuery->where('is_active', true);
                    })
                    // Eager-load the count of active activities
                    ->withCount(['activities as activities_count' => function ($q) {
                        $q->active();
                    }])
                    // Eager-load the sum of hours from active activities
                    ->withSum(['activities as activities_sum_hours' => function ($q) {
                        $q->active();
                    }], 'hours')
                    // Add the latest activity date for sorting
                    ->addSelect(['latest_activity_date' => Activity::select('date')
                        ->whereColumn('user_id', 'users.id')
                        ->active()
                        ->orderByDesc('date')
                        ->limit(1)
                    ]);
                break;

            case 'donation':
                // The base query is now User, constrained to those with donations.
                $query = User::query()
                    ->whereHas('donations', function ($q) {
                        $q->notDeleted()->personal();
                    })
                    // Count of cash donations. A NULL in_kind_donation (type never
                    // set) defaults to cash — matches User::countCashDonations().
                    ->withCount(['donations as cash_donations_count' => function ($q) {
                        $q->notDeleted()->personal()->where(function ($q2) {
                            $q2->where('in_kind_donation', false)->orWhereNull('in_kind_donation');
                        });
                    }])
                    // Count of in-kind donations
                    ->withCount(['donations as in_kind_donations_count' => function ($q) {
                        $q->notDeleted()->personal()->where('in_kind_donation', true);
                    }])
                    // Sum of cash donations. Same NULL-defaults-to-cash rule as above.
                    ->withSum(['donations as donations_sum_amount' => function ($q) {
                        $q->notDeleted()->personal()->where(function ($q2) {
                            $q2->where('in_kind_donation', false)->orWhereNull('in_kind_donation');
                        });
                    }], 'amount')
                    // Add the latest donation date for sorting
                    ->addSelect(['latest_donation_date' => Donation::select('date_donation')
                        ->whereColumn('user_id', 'users.id')
                        ->notDeleted()
                        ->personal()
                        ->orderByDesc('date_donation')
                        ->limit(1)
                    ]);
                break;


            default:
                $query = Training::query()
                    ->active()
                    ->whereHas('user');
                break;
        }

        // Determine if the base query is directly on the User model
        $isUserQuery = in_array($certificateType, ['donation', 'volunteering']);

        // Scope query based on user access level
        switch ($accessLevel) {
            case 'branch':
                if (isset($userBranchId)) {
                    $query->where('branch_id', $userBranchId);
                }
                break;

            case 'division':
                if (isset($userDivisionId)) {
                    $query->where('division_id', $userDivisionId);
                }
                break;
        }

        // Text search
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');

            $searchLogic = function ($q) use ($searchTerm) {
                $q->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->where('id', $searchTerm) // Search by User ID
                    ->orWhere('first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"]);
                });
            };

            if ($isUserQuery) {
                $query->where($searchLogic);
            } else {
                $query->whereHas('user', $searchLogic);
            }
        }

        // Branch filter (national only)
        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        // Division filter (national + branch)
        if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
            $query->where('division_id', $request->input('division_id'));
        }

        // Red Cross Unit filter
        if ($request->filled('red_cross_unit_id')) {
            $unitId = $request->input('red_cross_unit_id');
            if ($isUserQuery) {
                // The base query is User, which has a direct red_cross_unit_id
                $query->where('red_cross_unit_id', $unitId);
            } else {
                // The base query is Activity/Training, which has a user relationship
                $query->whereHas('user', function ($q) use ($unitId) {
                    $q->where('red_cross_unit_id', $unitId);
                });
            }
        }

        // Training type filter (relevant mainly for training_* types)
        if (str_starts_with($certificateType, 'training_') && $request->filled('training_type_id')) {
            $query->where('training_type_id', $request->input('training_type_id'));
        }

        // Print status filter
        if ($request->filled('print_status')) {
            $printStatus     = $request->input('print_status');
            $isTrainingType  = in_array($certificateType, ['training_competence', 'training_attendance']);
            $isUserBasedType = in_array($certificateType, ['membership', 'volunteering', 'donation']);

            if ($printStatus === 'printed') {
                if ($isTrainingType) {
                    $query->whereHas('certificatePrints', function ($q) use ($certificateType) {
                        $q->where('certificate_type', $certificateType)->whereNull('deleted_at');
                    });
                } elseif ($isUserBasedType) {
                    $query->whereHas('certificatePrints', function ($q) use ($certificateType) {
                        $q->where('certificate_type', $certificateType)->whereNull('deleted_at');
                    });
                }
            } elseif ($printStatus === 'not_printed') {
                if ($isTrainingType) {
                    $query->whereDoesntHave('certificatePrints', function ($q) use ($certificateType) {
                        $q->where('certificate_type', $certificateType)->whereNull('deleted_at');
                    });
                } elseif ($isUserBasedType) {
                    $query->whereDoesntHave('certificatePrints', function ($q) use ($certificateType) {
                        $q->where('certificate_type', $certificateType)->whereNull('deleted_at');
                    });
                }
            }
        }

        $withRelations = ['user.redCrossUnit', 'branch', 'division'];
        $dateColumn = 'created_at';

        switch ($certificateType) {
            case 'training_competence':
            case 'training_attendance':
                $withRelations[] = 'trainingType';
                $dateColumn = 'training_date';
                break;
            case 'membership':
                $withRelations[] = 'membershipFee';
                $dateColumn = 'payment_date';
                break;
            case 'volunteering':
                // For volunteering, the base query is on User, so relations are direct
                $withRelations = ['redCrossUnit', 'branch', 'division'];
                // We'll sort by the subquery alias we added
                $dateColumn = 'latest_activity_date';
                break;
            case 'donation':
                // For donation, the base query is on User, so relations are direct
                $withRelations = ['redCrossUnit', 'branch', 'division'];
                // We'll sort by the subquery alias we added
                $dateColumn = 'latest_donation_date';
                break;

        }

        $records = $query
            ->with($withRelations)
            ->latest($dateColumn)
            ->paginate(24)
            ->withQueryString();

        // Build a set of already-printed item keys for this certificate type
        $isTrainingCert  = in_array($certificateType, ['training_competence', 'training_attendance']);
        $isUserBasedCert = in_array($certificateType, ['membership', 'volunteering', 'donation']);

        $printedKeys = collect();

        if ($isTrainingCert) {
            $trainingIds = $records->pluck('id');
            $printedKeys = CertificatePrint::whereIn('training_id', $trainingIds)
                ->where('certificate_type', $certificateType)
                ->whereNull('deleted_at')
                ->pluck('training_id')
                ->flip(); // use as a set: training_id => true
        } elseif ($isUserBasedCert) {
            $userIds = $records->pluck('id'); // base query is User for these types
            if ($certificateType === 'membership') {
                $userIds = $records->pluck('user_id');
            }
            $printedKeys = CertificatePrint::whereIn('user_id', $userIds)
                ->where('certificate_type', $certificateType)
                ->whereNull('deleted_at')
                ->pluck('user_id')
                ->flip();
        }

        // Data for dropdowns, respecting access levels
        $branches = collect();
        $divisions = collect();
        $redCrossUnits = collect();

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
                if ($userBranchId) {
                    $branches = Branch::where('id', $userBranchId)->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $userBranchId)->orderBy('name')->get();
                }
                break;

            case 'division':
                if ($userDivision) {
                    $branches = Branch::where('id', $userDivision->branch_id)->orderBy('name')->get();
                    $divisions = Division::where('id', $userDivisionId)->orderBy('name')->get();
                }
                break;
        }

        $selectedDivisionId = $request->input('division_id', $userDivisionId);
        if ($selectedDivisionId) {
            // Prevent “escaping” division if user is division-scoped
            if (!($accessLevel === 'division' && $userDivisionId && (string)$userDivisionId !== (string)$selectedDivisionId)) {
                $redCrossUnits = RedCrossUnit::where('division_id', $selectedDivisionId)
                    ->orderBy('name')
                    ->get();
            }
        }

        $trainingTypes = TrainingType::orderBy('name')->get();

        // Signature titles for dropdowns
        $signatureTitles = SignatureTitle::includeInList()
            ->orderBy('name')
            ->get();

        // Signature image files
        $signaturesDir   = public_path('images/signatures');
        $signatureImages = is_dir($signaturesDir)
            ? array_map('basename', glob($signaturesDir . '/*.png') ?: [])
            : [];

        // Get remembered signature selections from session
        $selectedSign1Id = session('selected_sign_1_id', '_line_only_');
        $selectedSign2Id = session('selected_sign_2_id', '_line_only_');

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return view('certificates.index', compact(
            'records',
            'branches',
            'divisions',
            'redCrossUnits',
            'trainingTypes',
            'accessLevel',
            'userBranchId',
            'userDivisionId',
            'signatureTitles',
            'certificateTypes',
            'certificateType',
            'selectedSign1Id',
            'selectedSign2Id',
            'signatureImages',
            'printedKeys'
        ));
    }

    /**
     * Helper: build one branded training certificate payload.
     */
    protected function buildBrandedTrainingCertificateData(
        Training $training,
        ?string  $sign1Title = null,
        mixed    $sign2Title = null
    ): array
    {
        $training->loadMissing('trainingType', 'user');

        $signaturesCount = ($sign2Title === false) ? 1 : 2;
        $defaultSign1 = $sign1Title ?? 'Secretary General';
        $defaultSign2 = ($signaturesCount === 2) ? ($sign2Title ?? 'National Training Coordinator') : null;

        $startDate = $training->training_date->format('F j, Y');

        $endDate = null;
        if ($training->duration && $training->duration > 1) {
            $endDate = $training->training_date->copy()->addDays($training->duration - 1)->format('F j, Y');
        }

        if ($endDate && $endDate !== $startDate) {
            $dateLine = "from {$startDate} to {$endDate}";
        } else {
            $dateLine = "on {$startDate}";
        }

        $validLine = '';
        if ($training->valid_years) {
            $plural = $training->valid_years === 1 ? 'year' : 'years';
            $validLine = "Valid for {$training->valid_years} {$plural}.";
        }

        $dateLine .= '.';

        return [
            'orgName' => 'Nigerian Red Cross Society',
            'recipientName' => $training->user->full_name,
            'primaryCertifyText' => 'This is to certify that',
            'certifyText' => 'has successfully completed the training course on',
            'courseTitle' => $training->trainingType->name,
            'dateLine' => $dateLine,
            'validLine' => $validLine,
            'trainingDate' => $startDate,
            'trainingEndDate' => $endDate,
            'defaultSign1' => $defaultSign1,
            'defaultSign2' => $defaultSign2,
            'signaturesCount' => $signaturesCount,
            'footerLocation' => 'NRCS HQ',
            'footerProducer' => Auth::id(),
            'logoUrl' => asset('images/NRCS_logo.jpg'),
            'certificateImageUrl' => asset('images/certificates/certificate_of_competence.png'),
            'training' => $training,
            'user' => $training->user,
        ];
    }

    /**
     * Helper: build one branded attendance certificate payload.
     */
    protected function buildBrandedAttendanceCertificateData(
        Training $training,
        ?string  $sign1Title = null,
        mixed    $sign2Title = null
    ): array
    {
        $training->loadMissing('trainingType', 'user');

        $signaturesCount = ($sign2Title === false) ? 1 : 2;
        $defaultSign1 = $sign1Title ?? 'Secretary General';
        $defaultSign2 = ($signaturesCount === 2) ? ($sign2Title ?? 'National Training Coordinator') : null;

        $startDate = $training->training_date->format('F j, Y');

        $endDate = null;
        if ($training->duration && $training->duration > 1) {
            $endDate = $training->training_date->copy()->addDays($training->duration - 1)->format('F j, Y');
        }

        if ($endDate && $endDate !== $startDate) {
            $dateLine = "from {$startDate} to {$endDate}";
        } else {
            $dateLine = "on {$startDate}";
        }

        $validLine = '';
        if ($training->valid_years) {
            $plural = $training->valid_years === 1 ? 'year' : 'years';
            $validLine = "Valid for {$training->valid_years} {$plural}.";
        }

        $dateLine .= '.';

        return [
            'orgName' => 'Nigerian Red Cross Society',
            'recipientName' => $training->user->full_name,
            'primaryCertifyText' => 'This is to certify that',
            'certifyText' => 'has attended the training course on',
            'courseTitle' => $training->trainingType->name,
            'dateLine' => $dateLine,
            'validLine' => $validLine,
            'trainingDate' => $startDate,
            'trainingEndDate' => $endDate,
            'defaultSign1' => $defaultSign1,
            'defaultSign2' => $defaultSign2,
            'signaturesCount' => $signaturesCount,
            'footerLocation' => 'NRCS HQ',
            'footerProducer' => Auth::id(),
            'logoUrl' => asset('images/NRCS_logo.jpg'),
            'certificateImageUrl' => asset('images/certificates/certificate_of_attendance.png'),
            'training' => $training,
            'user' => $training->user,
        ];
    }

    /**
     * Helper: build one branded membership certificate payload.
     */
    protected function buildBrandedMembershipCertificateData(
        MembershipPayment $payment,
        ?string           $sign1Title = null,
        mixed             $sign2Title = null
    ): array
    {
        $payment->loadMissing('user', 'membershipFee', 'branch');

        $signaturesCount = ($sign2Title === false) ? 1 : 2;
        $defaultSign1 = $sign1Title ?? 'Secretary General';
        $defaultSign2 = ($signaturesCount === 2) ? ($sign2Title ?? 'Branch Chairman') : null;

        return [
            'orgName' => 'Nigerian Red Cross Society',
            'recipientName' => $payment->user->full_name,
            'primaryCertifyText' => 'This is to certify that',
            'certifyText' => 'is a registered',
            'courseTitle' => $payment->membershipFee->name . ' Member',
            'membershipType' => $payment->membershipFee->name . ' Member',
            'dateLine' => 'from ' . $payment->payment_date->format('F j, Y') . ' to ' . $payment->expiry_date->format('F j, Y'),
            'branchName' => $payment->branch->name ?? 'N/A',
            'defaultSign1' => $defaultSign1,
            'defaultSign2' => $defaultSign2,
            'signaturesCount' => $signaturesCount,
            'footerLocation' => $payment->branch->name ?? 'NRCS HQ',
            'footerProducer' => Auth::id(),
            'logoUrl' => asset('images/NRCS_logo.jpg'),
            'certificateImageUrl' => asset('images/certificates/certificate_of_membership.png'),
            'payment' => $payment,
            'user' => $payment->user,
        ];
    }

    /**
     * Helper: build one branded volunteering certificate payload.
     */
    protected function buildBrandedVolunteeringCertificateData(
        User    $user,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $signaturesCount = ($sign2Title === false) ? 1 : 2;
        $defaultSign1 = $sign1Title ?? 'Secretary General';
        $defaultSign2 = ($signaturesCount === 2) ? ($sign2Title ?? 'Branch Secretary') : null;

        $user->load(['activeActivities' => function ($query) {
            $query->with('activityType')->orderBy('date', 'desc');
        }]);

        $allActivities = $user->activeActivities;

        $totalHours = $allActivities->sum('hours');
        $totalCount = $allActivities->count();
        $maxItems = 10;

        $items = [];
        $itemNumber = 1;

        $activitiesToList = $allActivities->take($maxItems);
        foreach ($activitiesToList as $activity) {
            $items[] = [
                'number' => $itemNumber++,
                'date' => $activity->date->format('Y-m-d'),
                'activity' => $activity->activityType->name ?? 'Volunteer Activity',
                'hours' => $activity->hours ?? 0,
            ];
        }

        if ($totalCount > $maxItems) {
            $remainingHours = $allActivities->slice($maxItems)->sum('hours');
            $items[] = [
                'isSummaryRow' => true,
                'activity' => 'Total other activities',
                'hours' => $remainingHours,
            ];
        }

        $totalRow = [
            'label' => 'Total Hours',
            'hours' => $totalHours,
        ];

        return [
            'orgName' => 'Nigerian Red Cross Society',
            'recipientName' => $user->full_name,
            'primaryCertifyText' => 'This is to certify that',
            'certifyText' => 'has served our organisation with distinction in the following activities',
            'courseTitle' => '', // Removed summary text as requested
            'dateLine' => 'as of ' . now()->format('F j, Y'),
            'itemHeaders' => ['#', 'Date', 'Activity', 'Hours'],
            'items' => $items,
            'totalRow' => $totalRow,
            'defaultSign1' => $defaultSign1,
            'defaultSign2' => $defaultSign2,
            'signaturesCount' => $signaturesCount,
            'footerLocation' => $user->branch->name ?? 'NRCS HQ',
            'footerProducer' => Auth::id(),
            'logoUrl' => asset('images/NRCS_logo.jpg'),
            'certificateImageUrl' => asset('images/certificates/certificate_of_service.png'),
            'user' => $user,
        ];
    }

    /**
     * Helper: build one branded donation certificate payload.
     */
    /**
     * Helper: build one branded donation certificate payload.
     */
    protected function buildBrandedDonationCertificateData(
        User    $user,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $signaturesCount = ($sign2Title === false) ? 1 : 2;
        $defaultSign1 = $sign1Title ?? 'Secretary General';
        $defaultSign2 = ($signaturesCount === 2) ? ($sign2Title ?? 'Branch Chairman') : null;

        $user->load(['donations' => fn($q) => $q->notDeleted()->personal()->orderBy('date_donation', 'desc')]);
        $allDonations = $user->donations;

        $totalCashAmount = $allDonations->where('in_kind_donation', false)->sum('amount');
        $totalCount = $allDonations->count();
        $maxItems = 10;

        $items = [];
        $itemNumber = 1;

        $donationsToList = $allDonations->take($maxItems);
        foreach ($donationsToList as $donation) {
            $items[] = [
                'number' => $itemNumber++,
                'date' => $donation->date_donation->format('Y-m-d'),
                'description' => $donation->in_kind_donation
                    ? ($donation->donation_item ?: 'In-kind donation')
                    : 'Cash Donation',
                'amount' => $donation->in_kind_donation
                    ? (int)$donation->amount
                    : ('₦' . number_format($donation->amount, 0)),
            ];
        }

        if ($totalCount > $maxItems) {
            $remainingDonations = $allDonations->slice($maxItems);
            $remainingCash = $remainingDonations->where('in_kind_donation', false)->sum('amount');
            $remainingInKindCount = $remainingDonations->where('in_kind_donation', true)->count();

            $descParts = [];
            if ($remainingCash > 0) {
                $descParts[] = 'other cash donations';
            }
            if ($remainingInKindCount > 0) {
                $plural = $remainingInKindCount === 1 ? 'item' : 'items';
                $descParts[] = "and {$remainingInKindCount} other in-kind {$plural}";
            }

            if (!empty($descParts)) {
                $description = implode(' ', $descParts);
                $items[] = [
                    'isSummaryRow' => true,
                    'description' => "Total " . $description,
                    'amount' => ($remainingCash > 0) ? '₦' . number_format($remainingCash, 0) : '',
                ];
            }
        }

        $totalRow = [
            'label' => 'Total Cash Donations',
            'amount' => '₦' . number_format($totalCashAmount, 0),
        ];

        // 👉 Singular/plural certify text based on number of donations
        $certifyText = 'in sincere appreciation for the following generous contribution';
        if ($totalCount > 1) {
            $certifyText = 'in sincere appreciation for the following generous contributions';
        }

        return [
            'orgName' => 'Nigerian Red Cross Society',
            'recipientName' => $user->full_name,
            'primaryCertifyText' => 'This is to certify that',
            'certifyText' => $certifyText,
            'courseTitle' => '', // Removed summary text
            'dateLine' => 'as of ' . now()->format('F j, Y'),
            'itemHeaders' => ['#', 'Date', 'Item/Description', 'Amount/Quantity'],
            'items' => $items,
            'totalRow' => $totalRow,
            'defaultSign1' => $defaultSign1,
            'defaultSign2' => $defaultSign2,
            'signaturesCount' => $signaturesCount,
            'footerLocation' => $user->branch->name ?? 'NRCS HQ',
            'footerProducer' => Auth::id(),
            'logoUrl' => asset('images/NRCS_logo.jpg'),
            'certificateImageUrl' => asset('images/certificates/certificate_of_appreciation.png'),
            'user' => $user,
        ];
    }


    /**
     * Helper: build certificate payloads for a set of training IDs.
     */
    protected function buildTrainingCertificatesData(
        array   $trainingIds,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $trainings = Training::with('user', 'trainingType')
            ->whereIn('id', $trainingIds)
            ->get();

        $certificatesData = [];

        foreach ($trainings as $training) {
            $certificatesData[] = $this->buildBrandedTrainingCertificateData(
                $training,
                $sign1Title,
                $sign2Title
            );
        }

        return $certificatesData;
    }

    /**
     * Helper: build certificate payloads for a set of attendance IDs.
     */
    protected function buildAttendanceCertificatesData(
        array   $trainingIds,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $trainings = Training::with('user', 'trainingType')
            ->whereIn('id', $trainingIds)
            ->get();

        $certificatesData = [];

        foreach ($trainings as $training) {
            $certificatesData[] = $this->buildBrandedAttendanceCertificateData(
                $training,
                $sign1Title,
                $sign2Title
            );
        }

        return $certificatesData;
    }

    /**
     * Helper: build certificate payloads for a set of membership payment IDs.
     */
    protected function buildMembershipCertificatesData(
        array   $paymentIds,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $payments = MembershipPayment::with('user', 'membershipFee')
            ->whereIn('id', $paymentIds)
            ->personal()
            ->get();

        $certificatesData = [];

        foreach ($payments as $payment) {
            $certificatesData[] = $this->buildBrandedMembershipCertificateData(
                $payment,
                $sign1Title,
                $sign2Title
            );
        }

        return $certificatesData;
    }

    /**
     * Helper: build certificate payloads for a set of user IDs (Volunteering).
     */
    protected function buildVolunteeringCertificatesData(
        array   $userIds,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $users = User::withSum(['activities as activities_sum_hours' => function ($q) {
            $q->active();
        }], 'hours')
            ->withCount(['activities as activities_count' => function ($q) {
                $q->active();
            }])
            ->whereIn('id', $userIds)
            ->get();

        $certificatesData = [];
        foreach ($users as $user) {
            $certificatesData[] = $this->buildBrandedVolunteeringCertificateData(
                $user,
                $sign1Title,
                $sign2Title
            );
        }
        return $certificatesData;
    }

    /**
     * Helper: build certificate payloads for a set of user IDs (Donation).
     */
    protected function buildDonationCertificatesData(
        array   $userIds,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $users = User::withSum(['donations as donations_sum_amount' => function ($q) {
            $q->notDeleted()->personal()->where(function ($q2) {
                $q2->where('in_kind_donation', false)->orWhereNull('in_kind_donation');
            });
        }], 'amount')
            ->whereIn('id', $userIds)
            ->get();

        $certificatesData = [];
        foreach ($users as $user) {
            $certificatesData[] = $this->buildBrandedDonationCertificateData(
                $user,
                $sign1Title,
                $sign2Title
            );
        }
        return $certificatesData;
    }


    /**
     * Helper: build certificate payloads for a set of IDs, based on certificate type.
     */
    protected function buildCertificatesData(
        string  $certificateType,
        array   $itemIds,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array
    {
        $certificatesData = [];

        switch ($certificateType) {
            case 'training_competence':
                $certificatesData = $this->buildTrainingCertificatesData($itemIds, $sign1Title, $sign2Title);
                break;
            case 'training_attendance':
                $certificatesData = $this->buildAttendanceCertificatesData($itemIds, $sign1Title, $sign2Title);
                break;
            case 'membership':
                $certificatesData = $this->buildMembershipCertificatesData($itemIds, $sign1Title, $sign2Title);
                break;
            case 'volunteering':
                $certificatesData = $this->buildVolunteeringCertificatesData($itemIds, $sign1Title, $sign2Title);
                break;
            case 'donation':
                $certificatesData = $this->buildDonationCertificatesData($itemIds, $sign1Title, $sign2Title);
                break;
            default:
                return [];
        }

        // Inject the certificate type into each data array.
        return array_map(function ($certificateData) use ($certificateType) {
            $certificateData['certificate_type'] = $certificateType;
            return $certificateData;
        }, $certificatesData);
    }

    /**
     * Helper: default coords + font sizes for plain layout (pre-printed paper).
     */
    protected function buildPlainLayout(array $incoming = []): array
    {
        $defaults = [
            'coords' => [
                // Main centered text (A4 landscape, ~297mm wide, center ≈ 148mm)
                'org_name' => ['x' => 148, 'y' => 30],
                'certify_text' => ['x' => 148, 'y' => 60],
                'recipient_name' => ['x' => 148, 'y' => 90],
                'course_title_text' => ['x' => 148, 'y' => 118],
                'course_title' => ['x' => 148, 'y' => 135],
                'training_details' => ['x' => 148, 'y' => 158],

                // Signatures – left & right
                'signature_1' => ['x' => 70, 'y' => 182],
                'signature_2' => ['x' => 205, 'y' => 182],

                // Footer – centered near bottom
                'footer_info' => ['x' => 33, 'y' => 190],

                // QR Code - bottom right
                'qr_code' => ['x' => 262, 'y' => 180],
            ],

            'fontSizes' => [
                'org_name' => 24,
                'certify_text' => 16,
                'recipient_name' => 42,
                'course_title_text' => 16,
                'course_title' => 24,
                'training_details' => 14,
                'signature_1' => 12,
                'signature_2' => 12,
                'footer_info' => 10,
            ],
        ];

        return array_replace_recursive($defaults, $incoming);
    }

    /**
     * Helper: resolve selected signature titles from request.
     */
    protected function resolveSignatureTitlesFromRequest(Request $request): array
    {
        $sign1Id = $request->input('signature_1_title_id');
        $sign2Id = $request->input('signature_2_title_id');

        $signatureTitleMap = SignatureTitle::includeInList()
            ->orderBy('name')
            ->pluck('name', 'id'); // [id => name]

        $resolve = function ($id) use ($signatureTitleMap) {
            if ($id === '_none_') {
                return false; // Special marker for no signature
            }
            if ($id === '_line_only_') {
                return "\u{00A0}"; // Return a non-breaking space character (U+00A0)
            }

            if (empty($id)) {
                return null; // Null to trigger default title
            }
            return $signatureTitleMap[$id] ?? null; // DB lookup
        };

        $sign1Title = $resolve($sign1Id);
        $sign2Title = $resolve($sign2Id);


        return [$sign1Title, $sign2Title];
    }


    private function rememberSignatureSelections(Request $request)
    {
        $request->session()->put('selected_sign_1_id', $request->input('signature_1_title_id'));
        $request->session()->put('selected_sign_2_id', $request->input('signature_2_title_id'));
    }

    private function resolveSignatureImagesFromRequest(Request $request): array
    {
        $sign1Image = $request->input('signature_1_image', '');
        $sign1Name  = $request->input('signature_1_name', '');
        $sign2Image = $request->input('signature_2_image', '');
        $sign2Name  = $request->input('signature_2_name', '');

        return [
            $sign1Image ? asset('images/signatures/' . $sign1Image) : '',
            trim($sign1Name),
            $sign2Image ? asset('images/signatures/' . $sign2Image) : '',
            trim($sign2Name),
        ];
    }

    private function injectSignatureImages(array $certs, string $img1, string $name1, string $img2, string $name2): array
    {
        return array_map(function ($cert) use ($img1, $name1, $img2, $name2) {
            $cert['sign1Image'] = $img1;
            $cert['sign1Name']  = $name1;
            $cert['sign2Image'] = $img2;
            $cert['sign2Name']  = $name2;
            return $cert;
        }, $certs);
    }

    /**
     * Bulk print – plain layout for pre-printed paper.
     */
    public function bulkPrintPlain(Request $request)
    {
        $this->rememberSignatureSelections($request);

        $itemIds = $request->input('training_ids');
        $certificateType = $request->input('certificate_type');

        if (!is_array($itemIds) || empty($itemIds)) {
            return redirect()->back()->with('error', 'No certificates selected for printing.');
        }

        [$sign1Title, $sign2Title] = $this->resolveSignatureTitlesFromRequest($request);
        [$sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name] = $this->resolveSignatureImagesFromRequest($request);

        $incomingLayout = json_decode($request->input('layout', '{}'), true) ?: [];
        $layout = $this->buildPlainLayout($incomingLayout);

        $certificatesData = $this->buildCertificatesData($certificateType, $itemIds, $sign1Title, $sign2Title);
        $certificatesData = $this->injectSignatureImages($certificatesData, $sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name);

        return view('certificates.print-plain', [
            'layout' => $layout,
            'certificates' => $certificatesData,
        ]);
    }

    /**
     * Bulk print – full layout with logo & frame (landscape).
     */
    public function bulkPrintBranded(Request $request)
    {
        $this->rememberSignatureSelections($request);

        $itemIds = $request->input('training_ids');
        $certificateType = $request->input('certificate_type');

        if (!is_array($itemIds) || empty($itemIds)) {
            return redirect()->back()->with('error', 'No certificates selected for printing.');
        }

        [$sign1Title, $sign2Title] = $this->resolveSignatureTitlesFromRequest($request);
        [$sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name] = $this->resolveSignatureImagesFromRequest($request);

        $certificatesData = $this->buildCertificatesData($certificateType, $itemIds, $sign1Title, $sign2Title);
        $certificatesData = $this->injectSignatureImages($certificatesData, $sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name);

        $view = 'certificates.print-branded';

        return view($view, [
            'certificates' => $certificatesData,
        ]);
    }

    /**
     * Bulk print – full layout with logo & frame (portrait).
     */
    public function bulkPrintBrandedPortrait(Request $request)
    {
        $this->rememberSignatureSelections($request);

        $itemIds = $request->input('training_ids');
        $certificateType = $request->input('certificate_type');

        if (!is_array($itemIds) || empty($itemIds)) {
            return redirect()->back()->with('error', 'No certificates selected for printing.');
        }

        [$sign1Title, $sign2Title] = $this->resolveSignatureTitlesFromRequest($request);
        [$sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name] = $this->resolveSignatureImagesFromRequest($request);

        $certificatesData = $this->buildCertificatesData($certificateType, $itemIds, $sign1Title, $sign2Title);
        $certificatesData = $this->injectSignatureImages($certificatesData, $sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name);

        return view('certificates.print-branded-portrait', [
            'certificates' => $certificatesData,
        ]);
    }

    /**
     * Mark selected certificates as printed in the database.
     */
    public function markAsPrinted(Request $request)
    {
        $itemIds = $request->input('training_ids');
        $certificateType = $request->input('certificate_type');

        if (!is_array($itemIds) || empty($itemIds)) {
            return response()->json(['message' => 'No items selected.'], 422);
        }

        $printedByUserId = Auth::id();
        $now = now();
        $printRecords = [];

        $isTrainingCert = in_array($certificateType, ['training_competence', 'training_attendance']);

        if ($isTrainingCert) {
            $trainings = Training::whereIn('id', $itemIds)->get(['id', 'user_id']);
            foreach ($trainings as $training) {
                $printRecords[] = [
                    'user_id' => $training->user_id,
                    'training_id' => $training->id,
                    'printed_by_user_id' => $printedByUserId,
                    'certificate_type' => $certificateType,
                    'printed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        } elseif ($certificateType === 'membership') {
            $payments = MembershipPayment::whereIn('id', $itemIds)->personal()->get(['user_id']);
            foreach ($payments as $payment) {
                $printRecords[] = [
                    'user_id' => $payment->user_id,
                    'training_id' => null,
                    'printed_by_user_id' => $printedByUserId,
                    'certificate_type' => $certificateType,
                    'printed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        } elseif (in_array($certificateType, ['volunteering', 'donation'])) {
            // Here, the itemIds are user_ids.
            foreach ($itemIds as $userId) {
                $printRecords[] = [
                    'user_id' => $userId,
                    'training_id' => null,
                    'printed_by_user_id' => $printedByUserId,
                    'certificate_type' => $certificateType,
                    'printed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        } elseif (in_array($certificateType, ['organisation_membership', 'organisation_donation'])) {
            // Here, the itemIds are organisation_ids.
            foreach ($itemIds as $orgId) {
                $printRecords[] = [
                    'user_id'            => null,
                    'organisation_id'    => $orgId,
                    'training_id'        => null,
                    'printed_by_user_id' => $printedByUserId,
                    'certificate_type'   => $certificateType,
                    'printed_at'         => $now,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
        } else {
            return response()->json(['message' => 'Invalid certificate type.'], 422);
        }

        if (!empty($printRecords)) {
            CertificatePrint::insert($printRecords);
        }

        $count = count($printRecords);
        $plural = $count === 1 ? 'record' : 'records';

        return response()->json(['message' => "Successfully marked {$count} {$plural} as printed."]);
    }

    public function showCertificatePrintsReport(Request $request)
    {
        $query = CertificatePrint::query();

        // Get current user's access level and scoped ID
        $user        = Auth::user();
        $accessLevel = $user->getAccessLevel(); // e.g., 'national', 'branch', 'division'
        $scopedId    = $user->getScopedId();    // branch_id or division_id

        $userBranchId   = null;
        $userDivisionId = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userDivisionId = $scopedId;
                $userBranchId   = $userDivision->branch_id;
            }
        }

        // Apply global access level filters to the main query.
        // For org prints (user_id is null), scope by organisation.branch_id instead.
        if ($accessLevel === 'branch' && $userBranchId) {
            $query->where(function ($q) use ($userBranchId) {
                $q->whereHas('user', fn ($u) => $u->where('branch_id', $userBranchId))
                  ->orWhereHas('organisation', fn ($o) => $o->where('branch_id', $userBranchId));
            });
        } elseif ($accessLevel === 'division' && $userDivisionId) {
            $query->where(function ($q) use ($userDivisionId, $userBranchId) {
                $q->whereHas('user', fn ($u) => $u->where('division_id', $userDivisionId))
                  ->orWhereHas('organisation', fn ($o) => $o->where('branch_id', $userBranchId));
            });
        }

        // --- Filters ---

        // User search
        if ($request->filled('user_id_search')) {
            $searchTerm        = $request->input('user_id_search');
            $likeSearchTerm    = '%' . $searchTerm . '%';
            $numericSearchTerm = str_replace('%', '', $searchTerm); // For exact ID match

            $query->whereHas('user', function ($q) use ($likeSearchTerm, $numericSearchTerm) {
                $q->where('first_name', 'like', $likeSearchTerm)
                    ->orWhere('last_name', 'like', $likeSearchTerm);

                if (is_numeric($numericSearchTerm)) {
                    $q->orWhere('id', $numericSearchTerm);
                }
            });
        }

        // Branch filter — include org records matching by organisation.branch_id
        if ($request->filled('branch_id')) {
            $branchId = $request->input('branch_id');
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('user', fn ($u) => $u->where('branch_id', $branchId))
                  ->orWhereHas('organisation', fn ($o) => $o->where('branch_id', $branchId));
            });
        }

        // Division filter
        if ($request->filled('division_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('division_id', $request->input('division_id'));
            });
        }

        // Red Cross Unit filter
        if ($request->filled('red_cross_unit_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('red_cross_unit_id', $request->input('red_cross_unit_id'));
            });
        }

        // Certificate type filter
        if ($request->filled('certificate_type')) {
            $query->where('certificate_type', $request->input('certificate_type'));
        }

        // Date range filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('printed_at', [
                $request->input('start_date'),
                $request->input('end_date') . ' 23:59:59',
            ]);
        }

        // Eager load relationships for displaying user and printer information
        $certificatePrints = $query->with('user.branch', 'user.division', 'user.redCrossUnit', 'printedBy', 'organisation.branch')
            ->latest('printed_at')
            ->paginate(20);

        // Data for dropdowns, respecting access levels
        if (!isset($branches) || !$branches instanceof \Illuminate\Support\Collection) {
            $branches = collect();
        }

        if ($accessLevel === 'national') {
            $branches = Branch::orderBy('name')->get();
        } elseif ($accessLevel === 'branch' && $userBranchId) {
            $branches = Branch::where('id', $userBranchId)->orderBy('name')->get();
        } elseif ($accessLevel === 'division' && $userDivisionId) {
            $userDivision = Division::find($userDivisionId);
            if ($userDivision) {
                $branches = Branch::where('id', $userDivision->branch_id)->orderBy('name')->get();
            }
        }

        // Populate divisions based on selected branch or user's access level
        $selectedBranchId = $request->input('branch_id') ?? ($userBranchId ?? null);
        if ($selectedBranchId) {
            if ($accessLevel === 'national' || ($accessLevel === 'branch' && (string) $selectedBranchId === (string) $userBranchId)) {
                $divisions = Division::where('branch_id', $selectedBranchId)->orderBy('name')->get();
            } elseif ($accessLevel === 'division' && $userDivisionId) {
                $divisions = Division::where('id', $userDivisionId)->orderBy('name')->get();
            }
        } else {
            $divisions = collect();
        }

        // Populate red cross units based on selected division or user's access level
        $selectedDivisionId = $request->input('division_id') ?? ($userDivisionId ?? null);
        if ($selectedDivisionId) {
            $redCrossUnits = RedCrossUnit::where('division_id', $selectedDivisionId)->orderBy('name')->get();
        } else {
            $redCrossUnits = collect();
        }

        return view('certificates.prints-report', compact(
            'certificatePrints',
            'branches',
            'divisions',
            'redCrossUnits',
            'accessLevel',
            'userBranchId',
            'userDivisionId'
        ));
    }

    public function bulkDeletePrints(Request $request)
    {
        $validated = $request->validate([
            'print_ids' => 'required|string',
        ]);

        $ids = json_decode($validated['print_ids'], true);

        if (!is_array($ids) || empty($ids)) {
            return redirect()
                ->route('certificates.prints-report')
                ->with('error', 'No valid certificate prints were selected for deletion.');
        }

        // Only numeric IDs
        $ids = array_filter($ids, fn ($id) => is_numeric($id));

        if (empty($ids)) {
            return redirect()
                ->route('certificates.prints-report')
                ->with('error', 'No valid certificate prints were selected for deletion.');
        }

        // Soft delete (model uses SoftDeletes)
        CertificatePrint::whereIn('id', $ids)->delete();

        return redirect()
            ->route('certificates.prints-report')
            ->with('status', count($ids) . ' certificate print record(s) deleted.');
    }

    public function organisationIndex(Request $request)
    {
        $user        = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId    = $user->getScopedId();

        $userBranchId = null;
        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        }

        $certificateTypes = [
            'organisation_membership' => 'Membership',
            'organisation_donation'   => 'Donation',
        ];

        $certificateType = $request->input('certificate_type', 'organisation_membership');
        if (!array_key_exists($certificateType, $certificateTypes)) {
            $certificateType = 'organisation_membership';
        }

        switch ($certificateType) {
            case 'organisation_membership':
                $query = Organisation::query()
                    ->whereHas('activeMembership')
                    ->with(['activeMembership.membershipFee', 'branch']);
                break;

            case 'organisation_donation':
                $query = Organisation::query()
                    ->whereHas('donations')
                    ->with(['branch'])
                    // NULL in_kind_donation (type never set) defaults to cash —
                    // matches User::countCashDonations().
                    ->withCount(['donations as cash_donations_count' => fn ($q) => $q->where(fn ($q2) => $q2->where('in_kind_donation', false)->orWhereNull('in_kind_donation'))])
                    ->withCount(['donations as in_kind_donations_count' => fn ($q) => $q->where('in_kind_donation', true)])
                    ->withSum(['donations as donations_sum_amount' => fn ($q) => $q->where(fn ($q2) => $q2->where('in_kind_donation', false)->orWhereNull('in_kind_donation'))], 'amount');
                break;

            default:
                $query = Organisation::query()
                    ->whereHas('activeMembership')
                    ->with(['activeMembership.membershipFee', 'branch']);
                break;
        }

        if ($accessLevel === 'branch' && $userBranchId) {
            $query->where('branch_id', $userBranchId);
        }

        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $query->orderBy('name', 'asc');

        $records = $query->paginate(24)->withQueryString();

        $branches = ($accessLevel === 'branch' && $userBranchId)
            ? Branch::where('id', $userBranchId)->orderBy('name')->get()
            : Branch::orderBy('name')->get();

        $signatureTitles = SignatureTitle::includeInList()->orderBy('name')->get();

        $signaturesDir   = public_path('images/signatures');
        $signatureImages = is_dir($signaturesDir)
            ? array_map('basename', glob($signaturesDir . '/*.png') ?: [])
            : [];

        $selectedSign1Id = session('selected_sign_1_id', '_line_only_');
        $selectedSign2Id = session('selected_sign_2_id', '_line_only_');

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return view('certificates.organisations', compact(
            'records',
            'branches',
            'accessLevel',
            'userBranchId',
            'signatureTitles',
            'certificateTypes',
            'certificateType',
            'selectedSign1Id',
            'selectedSign2Id',
            'signatureImages'
        ));
    }

    protected function buildBrandedOrganisationMembershipCertificateData(
        Organisation $organisation,
        ?string      $sign1Title = null,
        mixed        $sign2Title = null
    ): array {
        $organisation->loadMissing('branch', 'activeMembership.membershipFee');
        $payment = $organisation->activeMembership;

        $signaturesCount = ($sign2Title === false) ? 1 : 2;
        $defaultSign1    = $sign1Title ?? 'Secretary General';
        $defaultSign2    = ($signaturesCount === 2) ? ($sign2Title ?? 'Branch Chairman') : null;

        return [
            'orgName'             => 'Nigerian Red Cross Society',
            'recipientName'       => $organisation->name,
            'primaryCertifyText'  => 'This is to certify that',
            'certifyText'         => 'is a registered',
            'courseTitle'         => ($payment->membershipFee->name ?? 'Member') . ' Member',
            'membershipType'      => ($payment->membershipFee->name ?? 'Member') . ' Member',
            'dateLine'            => 'from ' . $payment->payment_date->format('F j, Y') . ' to ' . $payment->expiry_date->format('F j, Y'),
            'branchName'          => $organisation->branch->name ?? 'N/A',
            'defaultSign1'        => $defaultSign1,
            'defaultSign2'        => $defaultSign2,
            'signaturesCount'     => $signaturesCount,
            'footerLocation'      => $organisation->branch->name ?? 'NRCS HQ',
            'footerProducer'      => Auth::id(),
            'logoUrl'             => asset('images/NRCS_logo.jpg'),
            'certificateImageUrl' => asset('images/certificates/certificate_of_membership.png'),
            'payment'             => $payment,
            'organisation'        => $organisation,
            'user'                => null,
        ];
    }

    protected function buildBrandedOrganisationDonationCertificateData(
        Organisation $organisation,
        ?string      $sign1Title = null,
        mixed        $sign2Title = null
    ): array {
        $organisation->loadMissing('branch');
        $organisation->load(['donations' => fn ($q) => $q->orderBy('date_donation', 'desc')]);
        $allDonations = $organisation->donations;

        $totalCashAmount = $allDonations->where('in_kind_donation', false)->sum('amount');
        $totalCount      = $allDonations->count();
        $maxItems        = 10;

        $signaturesCount = ($sign2Title === false) ? 1 : 2;
        $defaultSign1    = $sign1Title ?? 'Secretary General';
        $defaultSign2    = ($signaturesCount === 2) ? ($sign2Title ?? 'Branch Chairman') : null;

        $items      = [];
        $itemNumber = 1;

        foreach ($allDonations->take($maxItems) as $donation) {
            $items[] = [
                'number'      => $itemNumber++,
                'date'        => $donation->date_donation->format('Y-m-d'),
                'description' => $donation->in_kind_donation
                    ? ($donation->donation_item ?: 'In-kind donation')
                    : 'Cash Donation',
                'amount'      => $donation->in_kind_donation
                    ? (int) $donation->amount
                    : ('₦' . number_format($donation->amount, 0)),
            ];
        }

        if ($totalCount > $maxItems) {
            $remainingDonations   = $allDonations->slice($maxItems);
            $remainingCash        = $remainingDonations->where('in_kind_donation', false)->sum('amount');
            $remainingInKindCount = $remainingDonations->where('in_kind_donation', true)->count();

            $descParts = [];
            if ($remainingCash > 0) {
                $descParts[] = 'other cash donations';
            }
            if ($remainingInKindCount > 0) {
                $plural      = $remainingInKindCount === 1 ? 'item' : 'items';
                $descParts[] = "and {$remainingInKindCount} other in-kind {$plural}";
            }

            if (!empty($descParts)) {
                $items[] = [
                    'isSummaryRow' => true,
                    'description'  => 'Total ' . implode(' ', $descParts),
                    'amount'       => $remainingCash > 0 ? '₦' . number_format($remainingCash, 0) : '',
                ];
            }
        }

        $totalRow    = [
            'label'  => 'Total Cash Donations',
            'amount' => '₦' . number_format($totalCashAmount, 0),
        ];
        $certifyText = $totalCount > 1
            ? 'in sincere appreciation for the following generous contributions'
            : 'in sincere appreciation for the following generous contribution';

        return [
            'orgName'             => 'Nigerian Red Cross Society',
            'recipientName'       => $organisation->name,
            'primaryCertifyText'  => 'This is to certify that',
            'certifyText'         => $certifyText,
            'courseTitle'         => '',
            'dateLine'            => 'as of ' . now()->format('F j, Y'),
            'itemHeaders'         => ['#', 'Date', 'Item/Description', 'Amount/Quantity'],
            'items'               => $items,
            'totalRow'            => $totalRow,
            'defaultSign1'        => $defaultSign1,
            'defaultSign2'        => $defaultSign2,
            'signaturesCount'     => $signaturesCount,
            'footerLocation'      => $organisation->branch->name ?? 'NRCS HQ',
            'footerProducer'      => Auth::id(),
            'logoUrl'             => asset('images/NRCS_logo.jpg'),
            'certificateImageUrl' => asset('images/certificates/certificate_of_appreciation.png'),
            'organisation'        => $organisation,
            'user'                => null,
        ];
    }

    protected function buildOrganisationCertificatesData(
        string  $type,
        array   $orgIds,
        ?string $sign1Title = null,
        mixed   $sign2Title = null
    ): array {
        $organisations = Organisation::with(['branch', 'activeMembership.membershipFee'])
            ->whereIn('id', $orgIds)
            ->get();

        $certificatesData = [];
        foreach ($organisations as $organisation) {
            if ($type === 'organisation_membership') {
                $certificatesData[] = $this->buildBrandedOrganisationMembershipCertificateData($organisation, $sign1Title, $sign2Title);
            } elseif ($type === 'organisation_donation') {
                $certificatesData[] = $this->buildBrandedOrganisationDonationCertificateData($organisation, $sign1Title, $sign2Title);
            }
        }

        return array_map(fn ($cert) => array_merge($cert, ['certificate_type' => $type]), $certificatesData);
    }

    public function organisationBulkPrintPlain(Request $request)
    {
        $this->rememberSignatureSelections($request);

        $orgIds          = $request->input('training_ids');
        $certificateType = $request->input('certificate_type');

        if (!is_array($orgIds) || empty($orgIds)) {
            return redirect()->back()->with('error', 'No certificates selected for printing.');
        }

        [$sign1Title, $sign2Title]                               = $this->resolveSignatureTitlesFromRequest($request);
        [$sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name] = $this->resolveSignatureImagesFromRequest($request);

        $incomingLayout   = json_decode($request->input('layout', '{}'), true) ?: [];
        $layout           = $this->buildPlainLayout($incomingLayout);
        $certificatesData = $this->buildOrganisationCertificatesData($certificateType, $orgIds, $sign1Title, $sign2Title);
        $certificatesData = $this->injectSignatureImages($certificatesData, $sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name);

        return view('certificates.print-plain', [
            'layout'       => $layout,
            'certificates' => $certificatesData,
        ]);
    }

    public function organisationBulkPrintBranded(Request $request)
    {
        $this->rememberSignatureSelections($request);

        $orgIds          = $request->input('training_ids');
        $certificateType = $request->input('certificate_type');

        if (!is_array($orgIds) || empty($orgIds)) {
            return redirect()->back()->with('error', 'No certificates selected for printing.');
        }

        [$sign1Title, $sign2Title]                               = $this->resolveSignatureTitlesFromRequest($request);
        [$sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name] = $this->resolveSignatureImagesFromRequest($request);

        $certificatesData = $this->buildOrganisationCertificatesData($certificateType, $orgIds, $sign1Title, $sign2Title);
        $certificatesData = $this->injectSignatureImages($certificatesData, $sign1ImageUrl, $sign1Name, $sign2ImageUrl, $sign2Name);

        $view = $certificateType === 'organisation_donation'
            ? 'certificates.print-branded-portrait'
            : 'certificates.print-branded';

        return view($view, [
            'certificates' => $certificatesData,
        ]);
    }

    public function verify(Request $request)
    {
        $userToken  = $request->query('u');
        $type       = $request->query('type');
        $trainingId = $request->query('training_id');

        $allowedTypes = [
            'training_competence',
            'training_attendance',
            'membership',
            'donation',
            'volunteering',
        ];

        if (!$userToken || !in_array($type, $allowedTypes, true)) {
            return view('certificates.verify', [
                'valid'       => false,
                'reason'      => 'invalid_parameters',
                'user'        => null,
                'certificate' => null,
            ]);
        }

        $user = User::with(['branch', 'division', 'redCrossUnit'])
            ->where('id_check_token', $userToken)
            ->first();

        if (! $user) {
            return view('certificates.verify', [
                'valid'       => false,
                'reason'      => 'user_not_found',
                'user'        => null,
                'certificate' => null,
            ]);
        }

        $training = null;

        if (str_starts_with($type, 'training') && $trainingId) {
            $training = Training::find($trainingId);
        }

        return view('certificates.verify', [
            'valid'       => true,
            'reason'      => null,
            'user'        => $user,
            'certificate' => [
                'type'       => $type,
                'training'   => $training,
                'trainingId' => $trainingId,
            ],
        ]);
    }


}
