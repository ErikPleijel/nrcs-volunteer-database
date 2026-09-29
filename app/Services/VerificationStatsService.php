<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous volunteer statistics for the public certificate verification
 * page (certificates.verify): total volunteers, men/women, average age,
 * first-aid trained (and how many need a refresher), the Volunteer /
 * Volunteer & Member / Member split (User::contributor_type), and
 * volunteering hours, for a Red Cross Unit or a branch.
 *
 * The split has a fourth "unclassified" bucket: a branch's active/dormant
 * users include people with no RCU and no current fee (e.g. a lapsed
 * member). A unit's own users always have an RCU, so only V and V+M occur.
 *
 * Population: active + dormant users — the "volunteers" of
 * RedCrossUnitsReportController's demographics tab. The page is public, so a
 * group smaller than MIN_GROUP_SIZE is returned as suppressed: for a tiny
 * group the gender split and average age identify individuals.
 *
 * Hours are approved, non-deleted activities logged against the unit
 * (assignable) or the branch (activities.branch_id) — not the counted
 * people's own hours — so a leaver's past hours stay with the unit.
 */
class VerificationStatsService
{
    public const MIN_GROUP_SIZE = 10;

    private const CACHE_SECONDS = 3600;

    /** First aid older than this needs a refresher — the default of the users/index refresher wizard. */
    public const FIRST_AID_STALE_MONTHS = 36;

    public function forUnit(RedCrossUnit $unit): array
    {
        return $this->stats('unit', $unit->id, $unit->name, 'red_cross_unit_id');
    }

    /**
     * Branch-level stats; null only when there is no branch (a user or a
     * unit's division without one), in which case there is nothing to show.
     */
    public function forBranch(?Branch $branch): ?array
    {
        if (! $branch) {
            return null;
        }

        return $this->stats('branch', $branch->id, $branch->name, 'branch_id');
    }

    private function stats(string $scope, int $id, string $name, string $column): array
    {
        // v3: rows gained hours and first_aid_stale; old cached rows lack them.
        $row = Cache::remember("verify-stats:v3:{$scope}:{$id}", self::CACHE_SECONDS, function () use ($scope, $column, $id) {
            $year = now()->year;
            // Same comparison as UserFilterService's first_aid_refresher filter.
            $staleBefore = now()->subMonths(self::FIRST_AID_STALE_MONTHS)->toDateString();

            // has_current_fee comes from User::scopeWithContributorFacts(), so
            // the V / V+M / M split uses exactly the contributor_type rule.
            $users = User::query()
                ->select(['users.red_cross_unit_id', 'users.gender', 'users.birth_year', 'users.last_first_aid_at'])
                ->withContributorFacts()
                ->whereIn('users.lifecycle_status', ['active', 'dormant'])
                ->where('users.is_super_admin', false)
                ->where("users.{$column}", $id);

            // Calendar-year hours. Always exactly one row, so cross-joining it
            // leaves the counts over u intact and keeps this a single query.
            $hours = DB::table('activities')
                ->where('is_deleted', false)
                ->where('approval_status', 'approved') // only approved records are real
                ->when(
                    $scope === 'unit',
                    fn ($q) => $q->where('assignable_type', RedCrossUnit::class)->where('assignable_id', $id),
                    // Covering index (2026_09_29 migration). Forced because for a
                    // branch holding a large share of activities (FCT, ~38%) MySQL
                    // misestimates and picks activities_is_deleted_date_index.
                    fn ($q) => $q->forceIndex('activities_branch_hours_index')->where('branch_id', $id),
                )
                ->selectRaw('COALESCE(SUM(hours), 0) AS hours_total,
                             COALESCE(SUM(CASE WHEN YEAR(date) = ? THEN hours END), 0) AS hours_this_year,
                             COALESCE(SUM(CASE WHEN YEAR(date) = ? THEN hours END), 0) AS hours_last_year,
                             COALESCE(SUM(CASE WHEN YEAR(date) = ? THEN hours END), 0) AS hours_two_years_ago',
                    [$year, $year - 1, $year - 2]);

            // The year is cached with the row so the labels match the figures.
            return ['year' => $year] + (array) DB::query()
                ->fromSub($users, 'u')
                ->crossJoinSub($hours, 'h')
                // Implausible birth years (over 100, or under 5) are left
                // out of the average only.
                ->selectRaw('COUNT(*) AS total,
                             SUM(gender = "male") AS male,
                             SUM(gender = "female") AS female,
                             SUM(gender IS NULL) AS gender_unknown,
                             AVG(CASE WHEN birth_year BETWEEN ? AND ? THEN ? - birth_year END) AS avg_age,
                             SUM(birth_year IS NULL) AS age_unknown,
                             SUM(last_first_aid_at IS NOT NULL) AS first_aid,
                             SUM(last_first_aid_at IS NOT NULL AND last_first_aid_at < ?) AS first_aid_stale,
                             SUM(red_cross_unit_id IS NOT NULL AND NOT has_current_fee) AS volunteer_only,
                             SUM(red_cross_unit_id IS NOT NULL AND has_current_fee) AS volunteer_member,
                             SUM(red_cross_unit_id IS NULL AND has_current_fee) AS member_only,
                             SUM(red_cross_unit_id IS NULL AND NOT has_current_fee) AS unclassified,
                             MAX(h.hours_total) AS hours_total,
                             MAX(h.hours_this_year) AS hours_this_year,
                             MAX(h.hours_last_year) AS hours_last_year,
                             MAX(h.hours_two_years_ago) AS hours_two_years_ago',
                    [$year - 100, $year - 5, $year, $staleBefore])
                ->first();
        });

        $base = ['scope' => $scope, 'scope_label' => $name];

        $total = (int) $row['total'];
        if ($total < self::MIN_GROUP_SIZE) {
            return $base + ['suppressed' => true];
        }

        return $base + [
            'suppressed'     => false,
            'total'          => $total,
            'male'           => (int) $row['male'],
            'female'         => (int) $row['female'],
            'gender_unknown' => (int) $row['gender_unknown'],
            'avg_age'        => $row['avg_age'] !== null ? round((float) $row['avg_age'], 1) : null,
            'age_unknown'    => (int) $row['age_unknown'],
            'first_aid'      => (int) $row['first_aid'],
            'first_aid_stale' => (int) $row['first_aid_stale'],
            // contributor_type split; sums to total. Only returned alongside
            // the other figures — a suppressed group exposes none of it.
            'volunteer_only'   => (int) $row['volunteer_only'],
            'volunteer_member' => (int) $row['volunteer_member'],
            'member_only'      => (int) $row['member_only'],
            'unclassified'     => (int) $row['unclassified'],
            // Calendar years by activity date: year, year - 1, year - 2.
            'year'                => (int) $row['year'],
            'hours_total'         => (int) $row['hours_total'],
            'hours_this_year'     => (int) $row['hours_this_year'],
            'hours_last_year'     => (int) $row['hours_last_year'],
            'hours_two_years_ago' => (int) $row['hours_two_years_ago'],
        ];
    }
}
