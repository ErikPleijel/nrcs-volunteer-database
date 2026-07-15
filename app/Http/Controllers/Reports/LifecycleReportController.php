<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\StatsSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LifecycleReportController extends Controller
{
    /**
     * Lifecycle status keys, in fixed display order, mapped to their
     * stats_snapshots column (same name) and checkbox/chart label.
     */
    private const STATUS_DEFS = [
        'pending_engagement' => 'Pending',
        'active'             => 'Active',
        'dormant'            => 'Dormant',
        'archived'           => 'Archived',
    ];

    /** Default checkbox state when none of the four keys are present in the URL at all. */
    private const DEFAULT_CHECKED = [
        'pending_engagement' => true,
        'active'             => true,
        'dormant'            => true,
        'archived'           => false,
    ];

    /**
     * Lifecycle Report: National → Branch → Division drill (no unit level —
     * stats_snapshots has no red_cross_unit_id column, so there's no data
     * to drill into below division), line chart + drill-down table, with a
     * 4-checkbox status filter controlling both simultaneously.
     *
     * Unrestricted by design, same as admin-activities — every viewer
     * drills the same way regardless of their own access level/scope.
     */
    public function index(Request $request)
    {
        // Bake the default checkbox state into the URL (explicit, bookmarkable)
        // instead of applying it silently — same convention already used for
        // person_type on the campaign-planning reports this session. Checked
        // via hasAny() because unchecked checkboxes are simply absent from a
        // form submission; the form itself always submits all four keys
        // (each paired with a same-named hidden fallback), so hasAny() being
        // false can only mean "never submitted at all", not "submitted with
        // everything unchecked".
        if (! $request->hasAny(array_keys(self::STATUS_DEFS))) {
            $defaults = array_map(fn ($v) => $v ? '1' : '0', self::DEFAULT_CHECKED);

            return redirect()->route('reports.lifecycle.national', array_merge($request->query(), $defaults));
        }

        $checked = [];
        foreach (self::STATUS_DEFS as $key => $label) {
            $checked[$key] = $request->boolean($key);
        }
        $checkedKeys = array_keys(array_filter($checked));

        $trendOptions = [
            '2_years' => 24,
            '4_years' => 48,
            '6_years' => 72,
            '8_years' => 96,
        ];
        $selectedTrendKey = $request->input('trend_months', '2_years');
        $months = $trendOptions[$selectedTrendKey] ?? 24;

        // ── Drill state: branch → division only, two levels ────────────────
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $currentBranch = $branchId ? Branch::find($branchId) : null;
        $drillLevel = $currentBranch ? 'division' : 'branch';

        $drillCrumbs = [
            [
                'label' => 'National',
                'href'  => $drillLevel === 'branch'
                    ? null
                    : route('reports.lifecycle.national', array_merge($request->except('branch_id'), [])),
                'badge' => null,
            ],
        ];
        if ($currentBranch) {
            $drillCrumbs[] = ['label' => $currentBranch->name, 'href' => null, 'badge' => null];
        }

        // ── Existence check for the "no data yet" state ─────────────────────
        // Checks whether ANY snapshot row has a non-null value in any of the
        // currently-checked columns — not just whether snapshot rows exist
        // (they do, 10k+ backfilled rows, but every lifecycle column on
        // every one of them is NULL until the daily cron actually runs).
        $hasLifecycleData = ! empty($checkedKeys) && StatsSnapshot::query()
            ->where(function ($q) use ($checkedKeys) {
                foreach ($checkedKeys as $key) {
                    $q->orWhereNotNull($key);
                }
            })
            ->exists();

        $chartDataset = ['labels' => [], 'series' => []];
        $drillRows = collect();
        $drillAreaHeader = $drillLevel === 'division' ? 'Division' : 'Branch';
        $drillRowField = $drillLevel === 'branch' ? 'branch_id' : null;

        if ($hasLifecycleData) {
            $chartDataset = $this->trendSeries($months, $checkedKeys, $branchId);
            $drillRows = $drillLevel === 'branch'
                ? $this->branchRows($checkedKeys)
                : $this->divisionRows($checkedKeys, $branchId);
        }

        $columns = [];
        foreach ($checkedKeys as $key) {
            $columns[] = ['key' => $key, 'label' => self::STATUS_DEFS[$key]];
        }

        // CSV export reuses $drillRows/$columns/$drillAreaHeader exactly as
        // just computed above for the on-screen table — same drill level,
        // same checked statuses, same branchRows()/divisionRows() call (or
        // the empty collection when hasLifecycleData is false), so the file
        // can never drift from what's rendered.
        if ($request->input('export') === 'csv') {
            return $this->exportCsv($columns, $drillRows, $drillAreaHeader, $currentBranch);
        }

        return view('reports.lifecycle.index', [
            'checked'          => $checked,
            'trendOptions'     => $trendOptions,
            'selectedTrendKey' => $selectedTrendKey,
            'branchId'         => $branchId,
            'currentBranch'    => $currentBranch,
            'drillLevel'       => $drillLevel,
            'drillCrumbs'      => $drillCrumbs,
            'drillRows'        => $drillRows,
            'drillAreaHeader'  => $drillAreaHeader,
            'drillRowField'    => $drillRowField,
            'columns'          => $columns,
            'chartDataset'     => $chartDataset,
            'hasLifecycleData' => $hasLifecycleData,
            'statusDefs'       => self::STATUS_DEFS,
        ]);
    }

    /**
     * Streams the drill-down table's own $drillRows/$columns as CSV — not a
     * separate query or re-derivation, so it can't drift from what's on
     * screen. When hasLifecycleData is false, $drillRows is the same empty
     * collection the table itself would render as "No data found for this
     * level" — the CSV still comes out valid: header row, zero data rows.
     * Values are exported as plain numbers (no thousand-separator
     * formatting, empty string for null) since a CSV is meant to be
     * machine-read; the on-screen "—"/number_format() treatment is a
     * display nicety that doesn't belong in exported data.
     */
    private function exportCsv(array $columns, $drillRows, string $areaHeader, ?Branch $branch): StreamedResponse
    {
        $date = now()->toDateString();
        $scopeSlug = $branch ? Str::slug($branch->name) : 'national';
        $filename = "lifecycle-report-{$scopeSlug}-{$date}.csv";

        return response()->streamDownload(function () use ($columns, $drillRows, $areaHeader) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM must be the absolute first bytes — Excel only
            // recognizes it at the true start of the file. Immediately
            // followed by Excel's own "sep=" override line (raw fwrite, not
            // fputcsv, so it isn't quoted/escaped as a data field) so a
            // double-clicked file splits on comma regardless of the
            // system's regional list-separator setting.
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, array_merge([$areaHeader], array_column($columns, 'label')));

            foreach ($drillRows as $row) {
                $line = [$row['name']];
                foreach ($columns as $column) {
                    $value = $row[$column['key']] ?? null;
                    $line[] = $value !== null ? $value : '';
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Branch-level rows for the drill-down table: one row per branch,
     * summed across every division/no-division snapshot row under it,
     * as of the latest snapshot date at or before today.
     */
    private function branchRows(array $checkedKeys)
    {
        $latestDate = StatsSnapshot::where('snapshot_date', '<=', now())->max('snapshot_date');
        if (! $latestDate) {
            return collect();
        }

        return StatsSnapshot::query()
            ->where('stats_snapshots.snapshot_date', $latestDate)
            ->whereNotNull('stats_snapshots.branch_id')
            ->join('branches', 'stats_snapshots.branch_id', '=', 'branches.id')
            ->selectRaw('branches.id, branches.name, '.$this->sumSelect($checkedKeys))
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name')
            ->get()
            ->map(fn ($row) => $this->rowToArray($row, $checkedKeys));
    }

    /**
     * Division-level rows, scoped to one branch — the drill's leaf level
     * (no unit granularity exists in stats_snapshots).
     */
    private function divisionRows(array $checkedKeys, int $branchId)
    {
        $latestDate = StatsSnapshot::where('snapshot_date', '<=', now())->max('snapshot_date');
        if (! $latestDate) {
            return collect();
        }

        return StatsSnapshot::query()
            ->where('stats_snapshots.snapshot_date', $latestDate)
            ->where('stats_snapshots.branch_id', $branchId)
            ->whereNotNull('stats_snapshots.division_id')
            ->join('divisions', 'stats_snapshots.division_id', '=', 'divisions.id')
            ->selectRaw('divisions.id, divisions.name, '.$this->sumSelect($checkedKeys))
            ->groupBy('divisions.id', 'divisions.name')
            ->orderBy('divisions.name')
            ->get()
            ->map(fn ($row) => $this->rowToArray($row, $checkedKeys));
    }

    /**
     * Eloquent models from a selectRaw()/groupBy() aggregate query only carry
     * exactly the selected columns as attributes — (array) $row would dump
     * the model's internal object state instead, so pull attributes out
     * explicitly and cast the summed columns to int (NULL stays NULL).
     */
    private function rowToArray($row, array $checkedKeys): array
    {
        $data = ['id' => (int) $row->id, 'name' => $row->name];
        foreach ($checkedKeys as $key) {
            $data[$key] = $row->$key !== null ? (int) $row->$key : null;
        }

        return $data;
    }

    private function sumSelect(array $checkedKeys): string
    {
        return collect($checkedKeys)
            ->map(fn ($key) => "SUM({$key}) as {$key}")
            ->implode(', ');
    }

    /**
     * Monthly trend series from stats_snapshots, one series per checked
     * status. For each month in range: use the latest snapshot within that
     * month; months without a snapshot produce null points (gap), same
     * approach as MemberReportController::snapshotTrendSeries() — this
     * report reuses that convention (monthly/trend_months) rather than
     * admin-activities' fixed 1/2/4-year selector, since the data source is
     * the same monthly stats_snapshots table, not raw event timestamps.
     */
    private function trendSeries(int $months, array $checkedKeys, ?int $branchId): array
    {
        $labels = [];
        $seriesData = array_fill_keys($checkedKeys, []);

        $start = now()->subMonths($months)->startOfMonth();

        for ($m = $start->copy(); $m <= now(); $m->addMonth()) {
            $labels[] = $m->format('M Y');

            $date = StatsSnapshot::whereBetween('snapshot_date', [
                    $m->toDateString(),
                    $m->copy()->endOfMonth()->toDateString(),
                ])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->max('snapshot_date');

            if (! $date) {
                foreach ($checkedKeys as $key) {
                    $seriesData[$key][] = null;
                }
                continue;
            }

            $row = StatsSnapshot::where('snapshot_date', $date)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->selectRaw($this->sumSelect($checkedKeys))
                ->first();

            foreach ($checkedKeys as $key) {
                $seriesData[$key][] = $row->$key !== null ? (int) $row->$key : null;
            }
        }

        $series = [];
        foreach ($checkedKeys as $key) {
            $series[] = ['label' => self::STATUS_DEFS[$key], 'data' => $seriesData[$key]];
        }

        return ['labels' => $labels, 'series' => $series];
    }
}
