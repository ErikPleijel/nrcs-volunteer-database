<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\RedCrossUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous volunteer statistics for the public certificate verification
 * page (certificates.verify): total volunteers, men/women, average age and
 * first-aid trained, for a Red Cross Unit or a branch.
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
        $row = Cache::remember("verify-stats:{$scope}:{$id}", self::CACHE_SECONDS, function () use ($column, $id) {
            $year = now()->year;

            return (array) DB::table('users')
                ->whereIn('lifecycle_status', ['active', 'dormant'])
                ->where('is_super_admin', false)
                ->where($column, $id)
                // Implausible birth years (over 100, or under 5) are left
                // out of the average only.
                ->selectRaw('COUNT(*) AS total,
                             SUM(gender = "male") AS male,
                             SUM(gender = "female") AS female,
                             SUM(gender IS NULL) AS gender_unknown,
                             AVG(CASE WHEN birth_year BETWEEN ? AND ? THEN ? - birth_year END) AS avg_age,
                             SUM(birth_year IS NULL) AS age_unknown,
                             SUM(last_first_aid_at IS NOT NULL) AS first_aid',
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
        ];
    }
}
