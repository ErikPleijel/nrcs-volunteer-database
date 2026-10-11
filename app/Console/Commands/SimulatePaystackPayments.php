<?php

namespace App\Console\Commands;

use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Services\IncomeDistributionCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LOCAL TESTING ONLY (APP_ENV local or testing). Creates realistic, gateway-confirmed Paystack
 * membership payments without calling Paystack, so the Income Distribution
 * report has data to show. Each payment is a payment_transactions row plus
 * an approved membership_payments row, written directly (no lifecycle
 * recalculation, no markApprovedViaGateway()), with the branch/division and
 * income split the real webhook would store.
 *
 * Every simulated row has a SIM- reference (real ones are PSK-) and
 * meta.simulated = true; --purge deletes exactly those rows. The simulated
 * payments link to real local users, units and organisations, so those
 * members look paid until the rows are purged.
 */
class SimulatePaystackPayments extends Command
{
    protected $signature = 'payments:simulate
                            {--count=500 : Number of payments to create}
                            {--from= : Earliest payment date, Y-m-d (default 2025-01-01)}
                            {--to= : Latest payment date, Y-m-d (default now)}
                            {--edge-cases : Also create payments with no division / no branch (normally every payment has both, organisation payments a branch only)}
                            {--purge : Delete all simulated (SIM-) payments and nothing else}';

    protected $description = 'Create simulated Paystack membership payments for testing the Income Distribution report (local/testing only)';

    public const PREFIX = 'SIM-';

    private const TIMEZONE = 'Africa/Lagos';

    public function handle(): int
    {
        // An allowlist, not "not production": a staging or misnamed
        // environment must never get fake payments either.
        if (! app()->environment(['local', 'testing'])) {
            $this->error(sprintf(
                'payments:simulate only runs when APP_ENV is local or testing (this is "%s"). Nothing was changed.',
                app()->environment()
            ));

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge();
        }

        $count = (int) $this->option('count');
        if ($count < 1) {
            $this->error('--count must be at least 1.');

            return self::FAILURE;
        }

        try {
            $from = Carbon::parse($this->option('from') ?: '2025-01-01', self::TIMEZONE)->startOfDay();
            $to = $this->option('to')
                ? Carbon::parse($this->option('to'), self::TIMEZONE)->endOfDay()
                : Carbon::now(self::TIMEZONE);
        } catch (\Throwable) {
            $this->error('--from and --to must be dates (Y-m-d).');

            return self::FAILURE;
        }

        if ($from->gte($to)) {
            $this->error('--from must be before --to.');

            return self::FAILURE;
        }

        $fees = $this->fees();
        if ($fees['personal']->isEmpty()) {
            $this->error('No active personal membership fees found.');

            return self::FAILURE;
        }

        $payers = $this->payerPools();
        if ($payers['divisions']->isEmpty()) {
            $this->error('No users with a division found to pay.');

            return self::FAILURE;
        }

        $plans = $this->plan($count, $fees, $payers);

        $stats = ['kind' => [], 'rule' => [], 'quarter' => [], 'net' => 0];

        DB::transaction(function () use ($plans, $from, $to, &$stats) {
            foreach ($plans as $i => $plan) {
                $paidAt = $this->randomPaidAt($from, $to, $i);
                $row = $this->insertPayment($plan, $paidAt);

                $stats['kind'][$plan['kind']] = ($stats['kind'][$plan['kind']] ?? 0) + 1;
                $stats['rule'][$row['distribution_rule']] = ($stats['rule'][$row['distribution_rule']] ?? 0) + 1;
                $quarter = $paidAt->year.' Q'.$paidAt->quarter;
                $stats['quarter'][$quarter] = ($stats['quarter'][$quarter] ?? 0) + 1;
                $stats['net'] += $row['net_kobo'];
            }
        });

        ksort($stats['quarter']);

        $this->info(sprintf('Created %d simulated Paystack payments (%s to %s).', count($plans), $from->toDateString(), $to->toDateString()));
        $this->table(['Kind', 'Payments'], collect($stats['kind'])->map(fn ($n, $k) => [$k, $n])->values());
        $this->table(['Rule', 'Payments'], collect($stats['rule'])->map(fn ($n, $k) => [$k, $n])->values());
        $this->table(['Quarter', 'Payments'], collect($stats['quarter'])->map(fn ($n, $k) => [$k, $n])->values());
        $this->line(sprintf('Net distributed: ₦%s', number_format($stats['net'] / 100, 2)));
        $this->line('Remove them again with: php artisan payments:simulate --purge');

        return self::SUCCESS;
    }

