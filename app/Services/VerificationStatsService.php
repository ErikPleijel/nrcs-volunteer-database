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
 * first-aid trained and the Volunteer / Volunteer & Member / Member split
 * (User::contributor_type), for a Red Cross Unit or a branch.
 *
 * The split has a fourth "unclassified" bucket: a branch's active/dormant
 * users include people with no RCU and no current fee (e.g. a lapsed
 * member). A unit's own users always have an RCU, so only V and V+M occur.
 *
 * Population: active + dormant users — the "volunteers" of
 * RedCrossUnitsReportController's demographics tab. The page is public, so a
 * group smaller than MIN_GROUP_SIZE is returned as suppressed: for a tiny
 * group the gender split and average age identify individuals.
 */
class VerificationStatsService
{
    public const MIN_GROUP_SIZE = 10;

    private const CACHE_SECONDS = 3600;

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
        // v2: rows gained the contributor split; old cached rows lack it.
        $row = Cache::remember("verify-stats:v2:{$scope}:{$id}", self::CACHE_SECONDS, function () use ($column, $id) {
            $year = now()->year;

            // has_current_fee comes from User::scopeWithContributorFacts(), so
            // the V / V+M / M split uses exactly the contributor_type rule.
            $users = User::query()
                ->select(['users.red_cross_unit_id', 'users.gender', 'users.birth_year', 'users.last_first_aid_at'])
                ->withContributorFacts()
                ->whereIn('users.lifecycle_status', ['active', 'dormant'])
                ->where('users.is_super_admin', false)
                ->where("users.{$column}", $id);

            return (array) DB::query()
                ->fromSub($users, 'u')
                // Implausible birth years (over 100, or under 5) are left
                // out of the average only.
                ->selectRaw('COUNT(*) AS total,
                             SUM(gender = "male") AS male,
                             SUM(gender = "female") AS female,
                             SUM(gender IS NULL) AS gender_unknown,
                             AVG(CASE WHEN birth_year BETWEEN ? AND ? THEN ? - birth_year END) AS avg_age,
                             SUM(birth_year IS NULL) AS age_unknown,
                             SUM(last_first_aid_at IS NOT NULL) AS first_aid,
                             SUM(red_cross_unit_id IS NOT NULL AND NOT has_current_fee) AS volunteer_only,
                             SUM(red_cross_unit_id IS NOT NULL AND has_current_fee) AS volunteer_member,
                             SUM(red_cross_unit_id IS NULL AND has_current_fee) AS member_only,
                             SUM(red_cross_unit_id IS NULL AND NOT has_current_fee) AS unclassified',
                    [$year - 100, $year - 5, $year])
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
            // contributor_type split; sums to total. Only returned alongside
            // the other figures — a suppressed group exposes none of it.
            'volunteer_only'   => (int) $row['volunteer_only'],
            'volunteer_member' => (int) $row['volunteer_member'],
            'member_only'      => (int) $row['member_only'],
            'unclassified'     => (int) $row['unclassified'],
        ];
    }
}
