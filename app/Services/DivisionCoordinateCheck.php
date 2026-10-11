<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds divisions whose coordinates look wrong, for divisions:check-coordinates:
 *   - missing:   no latitude or longitude;
 *   - outside:   not inside Nigeria's bounding box;
 *   - duplicate: exactly the same point as another division;
 *   - far:       more than $maxKm from the middle (median) of the other
 *                divisions in the same branch.
 * A finding is only a suspicion — large states (e.g. Borno, Niger) have
 * genuine LGAs far from their middle.
 */
class DivisionCoordinateCheck
{
    /** Nigeria, with a small margin. */
    private const LAT_MIN = 4.0;

    private const LAT_MAX = 14.0;

    private const LNG_MIN = 2.5;

    private const LNG_MAX = 14.8;

    /** Fewer divisions with coordinates than this in a branch: no "far" check. */
    private const MIN_NEIGHBOURS = 3;

    /**
     * @return Collection<int, array{id: int, name: string, branch: string, latitude: ?float, longitude: ?float, problems: list<string>}>
     *                                                                                                                                    sorted by branch, then division
     */
    public function run(float $maxKm = 150.0): Collection
    {
        $divisions = DB::table('divisions')
            ->leftJoin('branches', 'branches.id', '=', 'divisions.branch_id')
            ->orderBy('branches.name')
            ->orderBy('divisions.name')
            ->get([
                'divisions.id', 'divisions.name', 'divisions.branch_id', 'branches.name as branch',
                'divisions.latitude', 'divisions.longitude',
            ])
            ->map(fn ($d) => (object) [
                'id' => (int) $d->id,
                'name' => $d->name,
                'branch_id' => $d->branch_id,
                'branch' => $d->branch ?? 'No branch',
                'latitude' => $d->latitude === null ? null : (float) $d->latitude,
                'longitude' => $d->longitude === null ? null : (float) $d->longitude,
            ]);

        $located = $divisions->filter(fn ($d) => $d->latitude !== null && $d->longitude !== null);

        // Same point => the divisions sharing it.
        $byPoint = $located->groupBy(fn ($d) => sprintf('%.6f,%.6f', $d->latitude, $d->longitude));

        $byBranch = $located->groupBy('branch_id');

        return $divisions
            ->map(function ($d) use ($byPoint, $byBranch, $maxKm) {
                $problems = [];

                if ($d->latitude === null || $d->longitude === null) {
                    $problems[] = 'No coordinates';
                } else {
                    if ($d->latitude < self::LAT_MIN || $d->latitude > self::LAT_MAX || $d->longitude < self::LNG_MIN || $d->longitude > self::LNG_MAX) {
                        $problems[] = 'Outside Nigeria';
                    }

                    $sharing = $byPoint[sprintf('%.6f,%.6f', $d->latitude, $d->longitude)]
                        ->reject(fn ($other) => $other->id === $d->id);
                    if ($sharing->isNotEmpty()) {
                        $problems[] = 'Same point as '.$sharing
                            ->map(fn ($other) => "{$other->name} #{$other->id}".($other->branch_id !== $d->branch_id ? " ({$other->branch})" : ''))
                            ->implode(', ');
                    }

                    $others = ($byBranch[$d->branch_id] ?? collect())->reject(fn ($other) => $other->id === $d->id);
                    if ($d->branch_id !== null && $others->count() >= self::MIN_NEIGHBOURS) {
                        $km = self::distanceKm($d->latitude, $d->longitude, self::median($others->pluck('latitude')), self::median($others->pluck('longitude')));
                        if ($km > $maxKm) {
                            $problems[] = sprintf('%d km from the middle of the other %s divisions', round($km), $d->branch);
                        }
                    }
                }

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'branch' => $d->branch,
                    'latitude' => $d->latitude,
                    'longitude' => $d->longitude,
                    'problems' => $problems,
                ];
            })
            ->filter(fn (array $row) => $row['problems'] !== [])
            ->values();
    }

    /** Great-circle distance in km (haversine). */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function median(Collection $values): float
    {
        return (float) $values->median();
    }
}
