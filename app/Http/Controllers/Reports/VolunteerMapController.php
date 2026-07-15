<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\User;

class VolunteerMapController extends Controller
{
    public function branches()
    {
        $counts = User::volunteers()
            ->selectRaw('branch_id, COUNT(*) as cnt')
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->pluck('cnt', 'branch_id');

        $branches = Branch::select('id', 'name', 'latitude', 'longitude', 'heat_score')->get();

        $points       = [];
        $missingCount = 0;

        foreach ($branches as $b) {
            $cnt = (int) ($counts[$b->id] ?? 0);
            if ($b->latitude === null || $b->longitude === null) {
                $missingCount++;
                continue;
            }
            $points[] = [
                'name'  => $b->name,
                'count' => $cnt,
                'lat'   => (float) $b->latitude,
                'lng'   => (float) $b->longitude,
                'heat'  => $b->heat_score !== null ? (float) $b->heat_score : null,
            ];
        }

        $maxCount = collect($points)->max('count') ?: 1;

        return view('reports.maps.volunteers', [
            'level'        => 'branch',
            'title'        => 'Volunteers by Branch',
            'points'       => $points,
            'maxCount'     => $maxCount,
            'missingCount' => $missingCount,
            'hasHeat'      => collect($points)->contains(fn ($p) => $p['heat'] !== null),
        ]);
    }

    public function divisions()
    {
        $counts = User::volunteers()
            ->selectRaw('division_id, COUNT(*) as cnt')
            ->whereNotNull('division_id')
            ->groupBy('division_id')
            ->pluck('cnt', 'division_id');

        $divisions = Division::select('id', 'name', 'latitude', 'longitude', 'heat_score')->get();

        $points       = [];
        $missingCount = 0;

        foreach ($divisions as $d) {
            $cnt = (int) ($counts[$d->id] ?? 0);
            if ($d->latitude === null || $d->longitude === null) {
                $missingCount++;
                continue;
            }
            $points[] = [
                'name'  => $d->name,
                'count' => $cnt,
                'lat'   => (float) $d->latitude,
                'lng'   => (float) $d->longitude,
                'heat'  => $d->heat_score !== null ? (float) $d->heat_score : null,
            ];
        }

        $maxCount = collect($points)->max('count') ?: 1;

        return view('reports.maps.volunteers', [
            'level'        => 'division',
            'title'        => 'Volunteers by Division',
            'points'       => $points,
            'maxCount'     => $maxCount,
            'missingCount' => $missingCount,
            'hasHeat'      => collect($points)->contains(fn ($p) => $p['heat'] !== null),
        ]);
    }
}
