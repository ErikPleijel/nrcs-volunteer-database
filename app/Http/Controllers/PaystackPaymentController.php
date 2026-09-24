<?php

namespace App\Http\Controllers;

use App\Exceptions\PaystackException;
use App\Models\Donation;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\PaymentTransaction;
use App\Models\RedCrossUnit;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Member/org-contact self-service Paystack payments (donations and personal
 * or org-sponsored membership fees). Distinct from MembershipPaymentController
 * / DonationController, which are the staff-facing manual-entry + four-eyes
 * approval flow — this controller is the online-payment counterpart, gated
 * only on being an authenticated person (see show()/routes), not on any
 * add_payments/add_donations permission.
 */
class PaystackPaymentController extends Controller
{
    /**
     * The "make a payment" landing page: lets the payer choose donation vs.
     * membership, and (if applicable) which of their linked organisations
     * they're paying on behalf of. Data assembly only — no payment logic.
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        $organisations = $user->organisations;

        // Set by the profile page's CTA buttons (?payment_type=membership /
        // ?payment_type=donation) so arriving via a specific button locks the
        // form to that single payment type instead of showing the toggle.
        // Anything other than these two exact values (missing, or garbage
        // from a hand-edited URL) is treated as "no lock" rather than an
        // error — this is a query param, not validated user input.
        $lockedPaymentType = $request->query('payment_type');
        if (! in_array($lockedPaymentType, ['donation', 'membership'], true)) {
            $lockedPaymentType = null;
        }

        // Set by profile/organisation's CTA via ?organisation_id=X, locking
        // the form to that organisation — hides the "Paying as" toggle
        // entirely and shows only the organisation fee list. Reuses the
        // exact same linkage check as initiate()'s own guard
        // ($user->organisations()->where('organisations.id', $organisationId)->exists())
        // so a payer can never lock the page to an organisation they aren't
        // actually linked to; anything invalid/missing/unlinked degrades to
        // "no lock", same graceful pattern as $lockedPaymentType above. The
        // model is resolved from the already-loaded $organisations
        // collection rather than a second query.
        $organisationId = $request->query('organisation_id');
        $lockedOrganisation = null;
        if (filled($organisationId) && $user->organisations()->where('organisations.id', $organisationId)->exists()) {
            $lockedOrganisation = $organisations->firstWhere('id', $organisationId);
        }

        // Set by the RCU payment page's CTA via ?red_cross_unit_id=X, locking
        // the form to that unit's annual fee. Unlike the organisation lock this
        // is the ONLY way to reach an RCU payment (there's no "Paying as"
        // toggle for units), so a bad lock is an error rather than a silent
        // fallback to the personal form: 403 unless the payer leads the unit.
        // An archived unit, or a payer with no email, gets the locked banner
        // with an explanation in place of the form (see $rcuBlockedReason).
        $redCrossUnitId = $request->query('red_cross_unit_id');
        $lockedRedCrossUnit = null;
        $rcuBlockedReason = null;
        if (filled($redCrossUnitId)) {
            abort_if(filled($organisationId), 400, 'A payment can be for an organisation or a Red Cross Unit, not both.');

            $lockedRedCrossUnit = RedCrossUnit::with('division.branch')->findOrFail($redCrossUnitId);

            abort_unless($lockedRedCrossUnit->isLedBy($user), 403, 'Only the team leader or assistant team leader of this Red Cross Unit can pay its annual fee.');

            // The unit's annual fee is a membership payment only.
            $lockedPaymentType = 'membership';

            if (! $lockedRedCrossUnit->is_active) {
                $rcuBlockedReason = 'This Red Cross Unit is archived, so its annual fee can no longer be paid. Please contact your branch if you think this is a mistake.';
            } elseif (blank($user->email)) {
                // Same copy as profile/organisation.blade.php's no-email CTA.
                $rcuBlockedReason = 'Add an email address to your profile to pay online, or contact your branch to pay directly.';
            }
        }

        // for_organizations, for_red_cross_units and is_volunteer_fee are all plain NOT NULL
        // booleans (default false) — no nullable/legacy-null case to account
        // for, so a straight true/false split is exact, not an approximation.
        // Volunteer fees are excluded here: volunteers pay their branch
        // directly, not through this self-service online flow.
        $personalMembershipFees = MembershipFee::active()->forPersons()->where('is_volunteer_fee', false)->orderBy('validity_years')->orderBy('amount')->get();
        $organisationMembershipFees = MembershipFee::active()->where('for_organizations', true)->where('is_volunteer_fee', false)->orderBy('validity_years')->orderBy('amount')->get();
        $rcuMembershipFees = MembershipFee::active()->forRedCrossUnits()->orderBy('validity_years')->orderBy('amount')->get();

        return view('make-payment.show', [
            'user' => $user,
            'organisations' => $organisations,
            'personalMembershipFees' => $personalMembershipFees,
            'organisationMembershipFees' => $organisationMembershipFees,
            'rcuMembershipFees' => $rcuMembershipFees,
            'lockedPaymentType' => $lockedPaymentType,
            'lockedOrganisation' => $lockedOrganisation,
            'lockedRedCrossUnit' => $lockedRedCrossUnit,
            'rcuBlockedReason' => $rcuBlockedReason,
        ]);
    }

    /**
     * Validate the requested payment, run the guards below, then start a
     * Paystack transaction and redirect the payer to Paystack's checkout.
     */
    public function initiate(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'payment_type' => ['required', 'in:donation,membership'],
            'amount' => ['nullable', 'required_if:payment_type,donation', 'numeric', 'min:1'],
            'organisation_id' => ['nullable', 'exists:organisations,id'],
            'red_cross_unit_id' => ['nullable', 'exists:red_cross_units,id', 'prohibits:organisation_id'],
            'membership_fee_id' => ['nullable', 'required_if:payment_type,membership', 'exists:membership_fees,id'],
        ], [
            'red_cross_unit_id.prohibits' => 'A payment can be for an organisation or a Red Cross Unit, not both.',
        ]);

        $organisationId = $validated['organisation_id'] ?? null;
        $isOrgPayment = $organisationId !== null;
        $redCrossUnitId = $validated['red_cross_unit_id'] ?? null;
        $isRcuPayment = $redCrossUnitId !== null;

        // Guards for an RCU annual fee payment, re-checked here rather than
        // trusted from show() — the form's hidden red_cross_unit_id can be
        // hand-crafted just like organisation_id below.
        if ($isRcuPayment) {
            $redCrossUnit = RedCrossUnit::findOrFail($redCrossUnitId);

            if (! $redCrossUnit->isLedBy($user)) {
                return back()->with('error', 'Only the team leader or assistant team leader of this Red Cross Unit can pay its annual fee.');
            }

            if (! $redCrossUnit->is_active) {
                return back()->with('error', 'This Red Cross Unit is archived, so its annual fee can no longer be paid.');
            }

            if ($validated['payment_type'] !== 'membership') {
                return back()->with('error', 'Only the annual fee can be paid on behalf of a Red Cross Unit.');
            }
        }

        // RCU fees are only payable on behalf of a unit, and a unit only pays
        // RCU fees — otherwise an RCU fee paid "as myself" would count as the
        // payer's own personal membership.
        if ($validated['payment_type'] === 'membership') {
            $isRcuFee = (bool) MembershipFee::whereKey($validated['membership_fee_id'])->value('for_red_cross_units');

            if ($isRcuFee !== $isRcuPayment) {
                return back()->with('error', 'That fee cannot be used for this payment.');
            }
        }

        // Guard: an org-sponsored payment must actually belong to one of the
        // payer's own linked organisations — this is member self-service (no
        // add_payments/add_donations permission gate on the route), so unlike
        // the staff-facing controllers there's nothing else stopping an
        // authenticated user from posting an arbitrary organisation_id.
        if ($isOrgPayment && ! $user->organisations()->where('organisations.id', $organisationId)->exists()) {
            return back()->with('error', 'You are not linked to that organisation.');
        }

        // Paystack requires a valid email to initialize a transaction; some
        // migrated accounts have none (see profile/show.blade.php's own
        // "email missing" banner) — fail fast with a clear message instead of
        // creating a PaymentTransaction row for a call that can't succeed.
        if (blank($user->email)) {
            return back()->with('error', 'Please add an email address to your profile before making a payment.');
        }

        // THE ARCHIVED-MEMBER GUARD: applies only to a personal membership
        // payment. Org-sponsored and RCU payments deliberately skip individual
        // lifecycle checks — MembershipPaymentController::store() already
        // exempts $isOrgPayment from the fee/RCU validation on the same
        // reasoning (these payments are attributed to the organisation or
        // unit, not the payer's own membership standing).
        if ($validated['payment_type'] === 'membership' && ! $isOrgPayment && ! $isRcuPayment && $user->lifecycle_status === 'archived') {
            return back()->with('error', 'Your account is archived. Please contact your branch or Red Cross Unit directly to renew your membership.');
        }

        if ($validated['payment_type'] === 'membership') {
            $fee = MembershipFee::findOrFail($validated['membership_fee_id']);
            $amountNaira = (float) $fee->amount;
        } else {
            $amountNaira = (float) $validated['amount'];
        }

        $amountInKobo = (int) round($amountNaira * 100);

        $reference = 'PSK-'.(string) Str::uuid();

        $meta = [
            'payment_type' => $validated['payment_type'],
            'membership_fee_id' => $validated['membership_fee_id'] ?? null,
            'donation_purpose' => $validated['payment_type'] === 'donation' ? $request->input('purpose') : null,
            'anonymous' => $validated['payment_type'] === 'donation' ? $request->boolean('anonymous') : null,
            'organisation_id' => $organisationId,
            'red_cross_unit_id' => $redCrossUnitId,
        ];

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'organisation_id' => $organisationId,
            'red_cross_unit_id' => $redCrossUnitId,
            'payable_type' => $validated['payment_type'] === 'membership' ? 'membership_payment' : 'donation',
            'reference' => $reference,
            'amount' => $amountInKobo,
            'status' => 'initiated',
            'meta' => $meta,
        ]);

        try {
            $response = (new PaystackService)->initializeTransaction(
                email: $user->email,
                amountInKobo: $amountInKobo,
                reference: $reference,
                metadata: $meta,
            );
        } catch (PaystackException $e) {
            Log::error('Paystack initializeTransaction failed', [
                'payment_transaction_id' => $transaction->id,
                'reference' => $reference,
                'user_id' => $user->id,
                'paystack_message' => $e->getMessage(),
            ]);

            return back()->with('error', 'We could not start your payment right now. Please try again shortly.');
        }

        return redirect()->away($response['data']['authorization_url'] ?? '');
    }

    /**
     * Browser redirect back from Paystack after checkout. This is a holding
     * page only — NOT where the Donation/MembershipPayment record gets
     * created. The payer can close the tab or lose connectivity before this
     * ever fires, so it cannot be the trigger for creating financial
     * records; the webhook (server-to-server, guaranteed by Paystack to
     * retry) is the actual source of truth for fulfilment.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        $transaction = PaymentTransaction::where('reference', $reference)->first();

        return view('make-payment.callback', [
            'transaction' => $transaction,
            'reference' => $reference,
        ]);
    }

    /**
     * Paystack server-to-server webhook. Must respond fast with a 2xx once
     * the signature checks out — Paystack retries on anything else.
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        $isValid = $signature !== null && (new PaystackService)->verifyWebhookSignature($payload, $signature);

        if (! $isValid) {
            Log::warning('Paystack webhook: invalid or missing signature', [
                'ip' => $request->ip(),
                'has_signature_header' => $signature !== null,
            ]);

            return response()->json(['status' => 'invalid signature'], 400);
        }

        // Paystack sends many event types beyond one-off checkout charges
        // (transfer.success/failed/reversed, subscription.create/disable,
        // customeridentification.success/failed, bank.transfer.rejected,
        // etc.) — this app only ever initiates payments via
        // initializeTransaction(), so charge.success is the only event that
        // can correspond to a transaction we started. Other events' own
        // "reference"/"amount" fields belong to a different Paystack object
        // (a transfer, a subscription, ...) and must not be looked up as if
        // they were one of ours.
        $event = $request->input('event');

        if ($event !== 'charge.success') {
            Log::info('Paystack webhook: ignoring non-charge.success event', ['event' => $event]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $reference = $request->input('data.reference');

        $transaction = PaymentTransaction::where('reference', $reference)->first();

        if (! $transaction) {
            // Either a webhook for a transaction not initiated through this
            // app, or a reference mismatch — not something to retry into.
            Log::warning('Paystack webhook: no PaymentTransaction found for reference', [
                'reference' => $reference,
            ]);

            return response()->json(['status' => 'unknown reference'], 200);
        }

        // Idempotency guard: Paystack webhooks can and do fire more than
        // once for the same event. Once this transaction is already
        // fulfilled, every further delivery is a no-op — never create a
        // second Donation/MembershipPayment or re-run markApprovedViaGateway().
        if ($transaction->status === 'success') {
            return response()->json(['status' => 'already processed'], 200);
        }

        try {
            // Never trust the webhook body's own claimed status/amount —
            // verify directly against Paystack's API, the authoritative
            // source of whether money actually moved (current Paystack
            // guidance: confirm via the verify endpoint before delivering
            // value, and check the amount matches before doing so).
            $verified = (new PaystackService)->verifyTransaction($reference);

            $verifiedStatus = $verified['data']['status'] ?? null;
            $verifiedAmount = (int) ($verified['data']['amount'] ?? 0);

            if ($verifiedStatus === 'success' && $verifiedAmount === (int) $transaction->amount) {
                DB::transaction(function () use ($transaction, $verified) {
                    $meta = $transaction->meta ?? [];

                    // Personal payment: scope to the payer's own branch/division.
                    // Org-sponsored payment: scope to the organisation's branch —
                    // organisations have no division of their own (Organisation ->
                    // Branch only, confirmed against every organisations-table
                    // migration), so division_id stays null for these.
                    // RCU payment: scope to the unit's own division and branch.
                    if ($transaction->red_cross_unit_id !== null) {
                        $branchId = $transaction->redCrossUnit->division?->branch_id;
                        $divisionId = $transaction->redCrossUnit->division_id;
                    } elseif ($transaction->organisation_id !== null) {
                        $branchId = $transaction->organisation->branch_id;
                        $divisionId = null;
                    } else {
                        $branchId = $transaction->user->branch_id;
                        $divisionId = $transaction->user->division_id;
                    }

                    if ($transaction->payable_type === 'membership_payment') {
                        $fee = MembershipFee::findOrFail($meta['membership_fee_id']);
                        $paymentDate = Carbon::today();
                        // Identical formula to MembershipPaymentController::store().
                        $expiryDate = $paymentDate->copy()->addYears($fee->validity_years);

                        $record = MembershipPayment::create([
                            'user_id' => $transaction->user_id,
                            'organisation_id' => $transaction->organisation_id,
                            'red_cross_unit_id' => $transaction->red_cross_unit_id,
                            'payment_date' => $paymentDate,
                            'expiry_date' => $expiryDate,
                            'membership_fee_id' => $fee->id,
                            'submission_name' => $transaction->user->full_name,
                            'submitted_by_user_id' => $transaction->user_id,
                            'submitted_at' => now(),
                            'branch_id' => $branchId,
                            'division_id' => $divisionId,
                            'is_deleted' => false,
                            'payment_channel' => 'paystack',
                            'gateway_reference' => $transaction->reference,
                            'gateway_response' => $verified,
                        ]);
                    } else {
                        $record = Donation::create([
                            'user_id' => $transaction->user_id,
                            'organisation_id' => $transaction->organisation_id,
                            // donations.amount is stored in whole Naira, unlike
                            // PaymentTransaction.amount (kobo) — convert back.
                            'amount' => (int) round($transaction->amount / 100),
                            'date_donation' => Carbon::today(),
                            'in_kind_donation' => false,
                            'donation_item' => 'Naira',
                            'purpose' => $meta['donation_purpose'] ?? null,
                            'anonymous' => $meta['anonymous'] ?? false,
                            'submission_name' => $transaction->user->full_name,
                            'entered_by_user_id' => $transaction->user_id,
                            'branch_id' => $branchId,
                            'division_id' => $divisionId,
                            'is_deleted' => false,
                            'payment_channel' => 'paystack',
                            'gateway_reference' => $transaction->reference,
                            'gateway_response' => $verified,
                        ]);
                    }

                    // approval_status isn't fillable on either model, so the
                    // freshly created $record has it as null in memory (the
                    // 'pending' value only exists as a DB column default) —
                    // without this refresh, markApprovedViaGateway()'s pending
                    // check sees null, not 'pending', and silently no-ops.
                    $record->refresh();

                    $record->markApprovedViaGateway();

                    $transaction->forceFill([
                        'status' => 'success',
                        'donation_id' => $record instanceof Donation ? $record->id : null,
                        'membership_payment_id' => $record instanceof MembershipPayment ? $record->id : null,
                        'raw_payload' => $verified,
                    ])->save();
                });

                return response()->json(['status' => 'processed'], 200);
            }

            // Either Paystack itself says the charge didn't succeed, or the
            // verified amount doesn't match what we charged for — both are a
            // hard failure worth a loud log entry, not a silent proceed.
            Log::warning('Paystack webhook: verification failed or amount mismatch — not fulfilling', [
                'reference' => $reference,
                'payment_transaction_id' => $transaction->id,
                'verified_status' => $verifiedStatus,
                'expected_amount' => (int) $transaction->amount,
                'verified_amount' => $verifiedAmount,
            ]);

            $transaction->forceFill([
                'status' => 'failed',
                'raw_payload' => $verified,
            ])->save();
        } catch (\Throwable $e) {
            // An uncaught exception here is OUR bug (or a transient Paystack
            // API blip), not something Paystack should retry into. Returning
            // a non-2xx would make Paystack retry this webhook indefinitely,
            // hammering the same exception forever. Log loudly and return
            // 200 anyway so a human catches it via logs/monitoring instead —
            // do not "fix" this into a 500.
            Log::error('Paystack webhook: unhandled exception during fulfilment', [
                'reference' => $reference,
                'payment_transaction_id' => $transaction->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['status' => 'received'], 200);
    }
}
