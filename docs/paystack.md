# Paystack Integration

## 1. Overview

This integration lets members and organisation contacts pay for their own
membership renewal or make a donation online via Paystack, instead of a
staff member entering the payment manually. It covers both personal
payments (a member paying for themselves) and organisation-sponsored
payments (a linked contact paying on behalf of one of their organisations).

The one architectural decision that shapes everything else: **a
gateway-confirmed payment is auto-approved, with no human four-eyes step.**
The existing `Donation`/`MembershipPayment` approval workflow
(`Approvable::approve()`/`reject()`) assumes a staff member is deciding —
there is no such person in an online self-service payment. A new method,
`Approvable::markApprovedViaGateway()`, was added as a separate path rather
than routing gateway payments through `approve()` with a synthetic "system"
user, which would misrepresent what actually happened in the audit trail.

## 2. What's implemented

### Schema additions
- `database/migrations/2026_07_29_120000_add_paystack_fields_to_payment_tables.php`
  — adds `payment_channel` (default `'manual'`), `gateway_reference`
  (unique), and `gateway_response` (json) to both `donations` and
  `membership_payments`.
- `database/migrations/2026_07_29_120001_create_payment_transactions_table.php`
  — creates `payment_transactions`, which tracks a Paystack transaction
  end-to-end from initiation through fulfilment (`donation_id`/
  `membership_payment_id` are only populated once fulfilled).

### PaystackService (`app/Services/PaystackService.php`)
Thin wrapper around Paystack's Transactions API. Takes no constructor
arguments (reads `config/paystack.php` directly), so it's trivial to
instantiate in tests and fake with `Http::fake()`. Three public methods:
- `initializeTransaction()` — starts a transaction, returns Paystack's
  response (including the `authorization_url` to redirect the payer to).
- `verifyTransaction()` — fetches Paystack's own record of a transaction by
  reference; does not decide success/failure itself, just returns what
  Paystack says.
- `verifyWebhookSignature()` — confirms a webhook payload genuinely came
  from Paystack. Paystack signs the raw request body with HMAC SHA512 using
  the account's **secret key** (there is no separate webhook secret).

### Approvable::markApprovedViaGateway() (`app/Models/Concerns/Approvable.php`)
Auto-approves a pending record with no decider. Differences from
`approve()`:
- `decided_by_user_id` is left `null` — combined with the payable record's
  own `payment_channel` column, this is what distinguishes a gateway
  auto-approval from a staff approval later.
- The dormant → active and pending_engagement → active lifecycle lifts are
  replicated identically to `approve()`.
- There is **no archived-member branch**, unlike `approve()`. This is
  deliberate, not a gap: an archived member is rejected upstream at payment
  *initiation* (see `PaystackPaymentController::initiate()` below), so no
  `PaymentTransaction` — and therefore no webhook fulfilment — should ever
  be able to reach this method for an archived member in the first place.

### Routes
- `routes/web.php` — `/make-payment` (`show`, `initiate`, `callback`),
  authenticated but with no `can:...` permission gate (this is member
  self-service, not a staff action).
- `/webhooks/paystack` — public, no `auth` middleware (Paystack's servers
  call it directly). **CSRF-exempted** in `bootstrap/app.php`
  (`validateCsrfTokens(except: ['webhooks/paystack'])`) — the only route in
  this app that carries that exemption, since Paystack cannot supply a CSRF
  token.

### PaystackPaymentController (`app/Http/Controllers/PaystackPaymentController.php`)
- `show()` — the "make a payment" landing page; assembles the payer's
  linked organisations and the personal/organisation membership fee lists.
  Data assembly only, no payment logic.
