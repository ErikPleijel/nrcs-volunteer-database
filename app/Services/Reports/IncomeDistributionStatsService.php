<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the Income Distribution report: how membership income is
 * shared between HQ, branches and divisions.
 *
 * Only sums the shares stored on each payment when it was fulfilled or
 * approved (IncomeDistributionCalculator) — nothing is recalculated, so a
 * closed quarter never changes. All money in kobo.
 *
 * Two channels: online (Paystack, Tab 1; quarter by paid_at, Paystack's
 * payment time in Africa/Lagos time) and, via manual(), manual payments
 * collected by branches (Tab 2; quarter by accounting_date — the approval
 * day, or the payment day for legacy imports; no Paystack charge, so net =
 * amount collected).
 */
class IncomeDistributionStatsService
{
    public const CHANNEL_ONLINE = 'online';

    public const CHANNEL_MANUAL = 'manual';

    private string $channel = self::CHANNEL_ONLINE;

    /** Shown on screen and in the CSV whenever the current quarter is selected. */
    /**
     * The first quarter the report offers: the old system started in 2016.
     * Payments dated earlier are counted by countBeforeFirstQuarter().
     */
    public const FIRST_QUARTER = '2016-01-01';

    public const IN_PROGRESS_NOTE = 'This quarter is not finished yet. Figures will change. Do not transfer money based on these numbers.';

    /** Manual payments: a quarter that ends on or before the go-live date (or no go-live date yet). */
    public const BEFORE_GO_LIVE_NOTE = 'Before go-live: amounts are estimates based on fee prices, for comparison only. Money shared during this period may have been different.';

    /** Shown to national admins when MANUAL_GO_LIVE_DATE is set but unusable. */
    public const INVALID_GO_LIVE_NOTE = 'MANUAL_GO_LIVE_DATE in .env is not a valid date (YYYY-MM-DD). All payments are treated as before go-live.';

    /** Manual payments: the quarter that contains the go-live date. */
    public static function transitionNote(CarbonInterface $goLive): string
    {
        $date = self::formatGoLiveDate($goLive);

        return "Transition quarter: payments before {$date} are estimates based on fee prices; payments from {$date} are recorded amounts.";
    }

    /** "1 November 2026". */
    public static function formatGoLiveDate(CarbonInterface $goLive): string
    {
        return $goLive->format('j F Y');
    }

    /**
     * Where a quarter stands against the manual payments go-live date:
     * 'before' (ends on or before it, or no go-live date yet), 'transition'
     * (go-live falls inside it, after its first day) or 'live'.
     */
    public static function goLivePhase(Carbon $start, Carbon $end, ?CarbonInterface $goLive): string
    {
        return match (true) {
            $goLive === null || $goLive->gte($end) => 'before',
            $goLive->gt($start) => 'transition',
            default => 'live',
        };
    }

    public const RULE_LABELS = [
        'standard' => 'Payments with a division',
        'branch_level' => 'Payments with a branch but no division (the division\'s share goes to the branch)',
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

    /** The same figures for manual payments collected by branches (Tab 2). */
    public function manual(): static
    {
        $copy = clone $this;
        $copy->channel = self::CHANNEL_MANUAL;

        return $copy;
    }

    public function isManual(): bool
    {
        return $this->channel === self::CHANNEL_MANUAL;
    }

    /**
     * Approved, not deleted membership payments of this channel. DB::table()
     * bypasses Approvable's ApprovedScope, so approval is filtered here.
     * Manual payments: channel 'manual' (or empty on very old rows) with
     * their shares stored — see unsplitManualPayments() for those without.
     */
    public function baseQuery(): Builder
    {
        $query = DB::table('membership_payments')
            ->where('membership_payments.approval_status', 'approved');

        if (! $this->isManual()) {
            return $query
                ->where('membership_payments.payment_channel', 'paystack')
                ->where('membership_payments.is_deleted', false);
        }

        return $query
            ->where(fn (Builder $q) => $q->where('membership_payments.payment_channel', 'manual')->orWhereNull('membership_payments.payment_channel'))
            ->where(fn (Builder $q) => $q->where('membership_payments.is_deleted', false)->orWhereNull('membership_payments.is_deleted'))
            ->whereNotNull('membership_payments.accounting_date');
    }

    /**
     * Approved manual payments that have no shares yet, so they are in no
     * quarter. Should be 0 after payments:backfill-manual-shares.
     */
    public function unsplitManualPayments(): int
    {
        return DB::table('membership_payments')
            ->where(fn (Builder $q) => $q->where('payment_channel', 'manual')->orWhereNull('payment_channel'))
            ->where('approval_status', 'approved')
            ->where(fn (Builder $q) => $q->where('is_deleted', false)->orWhereNull('is_deleted'))
            ->where(fn (Builder $q) => $q->whereNull('distribution_rule')->orWhereNull('accounting_date'))
            ->count();
    }

    /** The column that decides a payment's quarter. */
    private function dateColumn(): string
    {
        return 'membership_payments.'.($this->isManual() ? 'accounting_date' : 'paid_at');
    }

    /** A moment as compared with dateColumn() (accounting_date is a DATE). */
    private function bound(Carbon $moment): Carbon|string
    {
        return $this->isManual() ? $moment->toDateString() : $moment;
    }

    /**
     * Every quarter from the earliest payment's (never before FIRST_QUARTER) to the current
     * one, newest first: ['2026-Q4' => '2026 Q4 (in progress)',
     * '2026-Q3' => '2026 Q3', ...].
     *
     * @return array<string, string>
     */
    public function quarterOptions(): array
    {
        $current = self::currentQuarterStart();
        $earliest = $this->baseQuery()
            ->where($this->dateColumn(), '>=', $this->bound(self::firstQuarterStart()))
            ->min($this->dateColumn());
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
     * The latest FINISHED quarter with income, so nobody is shown
     * figures that will still change unless they choose to. The current
     * quarter only when no finished quarter has income yet.
     */
    public function defaultQuarter(): string
    {
        $latestFinished = $this->baseQuery()
            ->where($this->dateColumn(), '>=', $this->bound(self::firstQuarterStart()))
            ->where($this->dateColumn(), '<', $this->bound(self::currentQuarterStart()))
            ->max($this->dateColumn());

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

    /**
     * Payments dated before FIRST_QUARTER: they belong to no quarter in the
     * list, so the page points them out to national admins.
     */
    public function countBeforeFirstQuarter(): int
    {
        return $this->baseQuery()
            ->where($this->dateColumn(), '<', $this->bound(self::firstQuarterStart()))
            ->count();
    }

    private static function firstQuarterStart(): Carbon
    {
        return Carbon::parse(self::FIRST_QUARTER)->startOfDay();
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
     * rows always add up to the headline. $branchId: that branch's row only.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function branchRows(Carbon $start, Carbon $end, ?int $branchId = null): Collection
    {
        $rows = $this->inQuarter($start, $end)
            ->when($branchId !== null, fn (Builder $q) => $q->where('membership_payments.branch_id', $branchId))
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
    public function percentageSets(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        return $this->inQuarter($start, $end)
            ->when($branchId !== null, fn (Builder $q) => $q->where('membership_payments.branch_id', $branchId))
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
            ->where($this->dateColumn(), '>=', $this->bound($start))
            ->where($this->dateColumn(), '<', $this->bound($end));
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
