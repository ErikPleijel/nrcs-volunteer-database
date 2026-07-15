<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Setting;

class FirstAidMapController extends Controller
{
    public function branches()
    {
        return $this->render('branch');
    }

    public function divisions()
    {
        return $this->render('division');
    }

    private function render(string $level)
    {
        $cap = max(1, Setting::getInt('first_aid.freshness_cap_days', 1095));

        $cols = ['id', 'name', 'latitude', 'longitude',
            'first_aid_count', 'first_aid_avg_days', 'first_aid_computed_at'];

        if ($level === 'branch') {
            $areas = Branch::select($cols)->get();
            $title = 'First Aid Coverage by Branch';
        } else {
            $areas = Division::select($cols)->get();
            $title = 'First Aid Coverage by Division';
        }

        $points       = [];
        $missingCount = 0;

        foreach ($areas as $a) {
            if ($a->latitude === null || $a->longitude === null) {
                $missingCount++;
                continue;
            }

            $count   = (int) ($a->first_aid_count ?? 0);
            $avgDays = $a->first_aid_avg_days !== null ? (float) $a->first_aid_avg_days : null;

            // fresh: 1 = freshest (green), 0 = stalest (red); null when no first-aiders / not computed
            $fresh = ($count > 0 && $avgDays !== null)
                ? 1 - min(1, max(0, $avgDays) / $cap)
                : null;

            $points[] = [
                'name'     => $a->name,
                'count'    => $count,
                'lat'      => (float) $a->latitude,
                'lng'      => (float) $a->longitude,
                'fresh'    => $fresh,
                'avg_days' => $avgDays,
            ];
        }

        $maxCount = collect($points)->max('count') ?: 1;

        return view('reports.maps.first-aid', [
            'level'        => $level,
            'title'        => $title,
            'points'       => $points,
            'maxCount'     => $maxCount,
            'missingCount' => $missingCount,
            'capDays'      => $cap,
            'hasFreshness' => collect($points)->contains(fn ($p) => $p['fresh'] !== null),
        ]);
    }
}