- `initiate()` — validates the request, then runs three guards before
  starting a Paystack transaction:
  - **org-linkage check**: an org-sponsored payment must belong to one of
    the payer's own linked organisations.
  - **missing-email check**: Paystack requires an email to start a
    transaction; some migrated accounts have none.
  - **archived-member block**: applies only to a *personal* membership
    payment. Org-sponsored payments deliberately skip this (an
    organisational payment is attributed to the organisation, not the
    contact person's own membership standing — mirrors
    `MembershipPaymentController::store()`'s existing exemption for
    org-sponsored payments).
  On success, creates a `PaymentTransaction` (status `initiated`) and
  redirects the payer to Paystack's hosted checkout.
- `callback()` — the browser redirect back from Paystack after checkout. A
  holding/status page only — **not** where the `Donation`/
  `MembershipPayment` record is created, since the payer can close the tab
  or lose connectivity before this ever fires.
- `webhook()` — the actual source of truth for fulfilment (see below).

### webhook() fulfilment logic
1. Verifies `x-paystack-signature` against the raw request body; rejects
   with 400 if missing/invalid.
2. Ignores every event except `charge.success` (Paystack sends many other
   event types this app never initiates, e.g. transfers, subscriptions).
3. **Idempotency**: looks up the matching `PaymentTransaction` by
   reference; if its `status` is already `success`, returns 200 immediately
   without creating a second record or re-running
   `markApprovedViaGateway()`. Paystack webhooks can and do fire more than
   once for the same event.
4. **Never trusts the webhook body's own claimed status/amount** — calls
   `PaystackService::verifyTransaction()` to re-confirm directly against
   Paystack's API before doing anything, and checks the verified amount
   matches what was charged.
5. On a verified match: creates the `Donation`/`MembershipPayment` record
   inside a DB transaction, calls `markApprovedViaGateway()` on it, and
   marks the `PaymentTransaction` `success`.
   - `branch_id`/`division_id` are derived from the **payer's own**
     branch/division for a personal payment, or the **organisation's**
     branch for an org-sponsored one. `division_id` is always `null` for
     an org-sponsored payment — `Organisation` has no `division_id` column
     of its own (`Organisation` → `Branch` only).
6. On a mismatch or non-success verified status: marks the
   `PaymentTransaction` `failed` and logs a warning — no record is created.
7. Any uncaught exception during fulfilment is logged as an error and the
   handler still returns 200. Returning a non-2xx would make Paystack retry
   the same failing webhook indefinitely; a human is expected to catch this
   via log review instead.

### Views
- `resources/views/make-payment/show.blade.php` — the payment form:
  payment-type toggle (membership/donation), a "paying as" chooser (self vs.
  a linked organisation) shown only if the payer has any, and separate
  personal/organisation membership-fee `<select>` lists (only one is
  enabled/submitted at a time, controlled by inline JS).
- `resources/views/make-payment/callback.blade.php` — the post-checkout
  status page; renders one of four states (success, still-confirming,
  failed/abandoned, or reference-not-found) based on the matching
  `PaymentTransaction`'s status.

### Anonymous donations
The donation section of `make-payment/show.blade.php` includes an
"anonymous" checkbox, carried through `initiate()`'s `meta` array and
written to `Donation.anonymous` at webhook fulfilment. "Anonymous" means the
donation is not *publicly* attributed — the donor's name is suppressed (via
`Donation::getDonorFullNameAttribute()`) in the staff donations list
(`donations/index.blade.php`) and in the National/Branch Donations Report
breakdown (`reports/donations/breakdown.blade.php`). It does **not** mean
hidden from Red Cross staff entirely: the staff donation detail page
(`donations/show.blade.php`) still allows drilling through to the donor's
profile via the DB-reference link, regardless of the flag — this is
intentional, not a gap, since staff need to be able to identify a donor
internally for reconciliation, audit, or correspondence purposes even when
the donor has opted out of public attribution. Org-sponsored donations
always show the organisation's name regardless of the `anonymous` flag,
since that's the organisation's own public identity rather than a personal
one.

### profile/show.blade.php lifecycle CTAs
The three lifecycle-status banners on the profile page (no active
membership, expired membership, expiring-soon membership) now link to
`/make-payment` instead of showing a placeholder. Each links to
`route('make-payment.show', ['payment_type' => 'membership'])` and uses
button text matched to its state: "Make a Payment" (no active membership),
"Renew Membership" (expired), "Renew Early" (expiring soon).

