<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the Income Distribution report: how online (Paystack)
 * membership income is shared between HQ, branches and divisions.
 *
 * Only sums the shares stored on each payment when it was fulfilled
 * (IncomeDistributionCalculator) — nothing is recalculated, so a closed
 * quarter never changes. A payment's quarter is decided by paid_at
 * (Paystack's payment time, stored in Africa/Lagos time). All money in kobo.
 */
class IncomeDistributionStatsService
{
    /** Shown on screen and in the CSV whenever the current quarter is selected. */
    public const IN_PROGRESS_NOTE = 'This quarter is not finished yet. Figures will change. Do not transfer money based on these numbers.';

    public const RULE_LABELS = [
        'standard' => 'Payments with a division',
        'branch_level' => 'Payments with a branch but no division (e.g. organisations)',
        'hq_only' => 'Payments with no branch',
    ];

    /**
     * "HQ 25% / Branch 25% / Division 50%" for one percentage set (basis
     * points), naming only the parties the rule pays.
     */
    public static function describePercentages(string $rule, array $set): string
    {
        $percent = fn (int $bp) => rtrim(rtrim(number_format($bp / 100, 2), '0'), '.').'%';

        $parts = ['HQ '.$percent($set['hq'])];
        if ($rule !== 'hq_only') {
            $parts[] = 'Branch '.$percent($set['branch']);
        }
        if ($rule === 'standard') {
            $parts[] = 'Division '.$percent($set['division']);
        }

        return implode(' / ', $parts);
    }

    /**
     * Approved, not deleted Paystack membership payments. DB::table()
     * bypasses Approvable's ApprovedScope, so approval is filtered here.
     */
    public function baseQuery(): Builder
    {
        return DB::table('membership_payments')
            ->where('membership_payments.payment_channel', 'paystack')
            ->where('membership_payments.approval_status', 'approved')
            ->where('membership_payments.is_deleted', false);
    }

    /**
     * Every quarter from the earliest Paystack payment's to the current
     * one, newest first: ['2026-Q4' => '2026 Q4 (in progress)',
     * '2026-Q3' => '2026 Q3', ...].
     *
     * @return array<string, string>
     */
    public function quarterOptions(): array
    {
        $current = self::currentQuarterStart();
        $earliest = $this->baseQuery()->min('paid_at');
        $first = $earliest ? Carbon::parse($earliest)->firstOfQuarter()->startOfDay() : $current->copy();

        if ($first->gt($current)) {
            $first = $current->copy();
        }

        $options = [];
        for ($q = $current->copy(); $q->gte($first); $q->subQuarter()) {
            $key = self::quarterKey($q);
            $options[$key] = self::quarterLabel($key).(self::isInProgress($key) ? ' (in progress)' : '');
        }

        return $options;
    }

    /**
     * The latest FINISHED quarter with Paystack income, so nobody is shown
     * figures that will still change unless they choose to. The current
     * quarter only when no finished quarter has income yet.
     */
    public function defaultQuarter(): string
    {
        $latestFinished = $this->baseQuery()
            ->where('membership_payments.paid_at', '<', self::currentQuarterStart())
            ->max('paid_at');

        return self::quarterKey($latestFinished ? Carbon::parse($latestFinished) : Carbon::now());
    }

    /** Whether the quarter has not ended yet: its figures will still change. */
    public static function isInProgress(string $key): bool
    {
        return $key === self::quarterKey(Carbon::now());
    }

    /** '2026-Q3' => '2026 Q3'. */
    public static function quarterLabel(string $key): string
    {
        return str_replace('-', ' ', $key);
    }

    private static function currentQuarterStart(): Carbon
    {
        return Carbon::now()->firstOfQuarter()->startOfDay();
    }

    /**
     * '2026-Q3' => [start, start of next quarter] (Lagos time), or null when
     * the key is not a quarter.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function quarterRange(string $key): ?array
    {
        if (! preg_match('/^(\d{4})-Q([1-4])$/', $key, $m)) {
            return null;
        }

        $start = Carbon::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1)->startOfDay();

        return [$start, $start->copy()->addQuarter()];
    }

    public static function quarterKey(Carbon $moment): string
    {
        return $moment->year.'-Q'.$moment->quarter;
    }

    /**
     * Totals for the quarter: payments, paid, Paystack charges, net and the
     * three shares.
     *
     * @return array{count: int, paid: int, fees: int, net: int, hq: int, branch: int, division: int}
     */
    public function headline(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        $row = $this->inQuarter($start, $end)
            ->when($branchId !== null, fn (Builder $q) => $q->where('membership_payments.branch_id', $branchId))
            ->selectRaw($this->sumColumns())
            ->first();

        return $this->castSums($row);
    }

    /**
     * One row per branch that received income in the quarter, sorted by
     * name, then any branch that no longer exists ("Deleted branch #id"),
     * then payments with no branch at all ("No branch (to HQ)") — so the
     * rows always add up to the headline.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function branchRows(Carbon $start, Carbon $end): Collection
    {
        $rows = $this->inQuarter($start, $end)
            ->leftJoin('branches', 'branches.id', '=', 'membership_payments.branch_id')
            ->groupBy('membership_payments.branch_id', 'branches.id', 'branches.name')
            ->selectRaw('membership_payments.branch_id, branches.id as existing_branch_id, branches.name as branch_name, '.$this->sumColumns())
            ->get();

        return $rows
            ->map(function ($row) {
                $kind = match (true) {
                    $row->branch_id === null => 'none',
                    $row->existing_branch_id === null => 'deleted',
                    default => 'branch',
                };

                return [
                    'id' => $row->branch_id === null ? null : (int) $row->branch_id,
                    'kind' => $kind,
                    'label' => match ($kind) {
                        'branch' => $row->branch_name,
                        'deleted' => "Deleted branch #{$row->branch_id}",
                        'none' => 'No branch (to HQ)',
                    },
                ] + $this->castSums($row);
            })
            ->sortBy(fn (array $row) => [
                ['branch' => 0, 'deleted' => 1, 'none' => 2][$row['kind']],
                $row['kind'] === 'branch' ? mb_strtolower($row['label']) : str_pad((string) $row['id'], 12, '0', STR_PAD_LEFT),
            ])
            ->values();
    }

    /**
     * One row per division that receives a share in the quarter, grouped by
     * the division AND branch stored on the payments (so a ?branch= filter
     * matches that branch's row on the branch level exactly). Only the
     * standard rule gives a division a share. A division or branch that no
     * longer exists shows as "Deleted division #id" / "Deleted branch #id".
     * Sorted by branch, then division.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function divisionRows(Carbon $start, Carbon $end, ?int $branchId = null): Collection
    {
        return $this->inQuarter($start, $end)
            ->where('membership_payments.distribution_rule', 'standard')
            ->whereNotNull('membership_payments.division_id')
            ->when($branchId !== null, fn (Builder $q) => $q->where('membership_payments.branch_id', $branchId))
            ->leftJoin('divisions', 'divisions.id', '=', 'membership_payments.division_id')
            ->leftJoin('branches', 'branches.id', '=', 'membership_payments.branch_id')
            ->groupBy('membership_payments.division_id', 'membership_payments.branch_id', 'divisions.name', 'branches.name')
            ->havingRaw('SUM(membership_payments.division_share_kobo) > 0')
            ->select(
                'membership_payments.division_id',
                'membership_payments.branch_id',
                'divisions.name as division_name',
                'branches.name as branch_name',
                DB::raw('COUNT(*) as payments'),
                DB::raw('COALESCE(SUM(membership_payments.net_kobo), 0) as net'),
                DB::raw('COALESCE(SUM(membership_payments.division_share_kobo), 0) as division_share'),
            )
            ->get()
            ->map(fn ($row) => [
                'division_id' => (int) $row->division_id,
                'division' => $row->division_name ?? "Deleted division #{$row->division_id}",
                'division_deleted' => $row->division_name === null,
                'branch_id' => $row->branch_id === null ? null : (int) $row->branch_id,
                'branch' => $row->branch_id === null
                    ? 'No branch'
                    : ($row->branch_name ?? "Deleted branch #{$row->branch_id}"),
                'count' => (int) $row->payments,
                'net' => (int) $row->net,
                'division_share' => (int) $row->division_share,
            ])
            ->sortBy(fn (array $row) => [mb_strtolower($row['branch']), mb_strtolower($row['division']), $row['division_id']])
            ->values();
    }

    /** A branch's name, or "Deleted branch #id" if it no longer exists. */
    public function branchLabel(int $branchId): string
    {
        return DB::table('branches')->where('id', $branchId)->value('name') ?? "Deleted branch #{$branchId}";
    }

    /**
     * The percentage sets the quarter's payments were split with, per rule
     * (basis points), with how many payments used each. More than one set
     * for a rule means the percentages changed during the quarter.
     *
     * @return array<string, list<array{hq: int, branch: int, division: int, count: int}>>
     */
    public function percentageSets(Carbon $start, Carbon $end): array
    {
        return $this->inQuarter($start, $end)
            ->groupBy('distribution_rule', 'hq_pct_bp', 'branch_pct_bp', 'division_pct_bp')
            ->orderBy('distribution_rule')
            ->orderBy('hq_pct_bp')
            ->orderBy('branch_pct_bp')
            ->select('distribution_rule', 'hq_pct_bp', 'branch_pct_bp', 'division_pct_bp', DB::raw('COUNT(*) as payments'))
            ->get()
            ->groupBy('distribution_rule')
            ->map(fn (Collection $sets) => $sets->map(fn ($set) => [
                'hq' => (int) $set->hq_pct_bp,
                'branch' => (int) $set->branch_pct_bp,
                'division' => (int) $set->division_pct_bp,
                'count' => (int) $set->payments,
            ])->values()->all())
            ->all();
    }

    private function inQuarter(Carbon $start, Carbon $end): Builder
    {
        return $this->baseQuery()
            ->where('membership_payments.paid_at', '>=', $start)
            ->where('membership_payments.paid_at', '<', $end);
    }

    private function sumColumns(): string
    {
        return 'COUNT(*) as payments,
            COALESCE(SUM(membership_payments.amount_paid_kobo), 0) as paid,
            COALESCE(SUM(membership_payments.paystack_fee_kobo), 0) as fees,
            COALESCE(SUM(membership_payments.net_kobo), 0) as net,
            COALESCE(SUM(membership_payments.hq_share_kobo), 0) as hq,
            COALESCE(SUM(membership_payments.branch_share_kobo), 0) as branch,
            COALESCE(SUM(membership_payments.division_share_kobo), 0) as division,
            COALESCE(SUM(CASE WHEN membership_payments.organisation_id IS NOT NULL THEN membership_payments.net_kobo ELSE 0 END), 0) as organisation_net';
    }

    /**
     * SUM() comes back from MySQL as a decimal string; kobo sums are whole
     * numbers, so a plain int cast is exact.
     */
    private function castSums(?object $row): array
    {
        return [
            'count' => (int) ($row->payments ?? 0),
            'paid' => (int) ($row->paid ?? 0),
            'fees' => (int) ($row->fees ?? 0),
            'net' => (int) ($row->net ?? 0),
            'hq' => (int) ($row->hq ?? 0),
            'branch' => (int) ($row->branch ?? 0),
            'division' => (int) ($row->division ?? 0),
            'organisation_net' => (int) ($row->organisation_net ?? 0),
        ];
    }
}