    /**
     * The simulated Paystack charge in kobo: 1.5% + ₦100, the ₦100 waived
     * under ₦2,500, capped at ₦2,000. For simulation only.
     */
    public static function simulatedPaystackFee(int $amountKobo): int
    {
        $fee = intdiv($amountKobo * 15 + 500, 1000);

        if ($amountKobo >= 250_000) {
            $fee += 10_000;
        }

        return min($fee, 200_000);
    }

    private function purge(): int
    {
        $like = self::PREFIX.'%';

        [$transactions, $payments] = DB::transaction(fn () => [
            DB::table('payment_transactions')->where('reference', 'like', $like)->delete(),
            DB::table('membership_payments')->where('gateway_reference', 'like', $like)->delete(),
        ]);

        $this->info("Deleted {$payments} simulated membership payments and {$transactions} simulated payment transactions.");

        return self::SUCCESS;
    }

    /**
     * Active fees by kind. Organisation fees fall back to inactive ones
     * when none is active (true locally), so organisation payments appear.
     *
     * @return array{personal: Collection, organisation: Collection, unit: Collection}
     */
    private function fees(): array
    {
        $organisation = MembershipFee::active()->where('for_organizations', true)->get();
        if ($organisation->isEmpty()) {
            $organisation = MembershipFee::where('for_organizations', true)->get();
        }

        return [
            'personal' => MembershipFee::active()->where('for_organizations', false)->where('for_red_cross_units', false)->get(),
            'organisation' => $organisation,
            'unit' => MembershipFee::active()->forRedCrossUnits()->get(),
        ];
    }

    /**
     * Who can pay: divisions that have (non-archived) members, keyed by
     * branch so every branch gets payments; organisations with a branch;
     * active units with a division and a team leader; plus the edge cases.
     */
    private function payerPools(): array
    {
        $divisions = DB::table('users')
            ->join('divisions', 'divisions.id', '=', 'users.division_id')
            // A division without a branch would send the payment to HQ only;
            // that is an --edge-cases payment, not a normal one.
            ->whereNotNull('divisions.branch_id')
            ->where('users.lifecycle_status', '!=', 'archived')
            ->groupBy('users.division_id', 'divisions.branch_id')
            ->select('users.division_id', 'divisions.branch_id', DB::raw('COUNT(*) as members'))
            ->get();

        return [
            'divisions' => $divisions,
            'byBranch' => $divisions->groupBy('branch_id'),
            'organisations' => DB::table('organisations')->whereNull('deleted_at')->whereNotNull('branch_id')->pluck('id'),
            'units' => DB::table('red_cross_units')
                ->join('divisions', 'divisions.id', '=', 'red_cross_units.division_id')
                ->whereNotNull('divisions.branch_id')
                ->where('red_cross_units.is_active', true)
                ->whereNotNull('red_cross_units.team_leader_user_id')
                ->pluck('red_cross_units.team_leader_user_id', 'red_cross_units.id'),
            'userBranchNoDivision' => DB::table('users')->where('lifecycle_status', '!=', 'archived')->whereNotNull('branch_id')->whereNull('division_id')->value('id'),
            'userNoBranch' => DB::table('users')->where('lifecycle_status', '!=', 'archived')->whereNull('branch_id')->whereNull('division_id')->value('id'),
            'unitNoDivision' => DB::table('red_cross_units')->whereNull('division_id')->value('id'),
        ];
    }

    /**
     * One plan per payment: who pays, for what, and the branch/division the
     * webhook would store. The first payments cover every branch (and,
     * with --edge-cases, the three edge cases); the rest are ~80% personal,
     * ~10% organisation and ~10% unit. Without --edge-cases every payment
     * has a branch and division, organisation payments a branch only.
     */
    private function plan(int $count, array $fees, array $payers): array
    {
        $plans = [];

        $edgeCases = $this->option('edge-cases') ? $this->edgeCasePlans($fees, $payers) : [];

        foreach ($edgeCases as $plan) {
            if (count($plans) < $count) {
                $plans[] = $plan;
            }
        }

        // One personal payment per branch first, so all branches have income.
        foreach ($payers['byBranch']->shuffle() as $branchDivisions) {
            if (count($plans) >= $count) {
                break;
            }
            $plans[] = $this->personalPlan($fees, $branchDivisions->random());
        }

        while (count($plans) < $count) {
            $roll = mt_rand(1, 100);

            $plans[] = match (true) {
                $roll > 90 && $fees['unit']->isNotEmpty() && $payers['units']->isNotEmpty() => $this->unitPlan($fees, $payers),
                $roll > 80 && $fees['organisation']->isNotEmpty() && $payers['organisations']->isNotEmpty() => $this->organisationPlan($fees, $payers),
                default => $this->personalPlan($fees, $payers['divisions']->random()),
            };
        }

        return $plans;
    }

