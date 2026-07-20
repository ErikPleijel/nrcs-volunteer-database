

# Architecture & Policy Decisions — NRCS Volunteer Database

This file records deliberate decisions, including things we chose **not** to fix.
When a behaviour looks odd, check here before re-investigating.

Format: date · decision · rationale · accepted consequences.

---

## 2026-06-10 — Canonical "member" definition

**Decision:** A *member* is a user with `lifecycle_status` in `['active', 'dormant']`,
`red_cross_unit_id IS NULL`, and at least one valid membership payment.
Implemented as `User::scopeMembers()`. The structural part (payment + no RC unit,
**no** lifecycle condition) lives in `User::scopeHasValidMembership()`.

**Rationale:** "Member" was previously computed in at least three divergent ways
(`MembershipPayment::valid()` joins, `currentMembershipPayment` whereHas,
ad-hoc lifecycle conditions), producing different totals on the dashboard,
in reports, and in user filters.

**Consequences / notes:**
- Dormant members count as members — dormancy means "needs a nudge", not "out of the system".
- `pending_engagement` and `archived` users are **excluded** from member statistics
  even if they hold a valid payment.
- `UserFilterService` (`person_type=member`, `is_member`, `membership_filter=members`)
  uses `hasValidMembership()`, NOT `members()`, so the lifecycle remains governed by
  the `archived_filter` dropdown. Filtering "Archived + Members" still works.
- `is_member` and `membership_filter=members` now also exclude unit-attached volunteers
  (previously a bug: volunteers with a valid payment passed as members).

---

## 2026-06-10 — Membership expiry boundary is date-based

**Decision:** A membership expiring today is valid **all of today**.
`User::currentMembershipPayment()` compares `expiry_date >= now()->toDateString()`,
matching `MembershipPayment::scopeValid()`.

**Rationale:** The relationship previously compared against a full datetime,
so a payment expiring "today" was already invalid one second after midnight,
while the payment-side scope said it was valid. Counts disagreed for anyone
expiring on the query day.

---

## 2026-06-10 — Canonical "volunteer" definition

**Decision:** A *volunteer* is a user with `lifecycle_status` in `['active', 'dormant']`
attached to an **active** Red Cross unit (`red_cross_unit_id` set, unit `is_active = true`).
Implemented as `User::scopeVolunteers()`.

**Consequences / notes:**
- Users in deactivated units do not count as volunteers.
- Dashboard volunteer trends are approximated via `assigned_rcu_date <= [date]`.
  People who have since **left** a unit cannot be counted historically, so past
  figures are slightly understated and growth trends slightly flattering.
  `assigned_rcu_date` is verified non-null for all current unit-attached users.

---

## 2026-06-10 — Dashboard trend comparisons are knowingly approximate

**Decision:** Month/year trend arrows on the dashboard compare today's canonical
counts against historical point-in-time reconstructions that use broader
definitions (no lifecycle filter — lifecycle status cannot be reconstructed
retroactively). We accept the mismatch for now.

**Planned fix:** A nightly cron/scheduler job writing to a `stats_snapshots`
table (snapshot_date · scope_level · scope_id · metric · value). Once running,
trends become snapshot-vs-snapshot under consistent definitions, and the
point-in-time reconstruction methods can be retired. **Not yet built.**

---

## 2026-06-10 — Restricted super-admin model

**Decision:** Super-admin is no longer all-powerful. The `Gate::before` bypass on
`is_super_admin` was removed. The `super-admin` role now carries exactly the
observer permission set plus `manage_roles_and_permissions` and
`authorize_national_db_administrator`. Its single purpose: appointing and
removing National Database Administrators via the Authorizations page.

**Rationale:** The three super-admin accounts (Secretary General + two others,
seeded from `.env`) are institutional safeguards, not working accounts. They
have placeholder profiles and must not create payments, donations, campaigns,
or edit records.

**Consequences / accepted, do not "fix":**
- Super-admin **cannot edit user records of admins** — `UserPolicy::update`
  blocks editing users who hold roles. Appointment via the Authorizations page
  does not require user-edit access, so this is acceptable by design.
- Super-admin cannot view or edit "My Profile" (redirected; nav link hidden) —
  the seeded accounts have no real profile data.
- Super-admin no longer accesses Campaign Purpose Settings. The old code checked
  `hasRole('super_admin')` (underscore — a role name that never existed); the
  `Gate::before` bypass had been masking this latent bug. The condition was removed.
- `User::getAssignableRoles()` no longer special-cases super-admin; everyone flows
  through the `authorize_*` permission → role mapping.
- The `is_super_admin` column remains, used for: instruction banners (dashboard +
  home page), profile blocking, and excluding these accounts from user listings,
  campaign audiences, and the dormant-archiving tool.
- After changing permission seeders, production requires:
  `db:seed PermissionsTableSeeder` → `db:seed SuperAdminSeeder` →
  `php artisan permission:cache-reset`.

---

## 2026-06-10 — Pre-deploy audit practice

**Decision:** Before significant production deploys, run read-only Claude Code
audits from saved prompt files:

1. **Authorization audit** — every route's middleware, every controller
   `authorize()` call, endpoints reachable with only `auth`, menu `@can`s without
   a matching server-side check. Menu-hiding is not security.
2. **Definition-consistency audit** — list every place core concepts
   (member, volunteer, dormant, active unit) are computed; flag divergences
   from the canonical scopes.
3. **Seeder/production sync audit** — what will re-running seeders change on
   the production database (roles and permissions especially).
4. **Query performance audit** — flag N+1 patterns and per-row queries in loops
   on report pages before staff use them at scale.

**Longer term:** thin feature-test layer asserting permission boundaries and
definition consistency (e.g. "observer cannot POST payments.store",
"super-admin sees exactly one assignable role"). To be built after the
Nigeria mission.

**Practice:** when an audit finding is deliberately *not* fixed, record it here.

---

## 2026-06-10 — Statistics snapshot system

**Decision:** Nightly snapshots of core statistics in a `stats_snapshots` table,
one row per `(snapshot_date, branch_id, division_id)`, wide schema (one column
per metric). Captured: lifecycle counts, members (total/men/women, canonical
`User::members()`), volunteers (total/men/women, canonical `User::volunteers()`),
and `dormant_avg_days_inactive` as an archive-hygiene indicator. Scheduled daily
at 02:00 via `stats:snapshot`; idempotent (`updateOrCreate` on the unique key).
Super-admin accounts excluded.