### Organisation-sponsored payments (profile/organisation.blade.php)
- **`/make-payment` organisation locking** — `PaystackPaymentController::show()`
  now also reads `?organisation_id=X`, alongside the existing
  `?payment_type=Y` lock. When `organisation_id` is present, non-blank, and
  belongs to one of the logged-in user's own linked organisations (reusing
  `initiate()`'s exact linkage check —
  `$user->organisations()->where('organisations.id', $organisationId)->exists()`
  — rather than a separate check), the resolved `Organisation` model is
  passed to the view as `$lockedOrganisation` and the form locks to it: the
  "Paying as" section is hidden entirely (no radios, no way to switch to
  "pay for myself"), a hidden `organisation_id` input carries the value, and
  only the organisation-type membership fee `<select>` renders (never the
  personal one, and not wired into the personal/org toggle JS at all —
  that JS only applies when the payer is choosing between the two). Invalid,
  missing, or not-actually-linked values degrade to "no lock", same
  graceful pattern as an invalid `payment_type`.
- **`profile/organisation.blade.php` Membership section** — now has the
  same four-subcase logic as profile/show's Member path (never paid /
  lapsed / valid / expiring soon), computed via `ProfileController::
  organisationProfile()` in the same shape as `show()`'s own
  `$personalCurrentPayment`/`$currentMembership` (a real `MembershipPayment`
  model, `->expiresSoon(28)`, `->days_until_expiry`), just scoped to
  `$organisation->membershipPayments()` instead of the logged-in person's
  own payments. `$hasEverHadOrgPayment` mirrors `$hasEverHadPersonalPayment`
  the same way. There is no volunteer/archived/pending_engagement
  branching here — an organisation is always effectively on the "Member"
  path.
- **The email gate is on the *contact person*, not the organisation.**
  `$canPayOnline` on this page is `! blank(auth()->user()->email)` — the
  logged-in contact person's own email, since Paystack charges that
  person's email regardless of which organisation the payment is for. Both
  the Membership buttons/text and a new "Make a Donation" button in the
  Donations section are gated on it, with the same add-an-email-or-contact-
  your-branch fallback text used elsewhere in this integration.

### Test coverage
- `tests/Unit/Services/PaystackServiceTest.php` — `PaystackService`'s HTTP
  behaviour via `Http::fake()`: request shape for both API calls,
  `PaystackException` on non-2xx and on `status:false` bodies, and
  `verifyWebhookSignature()` correctness (valid signature, wrong key,
  tampered payload).
- `tests/Feature/Approval/GatewayApprovalTest.php` —
  `markApprovedViaGateway()` at the model level: approves without a
  decider, no-ops on already-approved/rejected records, and the
  dormant/pending_engagement lifecycle lifts (including Donation's
  pending_engagement opt-out).
- `tests/Feature/Paystack/PaystackPaymentControllerTest.php` — full
  controller coverage: `show()` rendering, every `initiate()` guard and
  both success paths (donation, personal/org membership), `callback()`
  for known/unknown references, and `webhook()` (signature rejection,
  event filtering, idempotency, successful fulfilment for personal and
  org-sponsored payments, amount-mismatch/non-success handling, and the
  exception-swallowing 200 path). Also covers the `organisation_id` lock
  on `show()`: locked rendering (no "Paying as" radios, only the org fee
  list, correct hidden field), an organisation the user isn't linked to
  degrading to unlocked, and both pre-existing unlocked/
  payment-type-only-locked paths confirmed unaffected.
- `ProfileController::organisationProfile()`'s Membership/Donations CTA
  logic was verified via a temporary test (not committed) covering all 8
  combinations — the four subcases (never paid, lapsed, valid, expiring
  soon) crossed with both contact-person-email states (present/blank) —
  plus the donation button and its route params. Consider adding a
  permanent test file for this controller if it doesn't have one yet, to
  guard against regressions the same way `PaystackPaymentControllerTest`
  does for the main flow.

