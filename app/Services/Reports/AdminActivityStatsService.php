<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\IdCardPrint;
use App\Models\RedCrossUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminActivityStatsService
{
    /**
     * Global TTL for all admin activity stats cache (in seconds).
     *
     * During dev, you can set this to 1 (or even 0 to disable caching).
     * In production, 3600 (1 hour) is a reasonable default.
     */
    private int $cacheTtl = 1;

    /**
     * Small helper so we don't repeat Cache::remember everywhere.
     */
    private function remember(string $key, \Closure $callback)
    {
        // If TTL <= 0, skip cache entirely (useful in dev/debug)
        if ($this->cacheTtl <= 0) {
            return $callback();
        }

        return Cache::remember($key, $this->cacheTtl, $callback);
    }

    /**
     * Monthly ID card print trend for Chart.js.
     *
     * Returns:
     * [
     *   'labels' => ['Jan 2022', 'Feb 2022', ...],
     *   'values' => [12, 8, 19, ...],
     * ]
     *
     * $trendYears: 2,4,6,8 (default 4 in your controller/UI).
     * $branchId: null for national, or specific branch for branch chart.
     */
    public function getIdCardTrend(int $trendYears, ?int $branchId = null): array
    {
        // Clamp years between 1 and 10, just to be safe
        $trendYears = max(1, min($trendYears, 10));

        $cacheKey = 'admin_activity_idcard_trend_' . $trendYears . ($branchId ? '_branch_' . $branchId : '');

        return $this->remember($cacheKey, function () use ($trendYears, $branchId) {
            // End = current month, start = N years back
            $end   = now()->startOfMonth();
            $start = (clone $end)->subYears($trendYears)->startOfMonth();

            $query = IdCardPrint::query()
                ->selectRaw("DATE_FORMAT(id_card_prints.printed_at, '%Y-%m') as ym, COUNT(*) as cnt")
                ->whereBetween('id_card_prints.printed_at', [$start, (clone $end)->endOfMonth()]);

            if ($branchId) {
                $query->join('users', 'users.id', '=', 'id_card_prints.user_id')
                    ->where('users.branch_id', $branchId);
            }

            $rows = $query->groupBy('ym')->orderBy('ym')->get();

            return $this->buildMonthlySeries($rows, $start, $end);
        });
    }

    /**
     * Rolling window shared by the drill-down summary methods — same
     * "N years back from the current month" window the trend methods
     * already use (see getIdCardTrend()/getCertificateTrend() above),
     * so a summary table's total matches the chart drawn above it.
     *
     * @return array{0: Carbon, 1: Carbon} [start, end]
     */
    private function rollingWindow(int $trendYears): array
    {
        $trendYears = max(1, min($trendYears, 10));

        $end   = now()->startOfMonth();
        $start = (clone $end)->subYears($trendYears)->startOfMonth();

        return [$start, (clone $end)->endOfMonth()];
    }

    /**
     * Drill-down table, national level: one row per active branch with its
     * total ID card print count for the rolling window. Every active branch
     * is included even at zero (the blade renders zero as "—").
     */
    public function getIdCardSummaryByBranch(int $trendYears): Collection
    {
        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_idcard_summary_branch_' . $trendYears;

        return $this->remember($cacheKey, function () use ($start, $end) {
            $counts = IdCardPrint::query()
                ->join('users', 'users.id', '=', 'id_card_prints.user_id')
                ->whereBetween('id_card_prints.printed_at', [$start, $end])
                ->whereNotNull('users.branch_id')
                ->selectRaw('users.branch_id, COUNT(*) as cnt')
                ->groupBy('users.branch_id')
                ->pluck('cnt', 'branch_id');

            return Branch::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($branch) => [
                    'id'    => $branch->id,
                    'name'  => $branch->name,
                    'total' => (int) ($counts[$branch->id] ?? 0),
                ]);
        });
    }

    /**
     * Drill-down table, branch level: one row per division in the given
     * branch with its total ID card print count for the rolling window.
     */
    public function getIdCardSummaryByDivision(int $trendYears, int $branchId): Collection
    {
        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_idcard_summary_division_' . $trendYears . '_branch_' . $branchId;

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            $counts = IdCardPrint::query()
                ->join('users', 'users.id', '=', 'id_card_prints.user_id')
                ->where('users.branch_id', $branchId)
                ->whereBetween('id_card_prints.printed_at', [$start, $end])
                ->whereNotNull('users.division_id')
                ->selectRaw('users.division_id, COUNT(*) as cnt')
                ->groupBy('users.division_id')
                ->pluck('cnt', 'division_id');

            return Division::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($division) => [
                    'id'    => $division->id,
                    'name'  => $division->name,
                    'total' => (int) ($counts[$division->id] ?? 0),
                ]);
        });
    }

    /**
     * Drill-down table, division level: one row per RC unit in the given
     * division with its total ID card print count for the rolling window,
     * plus a synthetic "(No RC Unit)" row (id null) if the division has
     * users without a unit assignment — mirrors IdCardExpiryReportController's
     * division() convention.
     */
    public function getIdCardSummaryByUnit(int $trendYears, int $divisionId): Collection
    {
        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_idcard_summary_unit_' . $trendYears . '_division_' . $divisionId;

        return $this->remember($cacheKey, function () use ($start, $end, $divisionId) {
            $counts = IdCardPrint::query()
                ->join('users', 'users.id', '=', 'id_card_prints.user_id')
                ->where('users.division_id', $divisionId)
                ->whereBetween('id_card_prints.printed_at', [$start, $end])
                ->whereNotNull('users.red_cross_unit_id')
                ->selectRaw('users.red_cross_unit_id, COUNT(*) as cnt')
                ->groupBy('users.red_cross_unit_id')
                ->pluck('cnt', 'red_cross_unit_id');

            $rows = RedCrossUnit::where('division_id', $divisionId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($unit) => [
                    'id'    => $unit->id,
                    'name'  => $unit->name,
                    'total' => (int) ($counts[$unit->id] ?? 0),
                ]);

            if (User::where('division_id', $divisionId)->whereNull('red_cross_unit_id')->exists()) {
                $noUnitTotal = IdCardPrint::query()
                    ->join('users', 'users.id', '=', 'id_card_prints.user_id')
                    ->where('users.division_id', $divisionId)
                    ->whereNull('users.red_cross_unit_id')
                    ->whereBetween('id_card_prints.printed_at', [$start, $end])
                    ->count();

                $rows->push([
                    'id'    => null,
                    'name'  => '(No RC Unit)',
                    'total' => $noUnitTotal,
                ]);
            }

            return $rows;
        });
    }

    /**
     * Monthly certificate print trend for Chart.js, for a single certificate type.
     *
     * Returns:
     * [
     *   'labels' => ['Jan 2022', 'Feb 2022', ...],
     *   'values' => [12, 8, 19, ...],
     * ]
     *
     * Branch filtering depends on certificate type: organisation-based
     * certificates ('organisation_membership', 'organisation_donation') join
     * organisations.branch_id; every other (user-based) certificate type joins
     * users.branch_id.
     */
    public function getCertificateTrend(int $trendYears, ?int $branchId, string $certificateType): array
    {
        // Clamp years between 1 and 10, just to be safe
        $trendYears = max(1, min($trendYears, 10));

        $cacheKey = 'admin_activity_certificate_trend_' . $trendYears . '_' . $certificateType . ($branchId ? '_branch_' . $branchId : '');

        return $this->remember($cacheKey, function () use ($trendYears, $branchId, $certificateType) {
            // End = current month, start = N years back
            $end   = now()->startOfMonth();
            $start = (clone $end)->subYears($trendYears)->startOfMonth();

            $query = CertificatePrint::query()
                ->selectRaw("DATE_FORMAT(certificates_print.printed_at, '%Y-%m') as ym, COUNT(*) as cnt")
                ->where('certificates_print.certificate_type', $certificateType)
                ->whereBetween('certificates_print.printed_at', [$start, (clone $end)->endOfMonth()]);

            $organisationCertificateTypes = ['organisation_membership', 'organisation_donation'];

            if (in_array($certificateType, $organisationCertificateTypes, true)) {
                if ($branchId) {
                    $query->join('organisations', 'organisations.id', '=', 'certificates_print.organisation_id')
                        ->where('organisations.branch_id', $branchId);
                }
            } else {
                if ($branchId) {
                    $query->join('users', 'users.id', '=', 'certificates_print.user_id')
                        ->where('users.branch_id', $branchId);
                }
            }

            $rows = $query->groupBy('ym')->orderBy('ym')->get();

            return $this->buildMonthlySeries($rows, $start, $end);
        });
    }

    /**
     * Certificate types whose branch attribution comes from `organisations`,
     * not `users` — organisations has no division_id/red_cross_unit_id
     * column, so these can never resolve below branch level.
     */
    private const ORGANISATION_CERTIFICATE_TYPES = ['organisation_membership', 'organisation_donation'];

    /**
     * Drill-down table, national level: one row per active branch with its
     * total certificate print count (for the given certificate type) over
     * the rolling window. User-based certificate types join through `users`;
     * organisation-based types join through `organisations` instead.
     */
    public function getCertificateSummaryByBranch(int $trendYears, string $certificateType): Collection
    {
        [$start, $end] = $this->rollingWindow($trendYears);
        $isOrganisation = in_array($certificateType, self::ORGANISATION_CERTIFICATE_TYPES, true);

        $cacheKey = 'admin_activity_certificate_summary_branch_' . $trendYears . '_' . $certificateType;

        return $this->remember($cacheKey, function () use ($start, $end, $certificateType, $isOrganisation) {
            if ($isOrganisation) {
                $counts = CertificatePrint::query()
                    ->join('organisations', 'organisations.id', '=', 'certificates_print.organisation_id')
                    ->where('certificates_print.certificate_type', $certificateType)
                    ->whereBetween('certificates_print.printed_at', [$start, $end])
                    ->whereNotNull('organisations.branch_id')
                    ->selectRaw('organisations.branch_id, COUNT(*) as cnt')
                    ->groupBy('organisations.branch_id')
                    ->pluck('cnt', 'branch_id');
            } else {
                $counts = CertificatePrint::query()
                    ->join('users', 'users.id', '=', 'certificates_print.user_id')
                    ->where('certificates_print.certificate_type', $certificateType)
                    ->whereBetween('certificates_print.printed_at', [$start, $end])
                    ->whereNotNull('users.branch_id')
                    ->selectRaw('users.branch_id, COUNT(*) as cnt')
                    ->groupBy('users.branch_id')
                    ->pluck('cnt', 'branch_id');
            }

            return Branch::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($branch) => [
                    'id'    => $branch->id,
                    'name'  => $branch->name,
                    'total' => (int) ($counts[$branch->id] ?? 0),
                ]);
        });
    }

    /**
     * Drill-down table, branch level: one row per division in the given
     * branch. Organisation-based certificate types cannot be scoped below
     * branch (organisations has no division_id) — throws if called for
     * one, since the controller clamps drillLevel back to 'branch' for
     * these types and should never reach this method for them.
     */
    public function getCertificateSummaryByDivision(int $trendYears, string $certificateType, int $branchId): Collection
    {
        if (in_array($certificateType, self::ORGANISATION_CERTIFICATE_TYPES, true)) {
            throw new \InvalidArgumentException("Certificate type [{$certificateType}] has no division-level data; organisations are not scoped below branch.");
        }

        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_certificate_summary_division_' . $trendYears . '_' . $certificateType . '_branch_' . $branchId;

        return $this->remember($cacheKey, function () use ($start, $end, $certificateType, $branchId) {
            $counts = CertificatePrint::query()
                ->join('users', 'users.id', '=', 'certificates_print.user_id')
                ->where('certificates_print.certificate_type', $certificateType)
                ->where('users.branch_id', $branchId)
                ->whereBetween('certificates_print.printed_at', [$start, $end])
                ->whereNotNull('users.division_id')
                ->selectRaw('users.division_id, COUNT(*) as cnt')
                ->groupBy('users.division_id')
                ->pluck('cnt', 'division_id');

            return Division::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($division) => [
                    'id'    => $division->id,
                    'name'  => $division->name,
                    'total' => (int) ($counts[$division->id] ?? 0),
                ]);
        });
    }

    /**
     * Drill-down table, division level: one row per RC unit in the given
     * division with its total certificate print count, plus a synthetic
     * "(No RC Unit)" row if the division has users without a unit
     * assignment. Same organisation guard as getCertificateSummaryByDivision().
     */
    public function getCertificateSummaryByUnit(int $trendYears, string $certificateType, int $divisionId): Collection
    {
        if (in_array($certificateType, self::ORGANISATION_CERTIFICATE_TYPES, true)) {
            throw new \InvalidArgumentException("Certificate type [{$certificateType}] has no unit-level data; organisations are not scoped below branch.");
        }

        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_certificate_summary_unit_' . $trendYears . '_' . $certificateType . '_division_' . $divisionId;

        return $this->remember($cacheKey, function () use ($start, $end, $certificateType, $divisionId) {
            $counts = CertificatePrint::query()
                ->join('users', 'users.id', '=', 'certificates_print.user_id')
                ->where('certificates_print.certificate_type', $certificateType)
                ->where('users.division_id', $divisionId)
                ->whereBetween('certificates_print.printed_at', [$start, $end])
                ->whereNotNull('users.red_cross_unit_id')
                ->selectRaw('users.red_cross_unit_id, COUNT(*) as cnt')
                ->groupBy('users.red_cross_unit_id')
                ->pluck('cnt', 'red_cross_unit_id');

            $rows = RedCrossUnit::where('division_id', $divisionId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($unit) => [
                    'id'    => $unit->id,
                    'name'  => $unit->name,
                    'total' => (int) ($counts[$unit->id] ?? 0),
                ]);

            if (User::where('division_id', $divisionId)->whereNull('red_cross_unit_id')->exists()) {
                $noUnitTotal = CertificatePrint::query()
                    ->join('users', 'users.id', '=', 'certificates_print.user_id')
                    ->where('certificates_print.certificate_type', $certificateType)
                    ->where('users.division_id', $divisionId)
                    ->whereNull('users.red_cross_unit_id')
                    ->whereBetween('certificates_print.printed_at', [$start, $end])
                    ->count();

                $rows->push([
                    'id'    => null,
                    'name'  => '(No RC Unit)',
                    'total' => $noUnitTotal,
                ]);
            }

            return $rows;
        });
    }

    /**
     * Monthly messages-sent trend for Chart.js, split into 'email' and 'sms' series.
     *
     * Returns:
     * [
     *   'labels' => ['Jan 2022', 'Feb 2022', ...],
     *   'email'  => [12, 8, 19, ...],
     *   'sms'    => [4, 1, 0, ...],
     * ]
     *
     * messaging_recipients has no branch_id and its 'channel' lives on the
     * parent messaging_campaigns row, not on the recipient itself. Recipients
     * can point at either a User or an Organisation (recipient_type), each of
     * which carries branch_id directly but via a different table — so this
     * runs two separate grouped queries (User-recipients, Organisation-recipients)
     * and merges them in PHP by month + channel, rather than a single join or
     * a UNION, following this codebase's existing convention of merging
     * separately-queried datasets in PHP (e.g. how the dashboard merges
     * snapshot data) instead of building one hard-to-read cross-type query.
     *
     * Channel rule: 'email', 'both', and 'email_fallback_sms' all count toward
     * the "email" series; 'sms' counts toward the "sms" series. Each campaign
     * is counted once, on exactly one series — never double-counted.
     */
    public function getMessageTrend(int $trendYears, ?int $branchId = null): array
    {
        // Clamp years between 1 and 10, just to be safe
        $trendYears = max(1, min($trendYears, 10));

        $cacheKey = 'admin_activity_message_trend_' . $trendYears . ($branchId ? '_branch_' . $branchId : '');

        return $this->remember($cacheKey, function () use ($trendYears, $branchId) {
            // End = current month, start = N years back
            $end   = now()->startOfMonth();
            $start = (clone $end)->subYears($trendYears)->startOfMonth();
            $windowEnd = (clone $end)->endOfMonth();

            // Query A: User recipients
            $userRows = DB::table('messaging_recipients')
                ->join('messaging_campaigns', 'messaging_campaigns.id', '=', 'messaging_recipients.messaging_campaign_id')
                ->join('users', 'users.id', '=', 'messaging_recipients.recipient_id')
                ->where('messaging_recipients.recipient_type', 'App\\Models\\User')
                ->where('messaging_recipients.status', 'sent')
                ->whereBetween('messaging_recipients.sent_at', [$start, $windowEnd])
                ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
                ->selectRaw("DATE_FORMAT(messaging_recipients.sent_at, '%Y-%m') as ym, messaging_campaigns.channel as channel, COUNT(*) as cnt")
                ->groupBy('ym', 'channel')
                ->get();

            // Query B: Organisation recipients
            $orgRows = DB::table('messaging_recipients')
                ->join('messaging_campaigns', 'messaging_campaigns.id', '=', 'messaging_recipients.messaging_campaign_id')
                ->join('organisations', 'organisations.id', '=', 'messaging_recipients.recipient_id')
                ->where('messaging_recipients.recipient_type', 'App\\Models\\Organisation')
                ->where('messaging_recipients.status', 'sent')
                ->whereBetween('messaging_recipients.sent_at', [$start, $windowEnd])
                ->when($branchId, fn ($q) => $q->where('organisations.branch_id', $branchId))
                ->selectRaw("DATE_FORMAT(messaging_recipients.sent_at, '%Y-%m') as ym, messaging_campaigns.channel as channel, COUNT(*) as cnt")
                ->groupBy('ym', 'channel')
                ->get();

            // Merge both result sets in PHP: [ym][channel] => cnt
            $byMonthChannel = [];

            foreach ($userRows->merge($orgRows) as $row) {
                $byMonthChannel[$row->ym][$row->channel] = ($byMonthChannel[$row->ym][$row->channel] ?? 0) + (int) $row->cnt;
            }

            $labels = [];
            $email  = [];
            $sms    = [];

            $cursor = $start->copy();
            while ($cursor <= $end) {
                $ym = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y'); // e.g. "Jan 2024"

                $monthChannels = $byMonthChannel[$ym] ?? [];

                // 'both' and 'email_fallback_sms' count once, on the email series only — never double-counted.
                $email[] = ($monthChannels['email'] ?? 0) + ($monthChannels['both'] ?? 0) + ($monthChannels['email_fallback_sms'] ?? 0);
                $sms[]   = $monthChannels['sms'] ?? 0;

                $cursor->addMonth();
            }

            return [
                'labels' => $labels,
                'email'  => $email,
                'sms'    => $sms,
            ];
        });
    }

    /**
     * Drill-down table, national level: one row per active branch with its
     * combined (User + Organisation recipient) message total over the
     * rolling window, plus the Organisation-recipient portion exposed
     * separately as 'org_total' so the view can show it as a sub-badge
     * without it being silently fused into the combined number. Both
     * recipient types resolve branch attribution correctly at this level
     * (users.branch_id / organisations.branch_id), so — unlike
     * organisation-based certificates — nothing here is a dead end and the
     * row still links to the division level.
     *
     * Deliberately does NOT key by channel (unlike getMessageTrend, which
     * feeds the chart) — the drill-down table shows one combined total per
     * row, matching the idcards/certificates tables' convention.
     */
    public function getMessageSummaryByBranch(int $trendYears): Collection
    {
        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_message_summary_branch_' . $trendYears;

        return $this->remember($cacheKey, function () use ($start, $end) {
            $userCounts = DB::table('messaging_recipients')
                ->join('users', 'users.id', '=', 'messaging_recipients.recipient_id')
                ->where('messaging_recipients.recipient_type', 'App\\Models\\User')
                ->where('messaging_recipients.status', 'sent')
                ->whereBetween('messaging_recipients.sent_at', [$start, $end])
                ->whereNotNull('users.branch_id')
                ->selectRaw('users.branch_id, COUNT(*) as cnt')
                ->groupBy('users.branch_id')
                ->pluck('cnt', 'branch_id');

            $orgCounts = DB::table('messaging_recipients')
                ->join('organisations', 'organisations.id', '=', 'messaging_recipients.recipient_id')
                ->where('messaging_recipients.recipient_type', 'App\\Models\\Organisation')
                ->where('messaging_recipients.status', 'sent')
                ->whereBetween('messaging_recipients.sent_at', [$start, $end])
                ->whereNotNull('organisations.branch_id')
                ->selectRaw('organisations.branch_id, COUNT(*) as cnt')
                ->groupBy('organisations.branch_id')
                ->pluck('cnt', 'branch_id');

            return Branch::active()->orderBy('name')->get(['id', 'name'])
                ->map(function ($branch) use ($userCounts, $orgCounts) {
                    $userTotal = (int) ($userCounts[$branch->id] ?? 0);
                    $orgTotal  = (int) ($orgCounts[$branch->id] ?? 0);

                    return [
                        'id'        => $branch->id,
                        'name'      => $branch->name,
                        'total'     => $userTotal + $orgTotal,
                        'org_total' => $orgTotal,
                    ];
                });
        });
    }

    /**
     * Drill-down table, branch level: one row per division in the given
     * branch. User-recipient only — organisations has no division_id, so
     * an Organisation-recipient message cannot be attributed to a division.
     * No 'org_total' key on these rows (the view's badge only ever shows
     * when that key is present and > 0, so it naturally stays hidden here).
     */
    public function getMessageSummaryByDivision(int $trendYears, int $branchId): Collection
    {
        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_message_summary_division_' . $trendYears . '_branch_' . $branchId;

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            $counts = DB::table('messaging_recipients')
                ->join('users', 'users.id', '=', 'messaging_recipients.recipient_id')
                ->where('messaging_recipients.recipient_type', 'App\\Models\\User')
                ->where('messaging_recipients.status', 'sent')
                ->where('users.branch_id', $branchId)
                ->whereBetween('messaging_recipients.sent_at', [$start, $end])
                ->whereNotNull('users.division_id')
                ->selectRaw('users.division_id, COUNT(*) as cnt')
                ->groupBy('users.division_id')
                ->pluck('cnt', 'division_id');

            return Division::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($division) => [
                    'id'    => $division->id,
                    'name'  => $division->name,
                    'total' => (int) ($counts[$division->id] ?? 0),
                ]);
        });
    }

    /**
     * Drill-down table, division level: one row per RC unit in the given
     * division, plus a synthetic "(No RC Unit)" row if applicable. Same
     * User-recipient-only rule as getMessageSummaryByDivision().
     */
    public function getMessageSummaryByUnit(int $trendYears, int $divisionId): Collection
    {
        [$start, $end] = $this->rollingWindow($trendYears);

        $cacheKey = 'admin_activity_message_summary_unit_' . $trendYears . '_division_' . $divisionId;

        return $this->remember($cacheKey, function () use ($start, $end, $divisionId) {
            $counts = DB::table('messaging_recipients')
                ->join('users', 'users.id', '=', 'messaging_recipients.recipient_id')
                ->where('messaging_recipients.recipient_type', 'App\\Models\\User')
                ->where('messaging_recipients.status', 'sent')
                ->where('users.division_id', $divisionId)
                ->whereBetween('messaging_recipients.sent_at', [$start, $end])
                ->whereNotNull('users.red_cross_unit_id')
                ->selectRaw('users.red_cross_unit_id, COUNT(*) as cnt')
                ->groupBy('users.red_cross_unit_id')
                ->pluck('cnt', 'red_cross_unit_id');

            $rows = RedCrossUnit::where('division_id', $divisionId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($unit) => [
                    'id'    => $unit->id,
                    'name'  => $unit->name,
                    'total' => (int) ($counts[$unit->id] ?? 0),
                ]);

            if (User::where('division_id', $divisionId)->whereNull('red_cross_unit_id')->exists()) {
                $noUnitTotal = DB::table('messaging_recipients')
                    ->join('users', 'users.id', '=', 'messaging_recipients.recipient_id')
                    ->where('messaging_recipients.recipient_type', 'App\\Models\\User')
                    ->where('messaging_recipients.status', 'sent')
                    ->where('users.division_id', $divisionId)
                    ->whereNull('users.red_cross_unit_id')
                    ->whereBetween('messaging_recipients.sent_at', [$start, $end])
                    ->count();

                $rows->push([
                    'id'    => null,
                    'name'  => '(No RC Unit)',
                    'total' => $noUnitTotal,
                ]);
            }

            return $rows;
        });
    }

    /**
     * Shared "walk month-by-month, fill gaps with 0" helper for the single-series
     * trend methods (getIdCardTrend, getCertificateTrend). Mirrors the inline
     * logic in RegistrationStatsService::getRegistrationTrendForChart() —
     * duplicated here rather than extracted to a shared base class, since
     * RegistrationStatsService (and its siblings, e.g. MembershipStatsService)
     * don't extend a common parent; each service in this codebase already
     * duplicates its own remember()/cache boilerplate independently.
     *
     * @param  \Illuminate\Support\Collection  $rows  each row has ->ym and ->cnt
     */
    private function buildMonthlySeries($rows, Carbon $start, Carbon $end): array
    {
        $byMonth = $rows->keyBy('ym');

        $labels = [];
        $values = [];

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $ym = $cursor->format('Y-m');

            $labels[] = $cursor->format('M Y'); // e.g. "Jan 2024"
            $values[] = optional($byMonth->get($ym))->cnt ?? 0;

            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'values' => array_map('intval', $values),
        ];
    }
}