**Key schema rules:**
- Lifecycle columns are **nullable**: NULL means "not known" (backfilled rows),
  never 0. Trend charts must treat NULL as missing, not zero.
- `division_id` and `branch_id` nullable as a safety net for super-admins and
  any unassigned users, so branch/national sums always reconcile.
- `is_backfilled` flag distinguishes approximated historical rows.

**Backfill (`stats:backfill`):** one-time, monthly granularity (1st of each
month), `--from` chosen at runtime. Members reconstructed via the same
point-in-time payment logic as the existing trend charts (deliberately, for
consistency); volunteers approximated via `assigned_rcu_date` (leavers
invisible — past figures understated). Backfill NEVER overwrites a
non-backfilled (live) row.

**Dashboard wiring:** main Members/Volunteers figures stay live (canonical
scopes); trend arrows compare live-now against the nearest snapshot **on or
before** the target date. No snapshot available → no arrow shown (honest
absence, not zero). The retired approximations: `assigned_rcu_date` trend
queries and all-lifecycle point-in-time member reconstructions on the dashboard.

---

## 2026-06-10 — Activating the scheduler cron is a deployment event

**Decision:** Adding the `schedule:run` crontab line on a server activates
EVERY registered scheduled command (discovered when `campaigns:send --batch=50`
began firing on the test VPS), not just the one being deployed.

**Required checks before/when activating the scheduler on any server:**
1. `php artisan schedule:list` — review every entry; anything that sends,
   deletes, or archives must be consciously approved for that environment.
2. Confirm mail/SMS configuration state: on test servers set `MAIL_MAILER=log`
   and neutralize SMS credentials, then `php artisan config:clear`, so campaign
   sends cannot reach real people (the database contains real addresses
   migrated from the old system).
3. Check `messaging_recipients` for pending rows before letting the send
   command run unattended.
4. Server timezone: scheduler times are server time (`timedatectl`). Decide
   once whether snapshots/sends key to UTC or Africa/Lagos
   (`config/app.php` timezone) — changing later shifts what "yesterday"
   means in the data. **Status: not yet decided.**

---

## 2026-06-10 — Image migration: filesystem copy, dual storage

**Decision:** Old-database photos/signatures are migrated by the
`images:migrate` command. On the VPS the old image directory is on the same
server, so the command copies via filesystem (source from
`IMAGE_MIGRATION_SOURCE` env / `--source=`); HTTP mode exists only for local
rehearsal. Web-optimized versions are generated with the same library and
settings as the existing upload flow. Signatures get an `original/` archive
copy in addition to `web/`, even though the current accessor reads only `web/`.
Idempotent: existing files are skipped, so the command is safely resumable.

**Parked follow-up:** once migration is verified on the VPS, remove the
remote-URL fallback from the User model accessors (`profile_photo_url`,
`original_profile_photo_url`, `signature_url`) so the app never reaches out
to the old server.

---

## 2026-06-11 — Division/branch "heat score"

**Decision:** Nightly `heat:recalculate` computes a 0–1 heat score per division and
per branch, stored on those tables (`heat_score`, `heat_computed_at`). Two equally
weighted factors, configurable in `config/heat.php`: hours-per-volunteer and
trainings-per-volunteer over a trailing window (default 6 months). Each factor is
normalized 0–1 relative to the busiest area BEFORE weighting, so equal weight is real
despite the factors' different natural scales.

**Consequences:**
- Heat is RELATIVE — a score reflects intensity compared to the current busiest
  area, so scores are not comparable across time (the leader moves). Acceptable for
  a "where is the energy now" map; would need fixed denominators to be a stable
  absolute measure.
- Per-volunteer (intensity), not raw totals — small, intensely active areas can
  score high; large coasting areas score low. By design.
- Branch heat is computed from the branch's own aggregated figures, NOT averaged
  from its divisions (averaging would distort via double normalization).
- Zero-volunteer areas score 0, never null after a run; null means "never computed".
- Window and weights are config-only; change without code edits. Record weight
  changes here.

---

## Tutorial system (Phase 1, 2026-06-11)

**Decision:** Three tutorial levels gated by `getAccessLevel()`: L1 all dashboard users,
L2 branch+national, L3 national only. Locked levels are VISIBLE on the dashboard
(grayed, padlock) — deliberate, so staff can see what training exists above them.

Lesson content lives as Blade views + `config/tutorials.php` registry, NOT in the
database (versioned in git, no authoring UI needed). Completion = user viewed the last
slide (no quiz); recorded in `tutorial_progress` (user_id × lesson_key, idempotent,
unique index). Audio is per-slide MP3 under `public/tutorials/audio/`, `preload="none"`
for bandwidth — files recorded later.

**Why not Intro.js:** AGPL licensing — incompatible with a proprietary deployment.
**Why not driver.js for Phase 1:** Driver.js page tours are Phase 2; the slide player
covers the content-delivery need first without the complexity of tour orchestration.

**Consequences:**
- Lesson content is code, not data — editing a lesson requires a deploy, not a DB
  edit. Acceptable for a small-team context; add an authoring UI if that changes.
- Progress is per lesson-key string; renaming a key loses old completion records.
  Treat lesson keys (`level1.welcome`) as stable identifiers once published.
- `x-cloak` is injected via `@once` in the player component, not in app.css —
  avoids touching the asset pipeline but means the style only exists on pages that
  include the player.


## Tutorial entry point + Level 0 (2026-06-11)
Dashboard now has a single "Tutorials" button under the Training section (replacing
the three per-level buttons), linking to a single /tutorials index that lists all
levels (0–3) and their lessons with completion ticks. Welcome moved from Level 1 to
a new Level 0 ("general/getting oriented"), gated like Level 1 (all dashboard users).
Lesson key renamed level1.welcome → level0.welcome; view moved to
tutorials/lessons/level0/welcome.blade.php. Locked levels remain visible-but-greyed
on the index (consistent with earlier decision). Old per-level route/page kept but
no longer linked.


