<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\Reports\MembershipStatsService;
use App\Services\Reports\RedCrossUnitStatsService;
use App\Services\Reports\TrainingStatsService;
use App\Services\Reports\DonationStatsService;
use App\Services\Reports\RegistrationStatsService; // Import the RegistrationStatsService
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{
    protected $membershipStatsService;
    protected $redCrossUnitStatsService;
    protected $trainingStatsService;
    protected $donationStatsService;
    protected $registrationStatsService; // Declare the new service property

    public function __construct(
        MembershipStatsService $membershipStatsService,
        RedCrossUnitStatsService $redCrossUnitStatsService,
        TrainingStatsService $trainingStatsService,
        DonationStatsService $donationStatsService,
        RegistrationStatsService $registrationStatsService // Inject RegistrationStatsService
    ) {
        $this->membershipStatsService = $membershipStatsService;
        $this->redCrossUnitStatsService = $redCrossUnitStatsService;
        $this->trainingStatsService = $trainingStatsService;
        $this->donationStatsService = $donationStatsService;
        $this->registrationStatsService = $registrationStatsService; // Assign the injected service
    }

    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        $extended = $request->boolean('extended');

        // Check if there's a branch_id in the request
        if ($request->has('branch_id')) {
            // Convert empty string to null (National view)
            $branchId = $request->input('branch_id') !== '' ? $request->input('branch_id') : null;

            // Store in session
            session(['dashboard_branch_id' => $branchId]);
        }
        // If no branch_id in the request and URL is exactly '/reports' (no other parameters),
        // use the user's scoped branch ID
        elseif ($request->path() === 'reports' && count($request->query()) === 0) {
            $branchId = $user->getScopedBranchId();

            // Clear the session to ensure fresh start
            session()->forget('dashboard_branch_id');
        }
        // Otherwise use session value if available
        elseif (session()->has('dashboard_branch_id')) {
            $branchId = session('dashboard_branch_id');
        }
        // Final fallback - use the user's scoped branch
        else {
            $branchId = $user->getScopedBranchId();
        }

        // Get all active branches for the dropdown
        $branches = Branch::active()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $cached = Cache::remember(
            $this->dashboardCacheKey($user->id, $branchId, $extended),
            now()->addHour(),
            function () use ($user, $branchId, $extended) {
                $dashboardData = $this->buildDashboardData($user, $branchId, $extended);

                return [
                    'data' => $dashboardData,
                    'generated_at' => now(),
                ];
            }
        );

        $dashboardData = $cached['data'];
        $dashboardGeneratedAt = $cached['generated_at'];

        return view('dashboard', compact('dashboardData', 'branches', 'extended', 'dashboardGeneratedAt'));
    }

    /**
     * Cache key for a given viewer's dashboard: scoped by branch, extended-mode, and the
     * viewing user (selfSubmitted* figures are per-user, so the key must be too — otherwise
     * one user's pending-approval counts would leak into another user's cached view).
     * Includes a generation stamp so stats:snapshot can invalidate every cached dashboard
     * in O(1) (via Cache::increment) without needing tag support, which the 'file'/'database'
     * cache drivers used in this app don't have.
     */
    private function dashboardCacheKey(int $userId, ?int $branchId, bool $extended): string
    {
        // Seed the generation counter on first-ever use (Cache::add is a no-op if it already
        // exists). Using Cache::get(..., $default) with a fallback here would be a trap:
        // the first Cache::increment() takes an absent key 0 -> 1, landing on the same value
        // the fallback would already report, so the very first nightly invalidation would be
        // silently swallowed. Seeding explicitly guarantees every increment is visible.
        Cache::add('dashboard:cache_gen', 1);
        $generation = Cache::get('dashboard:cache_gen');

        return sprintf(
            'dashboard:v%s:u%d:b%s:e%s',
            $generation,
            $userId,
            $branchId ?? 'nat',
            $extended ? '1' : '0'
        );
    }

    /**
     * Force-refresh this viewer's cached dashboard on their next load, then send them back.
     * branch_id/extended are read from the query string (matching whatever the dashboard
     * view was showing when "Refresh now" was clicked) rather than re-deriving from session,
     * so this doesn't need to duplicate index()'s branch-resolution logic.
     */
    public function refreshCache(Request $request)
    {
        $user = Auth::user();
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $extended = $request->boolean('extended');

        Cache::forget($this->dashboardCacheKey($user->id, $branchId, $extended));

        return redirect()->route('reports.dashboard', array_filter([
            'branch_id' => $branchId,
            'extended' => $extended ? 1 : null,
        ]));
    }

    /**
     * All the dashboard's card data. Pure computation — no caching concerns here, so query
     * logic for any given card can keep being edited without touching the caching wrapper.
     */
    private function buildDashboardData($user, ?int $branchId, bool $extended): array
    {
        // --- Always: Members card ---
        $current = $this->membershipStatsService->getTotalMembersCount($branchId);

        $genderCounts = \App\Models\User::members()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw("
                SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as men,
                SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as women,
                SUM(CASE WHEN gender IS NULL OR gender NOT IN ('male','female') THEN 1 ELSE 0 END) as unknown
            ")
            ->first();

        $genderMen     = (int) ($genderCounts->men     ?? 0);
        $genderWomen   = (int) ($genderCounts->women   ?? 0);
        $genderUnknown = (int) ($genderCounts->unknown ?? 0);

        $snapMonth = $this->snapshotTotalsAt(now()->subMonth(), $branchId);
        $snapYear  = $this->snapshotTotalsAt(now()->subYear(), $branchId);

        $oneMonthAgo     = $snapMonth?->members_total !== null ? (int) $snapMonth->members_total : null;
        $twelveMonthsAgo = $snapYear?->members_total  !== null ? (int) $snapYear->members_total  : null;

        $changeMonth = ($oneMonthAgo !== null && $oneMonthAgo > 0)
            ? round((($current - $oneMonthAgo) / $oneMonthAgo) * 100, 1)
            : null;

        $changeYear = ($twelveMonthsAgo !== null && $twelveMonthsAgo > 0)
            ? round((($current - $twelveMonthsAgo) / $twelveMonthsAgo) * 100, 1)
            : null;

        // --- Always: Volunteers card ---
        $volunteerBase = fn() => \App\Models\User::volunteers()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        $volunteerGenderCounts = $volunteerBase()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as men,
                SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as women,
                SUM(CASE WHEN gender IS NULL OR gender NOT IN ('male','female') THEN 1 ELSE 0 END) as unknown
            ")
            ->first();

        $volunteersCount = (int) ($volunteerGenderCounts->total ?? 0);

        // Reuse the same snapshot rows fetched for members above
        $volunteersOneMonthAgo     = $snapMonth?->volunteers_total !== null ? (int) $snapMonth->volunteers_total : null;
        $volunteersTwelveMonthsAgo = $snapYear?->volunteers_total  !== null ? (int) $snapYear->volunteers_total  : null;

        $volunteersChangeMonth = ($volunteersOneMonthAgo !== null && $volunteersOneMonthAgo > 0)
            ? round((($volunteersCount - $volunteersOneMonthAgo) / $volunteersOneMonthAgo) * 100, 1)
            : null;

        $volunteersChangeYear = ($volunteersTwelveMonthsAgo !== null && $volunteersTwelveMonthsAgo > 0)
            ? round((($volunteersCount - $volunteersTwelveMonthsAgo) / $volunteersTwelveMonthsAgo) * 100, 1)
            : null;

        // --- Extended: remaining 6 cards ---
        if ($extended) {
            // Renewal Rate card
            $expiredLast12      = $this->membershipStatsService->getExpiredMembersLast12MonthsNotRenewed($branchId);
            $expiredTotal       = $this->membershipStatsService->getExpiredMembersCount($branchId);
            $renewalRate        = $this->membershipStatsService->getRenewalRateLast12Months($branchId);
            $expiredLast90Days  = $this->membershipStatsService->getMembersExpiredLast90Days($branchId);
            $expiringNext30Days = $this->membershipStatsService->getMembersExpiringNext30Days($branchId);

            // Membership Revenue card
            $now            = now();
            $oneYearAgo     = $now->copy()->subYear();
            $twoYearsAgo    = $now->copy()->subYears(2);

            $revenueLast12Months     = $this->membershipStatsService->getMembershipRevenue($oneYearAgo, $now, $branchId);
            $revenuePrevious12Months = $this->membershipStatsService->getMembershipRevenue($twoYearsAgo, $oneYearAgo, $branchId);
            $revenueChangeYear       = $revenuePrevious12Months > 0
                ? round((($revenueLast12Months - $revenuePrevious12Months) / $revenuePrevious12Months) * 100, 1)
                : null;

            // Training & First Aid card
            $totalTrainingsLast12Months       = $this->trainingStatsService->getTotalTrainingsLast12Months($branchId);
            $totalTrainings12to24MonthsAgo    = $this->trainingStatsService->getTotalTrainings12to24MonthsAgo($branchId);
            $firstAidTrainingsLast12Months    = $this->trainingStatsService->getFirstAidTrainingsLast12Months($branchId);
            $firstAidTrainings12to24MonthsAgo = $this->trainingStatsService->getFirstAidTrainings12to24MonthsAgo($branchId);

            // Donations card
            $cashLast12       = $this->donationStatsService->getCashDonationAmountLast12Months($branchId);
            $cashPrev12       = $this->donationStatsService->getCashDonationAmount12to24MonthsAgo($branchId);
            $inKindCountLast12 = $this->donationStatsService->getInKindDonationCountLast12Months($branchId);
            $inKindCountPrev12 = $this->donationStatsService->getInKindDonationCount12to24MonthsAgo($branchId);

            // Registrations card
            $registrationsLast12Months = $this->registrationStatsService->getRegistrationsLast12Months($branchId);
            $registrationsPrev12Months = $this->registrationStatsService->getRegistrationsPrev12Months($branchId);

            // Red Cross Units card
            $activeUnitsCount            = $this->redCrossUnitStatsService->getActiveUnitsCount($branchId);
            $averageMembersPerActiveUnit  = $this->redCrossUnitStatsService->getAverageMembersPerActiveUnit($branchId);
            $unitsWithoutLeadershipCount = $this->redCrossUnitStatsService->getUnitsWithoutLeadershipCount($branchId);
        } else {
            $expiredLast12            = null;
            $expiredTotal             = null;
            $renewalRate              = null;
            $expiredLast90Days        = null;
            $expiringNext30Days       = null;
            $revenueLast12Months      = null;
            $revenuePrevious12Months  = null;
            $revenueChangeYear        = null;
            $totalTrainingsLast12Months       = null;
            $totalTrainings12to24MonthsAgo    = null;
            $firstAidTrainingsLast12Months    = null;
            $firstAidTrainings12to24MonthsAgo = null;
            $cashLast12               = null;
            $cashPrev12               = null;
            $inKindCountLast12        = null;
            $inKindCountPrev12        = null;
            $registrationsLast12Months = null;
            $registrationsPrev12Months = null;
            $activeUnitsCount            = null;
            $averageMembersPerActiveUnit  = null;
            $unitsWithoutLeadershipCount = null;
        }

        // --- Always: 7-day activity counts ---
        $sevenDaysAgo = now()->subDays(7);
        $messagesSentLast7 = DB::table('messaging_recipients')->where('status', 'sent')->where('sent_at', '>=', $sevenDaysAgo)->count();
        $idCardsPrintedLast7 = DB::table('id_card_prints')
            ->where('status', 'printed')
            ->where('printed_at', '>=', $sevenDaysAgo)
            ->count();
        $certificatesPrintedLast7 = DB::table('certificates_print')->whereNull('deleted_at')->where('printed_at', '>=', $sevenDaysAgo)->count();
        $loggedInLast24h = User::query()
            ->where('last_login_at', '>=', now()->subHours(3))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        // Lifecycle counts filtered by branch (always shown)
        $lifecycleAwaitingEngagement = User::query()->awaitingEngagement()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        $pendingVolunteers = User::query()->awaitingEngagement()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('can_contribute_volunteering', true)
            ->count();
        $pendingMembers = User::query()->awaitingEngagement()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('can_contribute_member', true)
            ->count();
        $lifecycleActive = User::query()->active()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        $activeVolunteers = User::query()->volunteers()->active()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        $dormantVolunteers = User::query()->volunteers()->dormant()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        $activeMembers = User::query()->members()->active()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        $lifecycleDormant = User::query()->dormant()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        $lifecycleArchived = User::query()->archived()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $unassignedGhostCount = User::unassignedGhost()
            ->whereIn('lifecycle_status', User::OPERATIONAL_STATUSES)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $unverifiedRegistrationsCount = User::whereNotNull('email')
            ->whereNull('email_verified_at')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $dbMigrationDate = config('housekeeping.db_migration_date');
        $hangingRegistrationCount = null;
        $hangingRegistrationTotalCount = null;
        if ($dbMigrationDate) {
            $counts = User::adminRegistered()
                ->where('lifecycle_status', 'pending_engagement')
                ->where('is_super_admin', false)
                ->whereNull('organisation_id')
                ->notInactive()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as filtered', [$dbMigrationDate])
                ->first();

            $hangingRegistrationCount = (int) $counts->filtered;
            $hangingRegistrationTotalCount = (int) $counts->total;
        }

        $selfSubmittedPayments = \App\Models\MembershipPayment::pendingApproval()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('submitted_by_user_id', $user->id)->count();
        $selfSubmittedDonations = \App\Models\Donation::pendingApproval()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('entered_by_user_id', $user->id)->count();
        $selfSubmittedTrainings = \App\Models\Training::pendingApproval()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('submitted_by_user_id', $user->id)->count();
        $selfSubmittedActivities = \App\Models\Activity::pendingApproval()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('submitted_by_user_id', $user->id)->count();

        // Campaign approval is national-only (messaging_campaigns has no branch_id;
        // no branch-level role holds campaign_request_approve), so no branch filter here.
        $selfSubmittedCampaigns = \App\Models\MessagingCampaign::where('status', 'proposed')
            ->where('submitted_by', $user->id)->count();

        $dashboardData = [
            'numberOfMembers'                          => $current,
            'genderMen'                                => $genderMen,
            'genderWomen'                              => $genderWomen,
            'genderUnknown'                            => $genderUnknown,
            'changeMonth'                              => $changeMonth,
            'changeYear'                               => $changeYear,
            'expiredLast12Months'                      => $expiredLast12,
            'expiredTotal'                             => $expiredTotal,
            'expiredLast90Days'                        => $expiredLast90Days,
            'expiringNext30Days'                       => $expiringNext30Days,
            'branchId'                                 => $branchId,
            'revenueLast12Months'                      => $revenueLast12Months,
            'revenuePrevious12Months'                  => $revenuePrevious12Months,
            'revenueChangeYear'                        => $revenueChangeYear,
            'renewalRate'                              => $renewalRate,
            'volunteersCount'        => $volunteersCount,
            'volunteersChangeMonth'  => $volunteersChangeMonth,
            'volunteersChangeYear'   => $volunteersChangeYear,
            'volunteerGenderMen'     => (int) ($volunteerGenderCounts->men ?? 0),
            'volunteerGenderWomen'   => (int) ($volunteerGenderCounts->women ?? 0),
            'volunteerGenderUnknown' => (int) ($volunteerGenderCounts->unknown ?? 0),
            'totalTrainingsLast12Months'               => $totalTrainingsLast12Months,
            'totalTrainings12to24MonthsAgo'            => $totalTrainings12to24MonthsAgo,
            'firstAidTrainingsLast12Months'            => $firstAidTrainingsLast12Months,
            'firstAidTrainings12to24MonthsAgo'         => $firstAidTrainings12to24MonthsAgo,
            'cashLast12'                               => $cashLast12,
            'cashPrev12'                               => $cashPrev12,
            'inKindCountLast12'                        => $inKindCountLast12,
            'inKindCountPrev12'                        => $inKindCountPrev12,
            'registrationsLast12Months'                => $registrationsLast12Months,
            'registrationsPrev12Months'                => $registrationsPrev12Months,

            'activeUnitsCount'                         => $activeUnitsCount,
            'averageMembersPerActiveUnit'              => $averageMembersPerActiveUnit,

            'unitsWithoutLeadershipCount'              => $unitsWithoutLeadershipCount,

            'lifecycleAwaitingEngagement'              => $lifecycleAwaitingEngagement,
            'pendingVolunteers'                        => $pendingVolunteers,
            'pendingMembers'                           => $pendingMembers,
            'lifecycleActive'                          => $lifecycleActive,
            'activeVolunteers'                         => $activeVolunteers,
            'dormantVolunteers'                        => $dormantVolunteers,
            'activeMembers'                            => $activeMembers,
            'lifecycleDormant'                         => $lifecycleDormant,
            'lifecycleArchived'                        => $lifecycleArchived,
            'unassignedGhostCount'                     => $unassignedGhostCount,
            'unverifiedRegistrationsCount'             => $unverifiedRegistrationsCount,
            'hangingRegistrationCount'                 => $hangingRegistrationCount,
            'hangingRegistrationTotalCount'             => $hangingRegistrationTotalCount,
            'hangingRegistrationConfigured'             => (bool) $dbMigrationDate,
            'dbMigrationDate'                           => $dbMigrationDate,

            'messagesSentLast7'       => $messagesSentLast7,
            'idCardsPrintedLast7'     => $idCardsPrintedLast7,
            'certificatesPrintedLast7' => $certificatesPrintedLast7,
            'loggedInLast24h'         => $loggedInLast24h,

            // Pending approvals — always national, always shown
            'pendingPayments'    => \App\Models\MembershipPayment::pendingApproval()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
            'pendingDonations'   => \App\Models\Donation::pendingApproval()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
            'pendingTrainings'   => \App\Models\Training::pendingApproval()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
            'pendingActivities'  => \App\Models\Activity::pendingApproval()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
            'pendingCampaigns'   => \App\Models\MessagingCampaign::where('status', 'proposed')->count(),
            'selfSubmittedPayments'   => $selfSubmittedPayments,
            'selfSubmittedDonations'  => $selfSubmittedDonations,
            'selfSubmittedTrainings'  => $selfSubmittedTrainings,
            'selfSubmittedActivities' => $selfSubmittedActivities,
            'selfSubmittedCampaigns'  => $selfSubmittedCampaigns,
        ];

        return $dashboardData;
    }

    /**
     * Sum snapshot totals for the nearest snapshot date on or before $target.
     * Returns null if no snapshot exists at or before that date.
     */
    private function snapshotTotalsAt(\Carbon\Carbon $target, ?int $branchId): ?object
    {
        $date = \App\Models\StatsSnapshot::where('snapshot_date', '<=', $target->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->max('snapshot_date');

        if (! $date) {
            return null;
        }

        return \App\Models\StatsSnapshot::where('snapshot_date', $date)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('SUM(members_total) as members_total, SUM(volunteers_total) as volunteers_total')
            ->first();
    }

    public function getFilterOptions()
    {
        $branches = Branch::active()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'branches' => $branches,
            'divisions' => [],
            'timeRanges' => [
                'last_7_days' => 'Last 7 Days',
                'last_30_days' => 'Last 30 Days',
                'last_90_days' => 'Last 90 Days',
                'last_year' => 'Last Year',
                'year_to_date' => 'Year to Date',
                'all_time' => 'All Time',
            ],
        ]);
    }
}