    private function personalPlan(array $fees, object $division, ?int $userId = null): array
    {
        $userId ??= DB::table('users')
            ->where('division_id', $division->division_id)
            ->where('lifecycle_status', '!=', 'archived')
            ->orderBy('id')
            ->offset(mt_rand(0, max(0, $division->members - 1)))
            ->value('id');

        $user = DB::table('users')->where('id', $userId)->first(['id', 'branch_id', 'division_id']);

        return $this->personalPlanFor($fees, $user);
    }

    private function personalPlanFor(array $fees, object $user): array
    {
        $fee = $fees['personal']->random();
        $idCard = mt_rand(1, 100) <= 30;

        return [
            'kind' => 'personal',
            'user_id' => $user->id,
            'organisation_id' => null,
            'red_cross_unit_id' => null,
            'fee' => $fee,
            'id_card_included' => $idCard,
            'amount_naira' => (float) $fee->amount + ($idCard ? (float) $fee->id_card_fee : 0),
            // As the webhook does: the payer's own branch/division.
            'branch_id' => $user->branch_id,
            'division_id' => $user->division_id,
        ];
    }

    private function organisationPlan(array $fees, array $payers): array
    {
        $organisationId = $payers['organisations']->random();
        $fee = $fees['organisation']->random();

        return [
            'kind' => 'organisation',
            'user_id' => $this->randomPayerId($payers),
            'organisation_id' => $organisationId,
            'red_cross_unit_id' => null,
            'fee' => $fee,
            'id_card_included' => false,
            'amount_naira' => (float) $fee->amount,
            // As the webhook does: the organisation's branch, no division.
            'branch_id' => DB::table('organisations')->where('id', $organisationId)->value('branch_id'),
            'division_id' => null,
        ];
    }

    private function unitPlan(array $fees, array $payers, ?int $unitId = null): array
    {
        $unitId ??= $payers['units']->keys()->random();
        $unit = DB::table('red_cross_units')
            ->leftJoin('divisions', 'divisions.id', '=', 'red_cross_units.division_id')
            ->where('red_cross_units.id', $unitId)
            ->first(['red_cross_units.team_leader_user_id', 'red_cross_units.division_id', 'divisions.branch_id']);
        $fee = $fees['unit']->random();

        return [
            'kind' => 'unit',
            'user_id' => $unit->team_leader_user_id ?? $this->randomPayerId($payers),
            'organisation_id' => null,
            'red_cross_unit_id' => $unitId,
            'fee' => $fee,
            'id_card_included' => false,
            'amount_naira' => (float) $fee->amount,
            // As the webhook does: the unit's division and its branch.
            'branch_id' => $unit->branch_id,
            'division_id' => $unit->division_id,
        ];
    }

    /**
     * A payer with a branch but no division (branch_level), a unit with no
     * division (hq_only) and a payer with no branch (hq_only). Real local
     * records are used; where none exists, a normal payer stands in with
     * the missing fields cleared, as the webhook would see them.
     */
    private function edgeCasePlans(array $fees, array $payers): array
    {
        $fallbackUser = fn () => DB::table('users')->where('id', $this->randomPayerId($payers))->first(['id', 'branch_id', 'division_id']);

        $branchNoDivision = $payers['userBranchNoDivision']
            ? DB::table('users')->where('id', $payers['userBranchNoDivision'])->first(['id', 'branch_id', 'division_id'])
            : tap($fallbackUser(), fn ($user) => $user->division_id = null);

        $noBranch = $payers['userNoBranch']
            ? DB::table('users')->where('id', $payers['userNoBranch'])->first(['id', 'branch_id', 'division_id'])
            : tap($fallbackUser(), function ($user) {
                $user->branch_id = null;
                $user->division_id = null;
            });

        $plans = [
            ['kind' => 'personal (branch, no division)'] + $this->personalPlanFor($fees, $branchNoDivision),
            ['kind' => 'personal (no branch)'] + $this->personalPlanFor($fees, $noBranch),
        ];

        if ($payers['unitNoDivision'] && $fees['unit']->isNotEmpty()) {
            $plans[] = ['kind' => 'unit (no division)'] + $this->unitPlan($fees, $payers, $payers['unitNoDivision']);
        }

        return $plans;
    }

    private function randomPayerId(array $payers): int
    {
        $division = $payers['divisions']->random();

        return DB::table('users')
            ->where('division_id', $division->division_id)
            ->where('lifecycle_status', '!=', 'archived')
            ->orderBy('id')
            ->offset(mt_rand(0, max(0, $division->members - 1)))
            ->value('id');
    }