## Tutorial pages served at /learn, assets at /tutorials (2026-06-11)
Route /tutorials collided with the real public/tutorials/ asset directory (Apache
mod_dir served the folder before Laravel saw the route → 404). Fixed by moving the
PAGES to /learn/* while keeping asset files under public/tutorials/. Route names
stay tutorials.* (so route() calls and asset() paths were unchanged). Rule: never
let an app route share a top-level path segment with a public/ directory. Do not
"fix" this class of problem with .htaccess DirectorySlash/rewrite hacks.

## Entry-form people search excludes super-admins (2026-06-13)
All create-form user searches (payments, and later donations/trainings/volunteering)
must exclude super-admins. Encoded as User::scopeSelectableForEntry() rather than
repeated per controller, so the rule lives in one place. NOTE: when combined with an
orWhere search group, the exclusion must be a top-level AND (outside the closure) —
placing it inside the orWhere chain silently fails (OR precedence).

## Entry-form people search excludes super-admins (2026-06-13)
All create-form user searches must exclude super-admins, via User::scopeSelectableForEntry()
(defined once, reused across payments/donations/trainings/volunteering). NOTE: when combined
with an orWhere search group, the exclusion must be top-level AND (outside the closure);
placing it inside the orWhere chain silently fails due to OR precedence.


## Volunteering log search = unit volunteers only (2026-06-13)
activities/create user search returns ONLY users in a Red Cross unit (red_cross_unit_id
NOT NULL) — the canonical volunteer definition — plus selectableForEntry() (no super-admins).
"Interested to volunteer" users are deliberately excluded: you can only log activity for
actual unit volunteers. Consequence: selected users always have a unit, so the assignment
block's "no unit & no TF" hidden-state is a rare safety net.

## Entry-form search marks archived users (2026-06-11)
Across create-form user searches (payments, activities; donations & trainings to follow),
archived users (lifecycle_status === 'archived') appear in results but render a red
"Archived" label instead of a Select button, so they can't be chosen. Requires
lifecycle_status in the controller search select(). Chosen over hiding them entirely so
admins can see the person exists but is archived. Pairs with selectableForEntry() (super-admin
exclusion) and, for activities, the volunteers-only (red_cross_unit_id NOT NULL) filter.


## Legacy role migration: order is load-bearing (2026-06-13)

The old DB assigns roles via two separate sources that must be applied in a
specific order, or roles get wiped / duplicated:

1. `seed:legacy-roles` — base role from users.legacy_role (uses syncRoles = replace)
2. `permissions:migrate-legacy-from-old` — upgrades branch_db_assistant → division
   role based on old Auth_* flags. Supersedes branch_db_assistant (Group A);
   SKIPS + logs branch_secretary / branch_db_administrator (Group B), since those
   senior roles must not be auto-demoted.
3. db:seed (SuperAdminSeeder) — re-asserts super-admin AFTER the above (syncRoles
   would otherwise wipe it).

Both legacy commands were originally additive (raw insert / updateOrInsert),
which stacked roles and produced 67 users with branch+division duplicates.
Both now use syncRoles. The orchestrator (MigrateOldDatabase::migrateAllTables)
runs them in the order above.

PRODUCTION RUN: `php artisan migrate:fresh` then `php artisan migrate:old-db --table=all`.
After running, verify ZERO users hold >1 role, and review the Group B skip-log
output by permissions:migrate-legacy-from-old (those need manual NRCS authorization).

Single-role is convention, not DB-enforced. Day-to-day role edits go through
UserController@updateRoles (syncRoles, safe). Do not reintroduce assignRole/
raw pivot inserts for operational roles.


## Tutorial
tutorial completion posts to a server-rendered route('tutorials.complete') URL rather than a JS-constructed path, after an undefined lesson key silently sent every completion to /learn/lesson/undefined (405) — masked by the player's swallowed .catch.
Tutorial Completion report population = role-holders only (whereHas('roles')), super-admins excluded via selectableForEntry(), matching the app-wide admin-search convention. "No Role" members are intentionally out of scope.
Report access limited to national + branch access levels (division/none → 403). Branch viewers are scoped to their own branch via getScopedBranchId(); national viewers get a branch dropdown with "National" (= all branches) on top. Chosen over the campaign-planning report's see-all-branches behaviour because this is per-person data.
Ordering by withMax('tutorialProgress', 'completed_at') desc; users with no completions sort last (MySQL NULL-last on DESC).

## First-aid map
First-aid map data is precomputed by firstaid:recalculate into first_aid_count / first_aid_avg_days / first_aid_computed_at on branches & divisions (raw average days, not a normalised score — colour normalisation happens at render via a tunable cap, default 1095 days). Population is lifecycle_status != 'archived' grouped by the user's branch/division; "first aider" = ≥1 non-deleted training of a training_types.is_first_aid type; freshness = avg days since each person's latest such training. Written via raw DB::table()->update() like heat scores, so no model $fillable changes.

First Aid Coverage map (reports.maps.first-aid.{branches,divisions}) clones the volunteer map; bubble size = first_aid_count, colour = freshness via fresh = 1 − min(1, avg_days/cap) fed into the existing heatColor() so green = recently trained (inverted from the heat map). Cap = Setting('first_aid.freshness_cap_days', 1095), normalised at render so the curve is tunable without recompute.

## Dormancy policy
Dormancy policy split by user type, defined once in User::isDormantByPolicy() / lifecyclePolicyType(). Volunteers (in a Red Cross unit) and "neither" users use the last_activity_at inactivity threshold (membership.dormant_after_months); members (no unit, with membership history) are dormant whenever they hold no current valid membership payment (expiry-based), regardless of inactivity — fixing members being wrongly dormant'd while their multi-year membership was still valid. Volunteer status takes precedence over membership. lifecycle:reconcile evaluates both directions and doubles as the one-time corrective sweep (--apply) for migration.
Batch 2: User::recalculateLifecycle() (post-deletion) delegates its demotion decision to User::isDormantByPolicy() instead of inline last_activity_at threshold checks. It remains demote-only (active → dormant); promotion stays event-driven (markActive()) plus the manual/scheduled lifecycle:reconcile. recalculateLifecycle() still recomputes last_activity_at (needed for the volunteer/neither branch, harmless for members). (UpdateUserLifecycleFromActivity, the unscheduled one-way batch demote command this paragraph originally also covered, was removed — lifecycle:reconcile is the sole scheduled sweep and already shares the same isDormantByPolicy() decision.)

## 500 error
/red-cross-units/{id}/edit 500'd on production-scale data (OOM at 128M) due to an unused User::orderBy()->get() hydrating the entire users table in RedCrossUnitController@edit. Removed — it fed no view variable. Watch for the same unbounded User::...->get() pattern elsewhere; it's invisible on small local data and only surfaces at full scale.

## Training validity (valid_years) is HQ-governed, not user-entered (2026-06-15)
On /trainings/create the Valid Years input was removed. valid_years is now derived
server-side in TrainingController@store from the selected TrainingType's
validity_years_limit (single source of truth; resistant to JS failure / tampering). The
form shows a read-only reminder of the expiry instead. NOTE: TrainingController@update and
the edit view still allow valid_years to be changed (national admins can correct an existing
record) — this asymmetry is intentional: HQ default on entry, correctable on edit.

## users.last_first_aid_at — denormalized latest first-aid date (2026-06-15)
Date (nullable, indexed) of a user's most recent non-deleted training whose
training_type.is_first_aid = true. Single source of truth for "latest FA per user",
replacing the per-user derivation previously inlined in firstaid:recalculate.
- firstaid:recalculate now recomputes the column for ALL users (set-based UPDATE, no model
  hydration) on a real run, then computes the branch/division first_aid_* aggregates by
  reading the column (lifecycle_status != 'archived' still filtered at aggregation).
  Aggregate numbers are unchanged — only the source moved from a trainings join to the column.
- --dry-run writes nothing; its aggregate preview is computed from the equivalent trainings
  subquery so it stays accurate without a column dependency (matches lifecycle:reconcile's
  dry = safe convention).
- Depends on training_types.is_first_aid being flagged (same gotcha as the FA map / "has any
  first aid" filter): on a migrate:fresh DB the recompute writes NULL until
  FirstAidTrainingTypesSeeder runs.
- Maintained live by TrainingController@store (forward-bump) and backfilled by migrate:old-db,
  which invokes firstaid:recalculate after the trainings import — but only once
  training_types.is_first_aid is flagged. If no FA type is flagged the backfill is skipped
  with a warning (prevents writing an all-NULL column / blank FA map at cutover and points
  to FirstAidTrainingTypesSeeder + a manual firstaid:recalculate). Edit/delete drift
  self-heals at the nightly recompute.

## FirstAidTrainingTypesSeeder — canonical owner of training_types.is_first_aid (2026-06-15)
The is_first_aid flag (used by the FA heat map, the users/index "has any first aid" filter, and
users.last_first_aid_at) is owned by database/seeders/FirstAidTrainingTypesSeeder, registered in
DatabaseSeeder after training types exist. Authoritative and idempotent: sets is_first_aid = 1
for the canonical FA type names and 0 for all others, so the flag survives migrate:fresh
deterministically and manual drift is corrected on reseed. CONSEQUENCE: a new first-aid training
type must be added to the seeder's name list, or the next db:seed clears its flag. Keyed by exact
name (the set flagged on dev as of 2026-06-15).

## Footgun: never run migrate:old-db --table=branches (or --table=divisions) alone on a populated DB (2026-06-15)
migrate:branches clears and re-inserts branches; four FKs reference branches with ON DELETE SET
NULL, so deleting branches nulls branch_id on users, divisions, organisations, and logs. A
standalone --table=branches (or --table=divisions) run therefore silently wipes those links, and
only a full ordered --table=all re-import restores them (logs.branch_id is app-generated, not in
old_db, so unrecoverable — but inconsequential metadata). Only import branches/divisions as part
of a full ordered --table=all import against a populated database.

## Campaign placeholder: time since last first aid (2026-06-15)
Merge token (mirroring the existing campaign placeholder convention) rendering the
human-readable time since a user's most recent first-aid training, e.g. "2 years, 3 months",
computed from users.last_first_aid_at to send date via User::timeSinceLastFirstAid() (returns
null when no FA record). The resolver substitutes null with "no first-aid training on record".
Excluding/targeting users with no FA record is a separate filter-wizard concern, not handled
by the placeholder.

## Campaigns: placeholders now render at send time, per recipient (2026-06-16)
Pre-existing gap: CampaignPlaceholderRenderer ran only in the Step 5 wizard preview;
CampaignSendRunner delivered $campaign->body raw, so NO {{...}} token (first_name included)
substituted at real send time. CampaignSendRunner now renders each templatable field (subject,
body, email/SMS bodies — the set the preview renders) per recipient via the same
CampaignPlaceholderRenderer, into local copies (the shared $campaign model is never mutated in
the loop), passing rendered values to the delivery channel(s). Rendering reads only
already-loaded recipient data; relation-backed tokens are eager-loaded on the per-chunk
recipient fetch (branch, division, redCrossUnit, currentMembershipPayment.membershipFee) and
column-backed tokens are selected on that fetch — no per-recipient queries at ~70k scale.
Time-based tokens (e.g. time_since_last_first_aid) reflect the actual send moment.
- CampaignPlaceholderRenderer is now lazy: only tokens present in the template are evaluated
  (memoised once per render), so output is unchanged and preview==send is preserved. This is
  what keeps the expensive donations_summary resolver from firing on campaigns that don't use it.
- donations_summary, when used, costs 2 COUNT queries per recipient via User::getDonationSummary
  (the accessor re-queries and can't be eager-loaded away). Accepted as intrinsic to that token;
  User model left untouched. All other relation tokens are eager-loaded → 0 per-recipient queries.

## users/index: First Aid Refresher filter (2026-06-15)
Filter key first_aid_refresher: value = month threshold (12/18/24/36/48/60), empty = off.
Matches last_first_aid_at NOT NULL AND last_first_aid_at < now() − X months (users who HAVE
first-aid training that has gone stale). Never-trained users (last_first_aid_at NULL) are
deliberately excluded — that audience is the existing training_filter=none_firstaid. Implemented
in UserFilterService so it applies to both the index listing and campaign recipient resolution
from a saved filter. Pairs with the {{user.time_since_last_first_aid}} placeholder. Index default
is off; 36 months is the typical refresher interval and the Campaign Wizard's pre-selected default.

## Campaign purpose: first_aid_refresher (2026-06-16)
Dedicated CampaignPurpose slug=first_aid_refresher ("First Aid Refresher") for the "any first
aid is getting stale" campaign, instead of reusing training_expiry. Rationale: the goal of the
first-aid-refresher work is to simplify away from per-training-type expiry tracking; a separate
purpose keeps refresher independent of training_expiry, which may be retired later pending
client input. The wizard refresher section and the campaign_msg contact throttle key off this slug.

## Training Coverage tab: scoped to active & dormant volunteers (2026-06-16)
The Coverage tab in the Training Statistics report now counts via User::scopeVolunteers()
(lifecycle active|dormant + active RC unit) instead of ->active() (active lifecycle, all
persons). Effect: members are excluded and dormant volunteers are included, so the denominator
is the operational volunteer base. Other tabs (Expiry, Certificates, Campaigns, First Aid
Staleness) are unchanged.

## First Aid Staleness tab: active & dormant, split volunteers vs members (2026-06-16)
The staleness tab counts active & dormant persons (members AND volunteers — NRCS trains anyone),
split into Volunteers and Members per age band via two grouped passes using the canonical
User::scopeVolunteers() and User::scopeMembers(). Each band has a Vol and a Mem column; Total
columns are per-type. Consequence of the canonical split: an active/dormant person with first
aid who is currently neither a member nor a volunteer (no unit, no valid payment) appears in
neither column. Persons with no FA record are excluded (Coverage tab covers training presence).

## ID verify page: canonical first-aid split + other trainings + volunteering hours (2026-06-16)
The public QR verify page (id-cards/verify) now partitions a member's non-deleted trainings via
the canonical training_types.is_first_aid flag instead of a name string-match: First Aid
Certifications (is_first_aid = true; expired still shown, badged) and a new "Other Trainings"
list (is_first_aid = false). Added "Total Volunteering Hours" (sum of non-deleted activities.hours).
Switching the discriminator keeps the page consistent with the FA map/filter/last_first_aid_at.

## Archived-account page: DB reference + copy-paste rejoin email (2026-06-16)
When login detects an archived user, the verified user's user_id_reference, full name, and
branch_id are stored in the session (not the URL — avoids enumerating archived accounts / DB
codes). The "Account Deactivated" page shows the DB reference prominently and offers a ready-made
rejoin message (Copy button + mailto to the branch email, pre-filled with subject, body, and the
DB reference). No self-service reactivation was added — reactivation stays a human decision at the
branch, since some accounts are archived deliberately.

## Pre-production log checklist (2026-06-17)
Before VPS go-live, change in .env:
- APP_DEBUG=false (currently true — leaks stack traces/query bindings on error pages)
- LOG_LEVEL=error or warning (currently debug — would carry dev noise into prod)
- Confirm APP_ENV=production (also disables the local-only SLOW QUERY listener)
  Note: fatal errors/exceptions are always logged via Laravel's exception handler
  regardless of APP_DEBUG — this is about verbosity/exposure, not whether errors get
  captured at all.

## Two-step approval workflow — donations / payments / volunteering / training (2026-06-22)

Records are created `pending` and only become "real" once approved. Submit → approve,
with four-eyes (no self-approval) and branch→national escalation.

- **"Only approved is real" invariant.** An `ApprovedScope` global scope on all four
  models hides pending/rejected by default. Lists, totals, reports, and lifecycle all
  see approved-only automatically. Opt in with `pendingApproval()` /
  `withAnyApprovalStatus()`.
- **Migration backfilled all existing rows to `approved`, and the four legacy
  importers stamp `approved` on insert.** Without both, every pre-existing/imported
  record would vanish behind the scope. Any NEW importer or query must account for the
  scope the same way.
- **Route-model binding caveat.** The global scope makes implicit binding 404 on
  pending records. Approval routes resolve via `withAnyApprovalStatus()->findOrFail()`,
  not implicit binding. `/approvals` is registered before `/{record}`.
- **Rejection vs withdrawal vs deletion are distinct.** Reject = status + reason (no
  delete; hidden by scope). Withdraw = guarded atomic hard-delete of one's own pending
  record (audit-logged first; the `where status=pending` guard handles the
  approved-in-between race). The pre-existing soft-delete/destroy path is untouched and
  applies only to approved records.
- **Deletion-mechanism divergence left alone, on purpose.** Donations uses SoftDeletes
  (deleted_at→removed_date) + an `is_deleted` flag; the other three use only
  `is_deleted`. The approval layer never touches deletion, so this was NOT unified.
  Separate cleanup ticket.
- **§7 raw-SQL report/command sites got explicit `approval_status='approved'` filters**
  because they bypass the global scope. The `MembershipPayment::`-based MembershipStats
  methods were deliberately NOT patched — they're Eloquent and inherit the scope, and
  force-filtering them risks ambiguous-column errors in their joins.
- **DatabaseTeamReportController is the one intentional exception** to approved-only:
  `cnt_*_entered` counts pending+approved (excludes rejected), because it measures the
  entry clerk's work, not approval outcomes — counting approved-only would make their
  number swing with approver latency. Documented in-code.
- **First-aid freshness recomputes on approval** via an `afterApproved()` hook
  (Training only), so the flagship staleness/bubble-map reads update immediately rather
  than waiting for the nightly `firstaid:recalculate`.
- **`approve_*` granted to `national_db_administrator` only** (not super-admin, which is
  observer-level; not the assistant tier). This grant is what makes branch→national
  escalation reachable — without it, a single-approver branch deadlocks on its own
  submissions. It is a routine capability, not a strict escalation-only gate; relies on
  practice + audit log.
- **Bulk approve never reactivates an archived member** — those are skipped and reported
  for individual handling via the confirmation modal.


- **super-admin cannot approve, by design.** It is observer-level with no Gate::before
  bypass, so it sits outside the approval chain entirely. This absence is deliberate and
  load-bearing: adding a Gate::before super-admin bypass would silently break four-eyes
  (super-admins could self-approve and bypass scope). There is consequently no break-glass
  approver above national_db_administrator — an accepted edge.
- **decided_at is cast to datetime centrally** in the Approvable trait
  (initializeApprovable() + mergeCasts()), not per-model, so all four stay in sync. Without
  the cast the review Blade 500s (->format() on a string) — a render-layer bug that
  model/tinker checks don't catch.


## fix:userdata placement in migrateAllTables()

**Decision:** `fix:userdata` is called after `lifecycle:reconcile --apply`
in `migrateAllTables()`, not before it or independently.

**Rationale:** Fix 5 in `fix:userdata` deletes users where `division_id IS NULL
AND organisation_id IS NULL` unconditionally — no guard, no dry-run flag. The
`MigrateUsers` restore pass (added after the reconciliation block) re-populates
division_ids for users whose division is resolvable from the old DB. If
`fix:userdata` ran before the restore pass completed, it would delete those
users rather than the single genuine orphan it is intended to remove.

The safe run order is:
1. `migrate:users` — imports users; restore pass recovers nulled division_ids
2. `lifecycle:reconcile --apply` — reconciles active/dormant users (no-op at
   this point in a clean run; nothing is active/dormant yet before fix:userdata's
   step 6 promotes the first users out of pending_engagement — see the
   2026-07-20 entry below)
3. `fix:userdata` — Fix 5 (now Fix 7, see the 2026-07-20 entry below) safely
   deletes only genuinely unresolvable users

**During rehearsal migration:** 373 users had valid division_ids temporarily
nulled by the reconciliation block due to stale state from prior dev runs.
These were recovered by the restore pass. On a clean single-pass production
run, the reconciliation block should find zero mismatches and the restore pass
should recover zero users. If the post-migration verify query returns > 0:

    SELECT COUNT(*) FROM users
    WHERE division_id IS NULL AND branch_id IS NOT NULL;

re-run `migrate:users` — the restore pass will recover them before
`fix:userdata` runs.

**Do not** move `fix:userdata` earlier in the sequence or run it standalone
before `migrate:users` has completed its restore pass.

---

## 2026-07-20 — Removed users:mark-dormant-from-activity from migrateAllTables()

**Decision:** `users:mark-dormant-from-activity` no longer runs as part of
`migrateAllTables()` (it previously ran as step 2 in the run order above,
before `lifecycle:reconcile --apply`). The command file itself is untouched
and can still be run standalone if ever needed; it is simply no longer
wired into the automated pipeline.

**Rationale:** The command demoted users to `dormant` based solely on
`last_activity_at` being null or older than `membership.dormant_after_months`,
scoped to `lifecycle_status IN ('pending_engagement', 'active')`. Three
problems, discovered together:

1. It ran *before* `fix:userdata`'s recompute step (see below), so it read
   the raw, not-yet-corrected `last_activity_at` seeded from legacy
   `persons.LastActivity` during `migrate:users` — not the value derived
   from actually-imported training/activity/donation/payment records.
2. It included `pending_engagement` in its own scope, so it could demote a
   still-pending user straight to `dormant` — a transition that doesn't
   exist anywhere else in the lifecycle state machine (there is no
   documented `pending → dormant` path) — bypassing the RCU-assignment /
   non-volunteer-fee-payment gate that decides whether a user should leave
   `pending_engagement` at all.
3. It applied the `last_activity_at` threshold uniformly, with no branch
   for the `member` policy type, whose dormancy (per
   `User::isDormantByPolicy()`) is decided by current payment validity,
   not activity recency — so it could wrongly demote a member with a
   still-valid multi-year payment but an old `payment_date`.

Because it ran before `fix:userdata`, and `fix:userdata`'s pending-promotion
logic (step 6) only touches users still at `lifecycle_status =
'pending_engagement'`, any user this command wrongly pushed to `dormant`
using stale data was permanently unreachable by the corrected logic —
`fix:userdata` step 5 only recomputes `last_activity_at` for users still
pending, so a user demoted here never had it corrected, even by the
second, "final word" `lifecycle:reconcile --apply` call.

Simply reordering it to run after `fix:userdata` was considered and
rejected: it would still touch `pending_engagement` (undoing step 6's
gate for every non-qualifying user with old/no activity) and would still
ignore the `member` branch. Its function is fully and more precisely
covered by `fix:userdata` steps 5 (recompute `last_activity_at` from
imported records) and 6 (RCU/fee-gated promotion, classified via the real
`User::lifecyclePolicyType()`/`isDormantByPolicy()`), plus the two
`lifecycle:reconcile --apply` calls that already reconcile every
active/dormant user against the same canonical, type-aware policy.

**Consequence:** the first `lifecycle:reconcile --apply` call (step 2 in
the run order above) is now a guaranteed no-op in a clean migration run —
nothing is `active`/`dormant` yet at that point, since `fix:userdata` step
6 is the first thing in the pipeline that promotes anyone out of
`pending_engagement`. Left in place as harmless (0 rows matched); not
removed, since a later change to the run order could make it meaningful
again.

Show the appended entry. Do not change anything else.

NOTE: dates:shift is dev-only; never run on production data.

## Dormant → active promotion on record approval (Approvable.php)

**Decision:** When an approvable record (training, membership payment, activity,
donation) is approved for a dormant user, Approvable::approve() unconditionally
lifts lifecycle_status to 'active' (line 212), then immediately calls
recalculateLifecycle() (line 215) as a policy safety check.

**Why lift-then-check rather than check-first:**
Approval is a deliberate human act confirming a real-world event. The lift
signals intent; recalculateLifecycle() then arbitrates whether policy conditions
are actually satisfied. If the new record does not meet the dormancy threshold
(e.g. a member whose payment is still expired), recalculateLifecycle() re-demotes
to dormant immediately. The net result is always policy-correct.

**Consequence:** dormant → active promotion in normal operations happens
exclusively on record approval via Approvable. recalculateLifecycle() called
from entry controllers is demote-only. lifecycle:reconcile --apply is
bidirectional and is the scheduled nightly sweep (03:00) as well as the
manual bulk correction tool.

**Full transition map:**
- pending → active:    lifecycle:reconcile --apply only (post-migration corrective)
- active → dormant:    nightly lifecycle:reconcile + recalculateLifecycle() on record removal
- dormant → active:    record approval (Approvable) + lifecycle:reconcile --apply
- * → archived:        manual admin action (UserController edit form)
- archived → active:   manual admin action, or record approval with explicit flag
- * → active:          RCU assignment (UserController:952, calls markActive())

**Do not add a promotion path to recalculateLifecycle().** It is intentionally
demote-only. Adding promotion there would cause dormant users to be silently
re-activated whenever any record is touched, including edits and removals.

**2026-07-20 update:** the `pending → active` line above is incomplete —
`Approvable::approve()` also silently promotes `pending_engagement` →
`active` for `Activity`/`MembershipPayment` approvals, ungated at the trait
level (see the "Closed two runtime gaps..." entry below for the details,
the two backend guards added against invalid inputs to that path, and the
accepted gap that remains).

---

## 2026-07-20 — Closed two runtime gaps feeding Approvable's ungated pending_engagement promotion

**Context:** `Approvable::approve()` (`app/Models/Concerns/Approvable.php:230-232`)
unconditionally promotes a `pending_engagement` user to `active` when an
approved record's `promotesFromPendingEngagement()` returns true — the
default for every module except `Training` and `Donation` (which correctly
override it to `false`). So `Activity` and `MembershipPayment` approvals
promote a `pending_engagement` user with **no check on RCU status or
`is_volunteer_fee`** — the two conditions that are supposed to gate leaving
`pending_engagement` in the first place (see the migration-time equivalent
gate in `fix:userdata`, documented in the entry above).

An audit of every live-runtime write path to `lifecycle_status` (excluding
`OldDbMigration` commands) confirmed this is still exactly the current
code — no drift since the trait was written. Rewriting `Approvable` itself
(e.g. adding an RCU/fee-type check inside `approve()`, or inside
`promotesFromPendingEngagement()`) was judged too broad a change for the
risk today — it's shared by all four approvable modules and touches every
approval in the system. Instead, the audit found and closed the two
concrete paths that could hand `Approvable` invalid data to promote on:

1. `MembershipPaymentController` had no backend check that a payment's fee
   type (`is_volunteer_fee`) matched the target user's RCU status — only
   client-side dropdown filtering in `membership-payments/create.blade.php`
   (DOM `hidden`/`disabled` on `<option>` elements), trivially bypassed via
   devtools, a direct POST, or any future API/import path.
2. `UserController`'s RCU-assignment save path called `User::markActive()`
   (force-promoting to `active`) on **any** `red_cross_unit_id` change,
   including unassignment (setting it to `null`) — so removing a user's
   unit still left them incorrectly `active`, with no unit and no
   membership payment basis behind it.

**What changed:**
- `MembershipPaymentController::store()` and `::update()`
  (`app/Http/Controllers/MembershipPaymentController.php`): added a
  closure-based `Validator` rule on `membership_fee_id` (same pattern as
  the existing `red_cross_unit_id` closure rule in
  `UserController.php:803-824`) checking `$fee->is_volunteer_fee` against
  the target user's `red_cross_unit_id` + `redCrossUnit->is_active`,
  rejecting mismatches with a validation error, in both the create and
  edit flows. Applies uniformly to personal and organisational payments —
  no exemption was carved out for organisational payments; that's a
  deliberate choice, not an oversight, since organisational payments are
  handled manually going forward.
- `UserController`'s unit-change handler (`UserController.php:1008-1009`):
  changed `if ($unitChanged && $user->lifecycle_status !== 'archived')` to
  also require `$newUnitId !== null` before calling `markActive()` — so
  unassignment no longer force-promotes. Deliberately does **not** add any
  demotion/recompute on unassignment — the user's `lifecycle_status` is
  simply left unchanged in that case. Whether unassignment should trigger
  a fresh `recalculateLifecycle()` is an open follow-up, not decided today.

**Deliberately not changed:**
- `Approvable::approve()`'s generic promotion logic itself remains ungated
  at the trait level. `Activity` and `MembershipPayment` still have no
  override of `promotesFromPendingEngagement()` (unlike `Training`/
  `Donation`, which correctly return `false`). This means: if any future
  code path creates/approves an `Activity` or `MembershipPayment` for a
  `pending_engagement` user **without** going through the two guarded
  controllers above (a new API endpoint, a bulk-import script, a future
  admin tool, tinker/artisan), the ungated promotion reopens. This is a
  known, accepted gap — not fixed today.
- `RedCrossUnitController::destroy()`'s existing hard block on
  deactivating a unit that still has assigned users (`->users()->count() >
  0` check) was confirmed safe during the same audit and left untouched.

**Consequence:** the two most common real-world routes to feeding
`Approvable` an invalid `pending_engagement` promotion (a mismatched
membership payment, or an RCU unassignment) are now closed. The trait-level
gap itself is unchanged and remains reachable from any path that bypasses
these two controllers.



### /idcheck public verification page — profile photo removed
Date: [today]
Decision: The public ID card verification page (/idcheck/{token}) no longer
displays the volunteer's profile photo. Photos now require authentication
(photos.show route, auth middleware group).
Reason: NDPA compliance — biometric images must not be served to unauthenticated
visitors. Name, branch, division, and membership status remain visible, which
is sufficient for ID verification purposes.
Reviewed by: [your name]


## Organisation-linked persons: membership not required, but encouraged

**Context:** An Organisation must have at least one linked Person to make donations
or membership payments. The question arose whether linked persons need to hold
personal membership themselves.

**Decision:** No — linked persons are not required to be personal members or
volunteers. However, this surfaced a lifecycle-system gap: a person who is *only*
an organisation contact (no Red Cross unit, no personal membership) falls into the
`lifecyclePolicyType() === 'neither'` bucket, which uses inactivity-based dormancy
(`last_activity_at`). Since being an org contact never touches `last_activity_at`,
such a person could be silently auto-flagged dormant by the nightly sweep, or
archived via bulk-archive, purely from personal inactivity — even while actively
serving as their organisation's registered contact.

**Fix applied (minimal exemption, not a full policy branch):**
- `ReconcileLifecycleStatus` (the scheduled `lifecycle:reconcile` sweep) excludes
  organisation-linked users via `whereDoesntHave('organisations')`.
- `DormantUserController::bulkArchive()` applies the same exclusion, so this
  exemption is enforced at the point of archiving, not just cosmetically in the
  bulk-archive listing UI (which already filtered them out visually).
- The individual user edit screen's manual archive checkbox is deliberately
  **not** exempted — a human admin archiving a specific person is a considered
  action, not a silent sweep. Instead, `users/edit.blade.php` shows a warning if
  the person is the *sole* linked contact for an organisation, naming the
  organisation and noting that archiving will block that organisation's ability
  to make payments/donations until a new contact is linked.

**Not implemented:** a full `lifecyclePolicyType()` branch for org contacts (e.g.
an `org_contact` type with its own dormancy rule). Considered but deferred as
disproportionate to the actual risk — chosen the minimal exemption instead. If
org contacts ever need their own richer lifecycle treatment, revisit here first
rather than special-casing further in the nightly command.

**Note (corrected 2026-07-20 — see below):** ~~if a linked person also pays
personal membership, they're already classified `member` (not `neither`) and
follow normal membership-expiry-based dormancy — this scenario was already
handled correctly before this fix.~~ This was only half true. `lifecyclePolicyType()`
did (and does) correctly classify such a person as `member`, not `neither` — but
that classification was never being *reached* for them: the `whereDoesntHave('organisations')`
exclusion above filtered them out of the `ReconcileLifecycleStatus` query entirely,
before any per-user classification ever ran. A real, currently-active RCU
volunteer or dues-paying member who also happened to carry an organisation
link was excluded from the nightly sweep just as completely as a
contact-only person — indefinitely, since the org link is sticky (only
removable via `OrganisationController::unlinkUser()`, a separate manual
admin action with no automatic trigger tied to RCU/membership status).
Fixed below.

---

## 2026-07-20 — Narrowed the organisation-contact exemption in ReconcileLifecycleStatus

**Decision:** `ReconcileLifecycleStatus`'s query-level `whereDoesntHave('organisations')`
(described above) is replaced with a per-user check inside the existing
chunked loop: a user is skipped **only** if they are organisation-linked
**and** `lifecyclePolicyType() === 'neither'`. Everyone else — including an
organisation-linked user who is also RCU-assigned or a dues-paying personal
member — now goes through the normal `isDormantByPolicy()` evaluation like
any other user.

**Why:** per the corrected note above, the original blanket exclusion was
broader than its own stated purpose. It was meant to protect genuine
organisation-only contacts from being wrongly auto-dormanted for lacking
`last_activity_at`, but it also silently and permanently exempted real
volunteers/members from the passive nightly correction mechanism for as
long as they carried an org link — which, being sticky, could be forever.

**Implementation:** the main query keeps `whereIn('lifecycle_status', ['active','dormant'])`
and adds `withCount('organisations')` (a single joined subquery per chunk,
not eager-loading the related rows — avoids N+1 without fetching data
that's never used). `lifecyclePolicyType()` is computed once per user at
the top of the loop and reused for both the skip decision and the existing
stats/sample reporting (previously computed twice — once implicitly via
the removed query filter's intent, once explicitly for stats).

**Known follow-up, not fixed today:** `DormantUserController::bulkArchive()`
still uses the original blanket `whereDoesntHave('organisations')` exclusion
(unchanged). That action only ever writes `lifecycle_status = 'archived'`
via an explicit admin bulk-selection, not a passive sweep, so the risk
profile is different — but the same "org-linked real volunteer/member gets
permanently skipped" gap technically still applies there. Revisit if this
matters for that action too.


## 2026-07-05 — Photo view logging removed

Logging every profile photo view (`sensitive_photo_viewed`, added in commit
1e9c2ba) was removed two days later in commit 7f657a4, bundled with an
unrelated change and undocumented at the time.

Retroactively confirmed as the correct call: logging every photo view
generated excessive log volume with no proportionate compliance benefit —
photo access control is already enforced via authenticated PhotoController
access, which is the actual safeguard. This entry formalizes that decision
after the fact so it's not mistaken for an unexplained regression.


## 2026-07-18 — Consent vs. policy-acceptance gating: migrated users

Two related-but-distinct NDPA fields on `users`, deliberately treated
differently:

**consent_obtained_at** (member/volunteer NDPA consent, recorded once at
registration — see `RegisterController`/`UserController::store()`) is
intentionally **not** enforced retroactively. Migrated users imported via
`MigrateUsers.php` have this field NULL, and nothing in the app currently
gates on it — no middleware, no visibility/contactability restriction, no
downstream check of any kind. This is a deliberate decision pending NRCS's
final policy on historical/migrated member consent, not an oversight.

**policy_accepted_at** (staff data-handling policy acknowledgement) IS
fully enforced for every role-holding user regardless of origin.
Confirmed by investigation: `MigrateUsers.php`'s user-insert (~60 fields,
explicitly "ALL the fields") never sets `policy_accepted_at`, so every
migrated admin/staff account has it NULL and is forced through
`/policy/accept` (`RequiresPolicyAcceptance`, appended globally to the
`web` middleware group in `bootstrap/app.php`) on first login after
go-live — identical treatment to a freshly-created account. No gap exists
here.


## Photo caching fix — verified via automated test, NOT yet confirmed in real browser use

**Decision/status:** `PhotoController::show()` was updated to send proper
`Cache-Control`, `ETag`, and `Last-Modified` headers, intended to fix a bug
where a newly uploaded profile photo would not display until the user
performed a hard refresh (the photo URL never changes even when the
underlying file does, so browsers were serving a stale cached copy
indefinitely).

**What was verified:** An automated test confirmed the fix works correctly
at the HTTP level — replaying a browser's old cached `ETag`/
`Last-Modified` headers after a real upload correctly returns a fresh 200
with the new image, not a stale 304. All existing photo-authorization
tests (`DataAccessControlTest`) still pass unaffected.

**What was NOT resolved:** In manual testing on the VPS immediately after
this fix, a hard refresh was still required to see a newly uploaded photo
— the exact symptom the fix was meant to eliminate. This discrepancy is
unexplained. Possible causes not yet investigated: browser-level caching
behavior that doesn't even send a conditional request in the first place
(in which case `Cache-Control` headers alone can't help), an intermediate
caching layer (proxy, XAMPP/dev-server config) between the browser and the
app, or a genuine remaining gap in the fix not caught by the automated
test's request-level simulation.

**Do not treat this as fully resolved.** Testing was paused because the
specific test users showing the bug (black-and-white placeholder photos)
were exhausted — all had already been "fixed" by re-uploading during this
session, leaving no remaining reproduction case. Re-verify this properly
the next time a user with a stale/placeholder photo is available, ideally
by checking actual browser Network tab behavior (is a conditional request
even being sent?) rather than relying on the automated test alone.
