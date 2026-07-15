<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Services\Reports\AdminActivityStatsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminActivityReportController extends Controller
{
    protected AdminActivityStatsService $adminActivityStatsService;

    public function __construct(AdminActivityStatsService $adminActivityStatsService)
    {
        $this->adminActivityStatsService = $adminActivityStatsService;
    }

    /**
     * Admin Activities report: ID cards printed, certificates printed by
     * type, and messages sent by channel — each as its own tab.
     *
     * - Tab selector (idcards / certificates / messages)
     * - Trend chart per tab (1/2/4 years, default 2)
     * - Branch filter: any branch may be selected regardless of the
     *   viewer's own access level/scope (intentionally unrestricted —
     *   this report is for comparing branches, not scoped self-service).
     */
    public function index(Request $request)
    {
        $validTabs = ['idcards', 'certificates', 'messages'];
        $tab = $request->input('tab', 'idcards');
        if (! in_array($tab, $validTabs, true)) {
            $tab = 'idcards';
        }

        $trendYearOptions = [1 => '1 Year', 2 => '2 Years', 4 => '4 Years'];
        $trendYears = (int) $request->input('trend_years', 2);
        if (! array_key_exists($trendYears, $trendYearOptions)) {
            $trendYears = 2;
        }

        $certificateTypeOptions = [
            'training_competence'     => 'Training – Competence',
            'training_attendance'     => 'Training – Attendance',
            'membership'              => 'Membership',
            'donation'                => 'Donation',
            'volunteering'            => 'Volunteering',
            'organisation_membership' => 'Organisation – Membership',
            'organisation_donation'   => 'Organisation – Donation',
        ];
        $certificateType = $request->input('certificate_type', 'membership');
        if (! array_key_exists($certificateType, $certificateTypeOptions)) {
            $certificateType = 'membership';
        }

        // No restriction on which branch may be selected — branch/division
        // users can compare against branches other than their own here.
        $branchId = $request->input('branch_id');
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;

        $branches = Branch::active()->select('id', 'name')->orderBy('name')->get();

        $idCardTrend = null;
        $certificateTrend = null;
        $messageTrend = null;

        if ($tab === 'idcards') {
            $idCardTrend = $this->adminActivityStatsService->getIdCardTrend($trendYears, $branchId);
        } elseif ($tab === 'certificates') {
            $certificateTrend = $this->adminActivityStatsService->getCertificateTrend($trendYears, $branchId, $certificateType);
        } elseif ($tab === 'messages') {
            $messageTrend = $this->adminActivityStatsService->getMessageTrend($trendYears, $branchId);
        }

        // ── Drill-down table (National → Branch → Division → RC Unit) ──────
        // Unrestricted by design, same as the branch filter above — every
        // viewer drills the same way regardless of their own access level.
        // branch_id is shared with the filter dropdown above: drilling into
        // a branch row also scopes the trend chart to that branch, same as
        // picking it from the dropdown.
        $divisionId = $request->filled('division_id') ? (int) $request->input('division_id') : null;

        if ($branchId && $divisionId) {
            $drillLevel = 'unit';
        } elseif ($branchId) {
            $drillLevel = 'division';
        } else {
            $drillLevel = 'branch';
        }

        // Organisation-based certificate selections have no division/unit
        // column to drill into (organisations table is branch_id only) —
        // branch is a structural dead end for these, so clamp back to it
        // even if a stray division_id is left over from another selection.
        $organisationCertificateTypes = ['organisation_membership', 'organisation_donation'];
        $isOrganisationScoped = $tab === 'certificates' && in_array($certificateType, $organisationCertificateTypes, true);
        if ($isOrganisationScoped) {
            $drillLevel = 'branch';
        }

        $currentBranch = $branchId ? Branch::find($branchId) : null;
        $currentDivision = $divisionId ? Division::find($divisionId) : null;

        $drillCrumbs = [];
        $drillCrumbs[] = [
            'label' => 'National',
            'href'  => $drillLevel === 'branch'
                ? null
                : route('reports.admin-activities.index', array_merge(request()->except(['branch_id', 'division_id']), ['tab' => $tab])),
            'badge' => null,
        ];
        if ($currentBranch && in_array($drillLevel, ['division', 'unit'], true)) {
            $drillCrumbs[] = [
                'label' => $currentBranch->name,
                'href'  => $drillLevel === 'unit'
                    ? route('reports.admin-activities.index', array_merge(request()->except(['division_id']), ['tab' => $tab]))
                    : null,
                'badge' => null,
            ];
        }
        if ($currentDivision && $drillLevel === 'unit') {
            $drillCrumbs[] = ['label' => $currentDivision->name, 'href' => null, 'badge' => null];
        }

        // Rows + column header + link field for the active tab only.
        $drillRows = collect();
        $drillAreaHeader = 'Branch';
        $drillRowField = 'branch_id';

        if ($tab === 'idcards') {
            switch ($drillLevel) {
                case 'branch':
                    $drillRows = $this->adminActivityStatsService->getIdCardSummaryByBranch($trendYears);
                    $drillAreaHeader = 'Branch';
                    $drillRowField = 'branch_id';
                    break;
                case 'division':
                    $drillRows = $this->adminActivityStatsService->getIdCardSummaryByDivision($trendYears, $branchId);
                    $drillAreaHeader = 'Division';
                    $drillRowField = 'division_id';
                    break;
                case 'unit':
                    $drillRows = $this->adminActivityStatsService->getIdCardSummaryByUnit($trendYears, $divisionId);
                    $drillAreaHeader = 'Red Cross Unit';
                    $drillRowField = null;
                    break;
            }
        } elseif ($tab === 'certificates') {
            switch ($drillLevel) {
                case 'branch':
                    $drillRows = $this->adminActivityStatsService->getCertificateSummaryByBranch($trendYears, $certificateType);
                    $drillAreaHeader = 'Branch';
                    // Organisation-based types are a structural dead end at
                    // branch level — no link field means the blade renders
                    // plain text instead of an <a>.
                    $drillRowField = $isOrganisationScoped ? null : 'branch_id';
                    break;
                case 'division':
                    $drillRows = $this->adminActivityStatsService->getCertificateSummaryByDivision($trendYears, $certificateType, $branchId);
                    $drillAreaHeader = 'Division';
                    $drillRowField = 'division_id';
                    break;
                case 'unit':
                    $drillRows = $this->adminActivityStatsService->getCertificateSummaryByUnit($trendYears, $certificateType, $divisionId);
                    $drillAreaHeader = 'Red Cross Unit';
                    $drillRowField = null;
                    break;
            }
        } elseif ($tab === 'messages') {
            switch ($drillLevel) {
                case 'branch':
                    $drillRows = $this->adminActivityStatsService->getMessageSummaryByBranch($trendYears);
                    $drillAreaHeader = 'Branch';
                    // Unlike organisation-based certificates, branch-level
                    // attribution is fully correct for both recipient types
                    // here, so this still drills normally to division level.
                    $drillRowField = 'branch_id';
                    break;
                case 'division':
                    $drillRows = $this->adminActivityStatsService->getMessageSummaryByDivision($trendYears, $branchId);
                    $drillAreaHeader = 'Division';
                    $drillRowField = 'division_id';
                    break;
                case 'unit':
                    $drillRows = $this->adminActivityStatsService->getMessageSummaryByUnit($trendYears, $divisionId);
                    $drillAreaHeader = 'Red Cross Unit';
                    $drillRowField = null;
                    break;
            }
        }

        // Messages: organisation-recipient messages can't be attributed
        // below branch level (organisations has no division_id/unit_id) —
        // show an explanatory note once we're drilled past branch, so the
        // smaller division/unit totals don't read as unexplained data loss.
        $showMessagesOrgNote = $tab === 'messages' && in_array($drillLevel, ['division', 'unit'], true);

        // Export reflects whichever tab AND drill level is currently active
        // — $drillRows/$drillAreaHeader above are already scoped to exactly
        // that combination, same "current view only" rule as every other
        // report's export.
        if ($request->input('export') === 'csv') {
            $scopeSlug = match ($drillLevel) {
                'branch'   => 'national',
                'division' => \Illuminate\Support\Str::slug($currentBranch->name),
                'unit'     => \Illuminate\Support\Str::slug($currentBranch->name).'-'.\Illuminate\Support\Str::slug($currentDivision->name),
            };

            return $this->exportCsv(
                $tab,
                $drillRows,
                $drillAreaHeader,
                $scopeSlug,
                $tab === 'certificates' ? $certificateType : null
            );
        }

        return view('reports.admin-activities.index', compact(
            'tab', 'trendYears', 'certificateType', 'branchId', 'branches',
            'idCardTrend', 'certificateTrend', 'messageTrend',
            'trendYearOptions', 'certificateTypeOptions',
            'drillLevel', 'drillCrumbs', 'drillRows', 'drillAreaHeader', 'drillRowField',
            'isOrganisationScoped', 'showMessagesOrgNote'
        ));
    }

    /**
     * Streams $drillRows as CSV — same BOM + sep=, + fputcsv pattern as
     * MemberReportController::exportCsv(). Dead-end rows (organisation
     * certificate types, "(No RC Unit)") carry a real 'total' regardless of
     * whether the on-screen table links them, so no special-casing is
     * needed here — the CSV just reads the row data, independent of the
     * blade's link/no-link decision.
     *
     * Messages tab only: splits the on-screen "142 (12 org)" combined
     * display into two real numeric columns (Total, Org Recipients) rather
     * than embedding a mixed string, since the CSV is meant for reuse.
     * The Org Recipients column is always present for this tab, even at
     * division/unit level where it's simply 0 (no 'org_total' key on those
     * rows) — kept for a single consistent column schema across drill
     * levels for the same tab, rather than a export shape that changes
     * depending on how deep you've drilled.
     */
    private function exportCsv(string $tab, $drillRows, string $areaLabel, string $scopeSlug, ?string $certificateType = null): StreamedResponse
    {
        $date = now()->toDateString();
        $typeSuffix = $certificateType ? '-'.$certificateType : '';
        $filename = "admin-activities-{$tab}{$typeSuffix}-{$scopeSlug}-{$date}.csv";

        return response()->streamDownload(function () use ($drillRows, $areaLabel, $tab) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            $header = $tab === 'messages' ? [$areaLabel, 'Total', 'Org Recipients'] : [$areaLabel, 'Total'];
            fputcsv($out, $header);

            foreach ($drillRows as $row) {
                $line = [$row['name'], $row['total']];
                if ($tab === 'messages') {
                    $line[] = $row['org_total'] ?? 0;
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