## 3. Known gaps / deliberately deferred

These are decisions made to keep this piece of work scoped, not oversights:

- **No proactive alerting on amount-mismatch or verification failure.**
  Both are logged (`Log::warning`/`Log::error`) but nothing pages anyone.
  For the Nigeria mission, this means relying on manual log review.
  Revisit post-launch.
- **No Paystack Inline JS popup.** The flow uses a server-side redirect to
  Paystack's own hosted checkout page (`authorization_url`), not the
  in-page JS popup. Simpler and CSP-friendlier, at the cost of a full page
  redirect round-trip.
- **No auto-refresh/polling on the callback status page.** If the webhook
  hasn't fired yet when the payer lands back on `/make-payment/callback`,
  they see a "still being confirmed" message and must manually reload —
  there's no polling or websocket push.
- **Membership fee filtering now differs between the two forms.** The new
  self-service form (`make-payment/show.blade.php`) correctly splits fees
  by `for_organizations`. The pre-existing staff-facing
  `MembershipPaymentController::create()` form was **not touched** and
  still shows every active fee unfiltered, regardless of
  `for_organizations`. Out of scope for this work, but worth knowing the
  two forms now behave differently.
- **Pre-existing `submission_name` bug found, not fixed.**
  `MembershipPaymentController::store()` sets
  `'submission_name' => auth()->user()->name` — `User` has no `name`
  attribute or accessor (only `first_name`/`last_name`/`full_name`), so
  this silently writes `null` on every staff-entered membership payment.
  Found while cross-referencing this integration's own (correct) use of
  `$transaction->user->full_name`. Flagged here so it isn't mistaken for
  something this integration caused — it predates this work and was left
  alone as out of scope.
- **Org multi-payer conflict — deliberately not built.** The code comment
  in `profile/show.blade.php` (near the expiring-soon banner) describes a
  real scenario: multiple people can be linked to the same organisation, all
  get a renewal reminder, and nothing currently stops more than one of them
  from paying the same org's fee. This is a genuine, not-yet-designed
  feature, not an oversight. Deferred as low-risk — worst case is a double
  payment requiring manual reconciliation/refund, not a broken flow — in
  favor of prioritizing live Paystack sandbox testing during the Nigeria
  mission.

## 4. What remains before this can go live in Nigeria

- [ ] Obtain a real Paystack account: test-mode keys first, then live keys
      once KYC is complete.
- [ ] Populate `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY`, and
      `PAYSTACK_CURRENCY` in the production `.env`.
- [ ] Configure the live webhook URL in the Paystack dashboard, pointing to
      `https://{production-domain}/webhooks/paystack`.
- [ ] End-to-end test against Paystack's real sandbox/test mode — not just
      the `Http::fake()` unit tests. Run one real test transaction and one
      real test webhook delivery, and confirm signature verification works
      against Paystack's actual signing (not just the mocked format used in
      tests).
- [ ] Decide on the amount-mismatch/verification-failure alerting gap
      (§3) before or shortly after go-live.
- [ ] Confirm NRCS's settlement/payout bank account is fully configured in
      the live Paystack dashboard — flagged early on as the slowest step of
      KYC.
- [ ] Spot-check that the callback URL (`config('app.url')`) resolves to
      the production VPS domain, not a dev/staging URL.
- [ ] Consider adding a Paystack logo/"Secured by Paystack" mark on
      `make-payment/show.blade.php`, near the "Proceed to Payment" button.
      Not implemented yet — decide once real Paystack account access is
      available, since (a) Paystack's merchant terms may specify required
      branding/placement that should be confirmed directly rather than
      guessed, and (b) the actual redirect to Paystack's hosted checkout
      page already carries their branding once the user gets there, so
      this is purely a trust-signal addition on our own page beforehand,
      not a functional requirement.