    /**
     * A random moment in the range, Lagos time. One payment in twenty lands
     * within two hours of a quarter boundary inside the range.
     */
    private function randomPaidAt(CarbonInterface $from, CarbonInterface $to, int $index): Carbon
    {
        $boundaries = [];
        for ($q = $from->copy()->firstOfQuarter()->addQuarter(); $q->lt($to); $q->addQuarter()) {
            $boundaries[] = $q->copy();
        }

        if ($boundaries && $index % 20 === 7) {
            $moment = $boundaries[array_rand($boundaries)]->copy()->addSeconds(mt_rand(-7200, 7200));

            if ($moment->between($from, $to)) {
                return $moment;
            }
        }

        return Carbon::createFromTimestamp(mt_rand($from->getTimestamp(), $to->getTimestamp()), self::TIMEZONE);
    }

    /**
     * Write the payment_transactions and membership_payments rows the real
     * webhook would leave behind. Returns the membership payment's columns.
     */
    private function insertPayment(array $plan, Carbon $paidAt): array
    {
        $reference = self::PREFIX.Str::uuid();
        $amountKobo = (int) round($plan['amount_naira'] * 100);
        $feeKobo = self::simulatedPaystackFee($amountKobo);
        $paidAtUtc = $paidAt->copy()->utc()->format('Y-m-d\TH:i:s.000\Z');

        $meta = [
            'payment_type' => 'membership',
            'membership_fee_id' => $plan['fee']->id,
            'donation_purpose' => null,
            'anonymous' => null,
            'organisation_id' => $plan['organisation_id'],
            'red_cross_unit_id' => $plan['red_cross_unit_id'],
            'id_card_included' => $plan['id_card_included'],
            'simulated' => true,
        ];

        // Shaped like Paystack's GET /transaction/verify response.
        $verified = [
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'status' => 'success',
                'reference' => $reference,
                'amount' => $amountKobo,
                'fees' => $feeKobo,
                'currency' => 'NGN',
                'channel' => 'card',
                'paid_at' => $paidAtUtc,
                'created_at' => $paidAt->copy()->subMinutes(mt_rand(1, 10))->utc()->format('Y-m-d\TH:i:s.000\Z'),
                'gateway_response' => 'Successful',
                'metadata' => $meta,
            ],
        ];

        $branchId = IncomeDistributionCalculator::resolveBranchId($plan['branch_id'], $plan['division_id']);
        $distribution = IncomeDistributionCalculator::paymentColumns($amountKobo, $feeKobo, $paidAt, $branchId, $plan['division_id'], $plan['organisation_id'] !== null);
        $paymentDate = $paidAt->copy()->startOfDay();

        $row = [
            'user_id' => $plan['user_id'],
            'organisation_id' => $plan['organisation_id'],
            'red_cross_unit_id' => $plan['red_cross_unit_id'],
            'payment_date' => $paymentDate->toDateString(),
            'expiry_date' => $paymentDate->copy()->addYears($plan['fee']->validity_years)->toDateString(),
            'membership_fee_id' => $plan['fee']->id,
            'is_deleted' => false,
            'submitted_at' => $paidAt->format('Y-m-d H:i:s'),
            'submission_name' => 'Simulated Paystack payment',
            'submitted_by_user_id' => $plan['user_id'],
            'branch_id' => $branchId,
            'division_id' => $plan['division_id'],
            'id_card_included' => $plan['id_card_included'],
            'payment_channel' => 'paystack',
            'gateway_reference' => $reference,
            'gateway_response' => json_encode($verified),
            'approval_status' => MembershipPayment::APPROVED,
            'decided_by_user_id' => null,
            'decided_at' => $paidAt->format('Y-m-d H:i:s'),
            'created_at' => $paidAt->format('Y-m-d H:i:s'),
            'updated_at' => $paidAt->format('Y-m-d H:i:s'),
        ] + array_merge($distribution, ['paid_at' => $paidAt->format('Y-m-d H:i:s')]);

        $paymentId = DB::table('membership_payments')->insertGetId($row);

        DB::table('payment_transactions')->insert([
            'user_id' => $plan['user_id'],
            'organisation_id' => $plan['organisation_id'],
            'red_cross_unit_id' => $plan['red_cross_unit_id'],
            'payable_type' => 'membership_payment',
            'reference' => $reference,
            'amount' => $amountKobo,
            'status' => 'success',
            'meta' => json_encode($meta),
            'raw_payload' => json_encode($verified),
            'membership_payment_id' => $paymentId,
            'created_at' => $paidAt->format('Y-m-d H:i:s'),
            'updated_at' => $paidAt->format('Y-m-d H:i:s'),
        ]);

        return $row;
    }
}
