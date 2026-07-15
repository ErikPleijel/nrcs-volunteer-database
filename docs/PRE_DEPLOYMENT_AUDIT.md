# NRCS Laravel — Pre-Deployment Audit Checklist

A consolidated, ordered checklist to run before the production migration and
go-live (~7,000–13,000 users). Work top to bottom; the earlier categories are
the highest-stakes and hardest to fix after launch.

**Workflow note:** for each item, give Claude Code a tightly-scoped
*investigation* prompt first, review the output, then decide whether a *fix*
prompt is needed. Never combine investigate + fix in one prompt — you lose the
chance to review before changes land.

> Status: drafted before final NRCS feedback. Some items may shift once their
> requirements are confirmed. The app is generally well-received so far.

---

## 1. Data integrity

- [ ] **Lifecycle coherence** — after the final migration run (and post `dates:shift` + `lifecycle:reconcile --apply`), run `lifecycle:reconcile` in dry-run mode and confirm it reports zero discrepancies. Non-zero means the data and the cron policy are already out of sync before go-live.
- [X] **Stat-column correctness (`created_at` vs event date)** — scan all dashboard and report "last N days/months" stat queries. Each must filter on the real event column (`printed_at`, `date_donation`, `training_date`, `payment_date`, etc.), not `created_at` (insert time). Critical because `dates:shift` moves event dates but not `created_at`. (Found example: `id_card_prints` used `created_at` instead of `printed_at`.)
- [X] **ApprovedScope blind spots** — grep every `DB::table()` query in the codebase against the four approvable tables (donations, membership_payments, activities, trainings). Confirm none should be applying approval filtering, since `DB::table()` bypasses `ApprovedScope` silently.
- [ ] **Stats snapshot consistency** — spot-check `stats:backfill`-generated rows against a live `scopeVolunteers()` count for the same month. Confirm the historical-vs-current-state issue (scope evaluating `is_active` / `lifecycle_status` as it stands now) is either resolved or documented as a known limitation.
- [ ] **Orphaned relational data** — check for: users with `division_id = NULL` where a valid match exists (the 373-user bug); `red_cross_unit_id` pointing to soft-deleted/inactive units; membership payments without a valid user; campaign recipients pointing to archived users; orphan rows in activities/payments/taskforce_members after `fix:userdata` Fix 5.
- [ ] **`created_at` registration-date integrity** — confirm `created_at` is sourced from old-DB `Timestamp` for migrated users (verified: it is, modulo `dates:shift`). Confirm only genuinely non-migrated accounts (seeded admins) carry the migration-run date.
- [ ] **Four-eyes integrity (data)** — `Approvable::guardNotSelf()` structurally prevents self-approval by throwing at approve/reject time if `decided_by_user_id` would equal the submitter column. Verifiable by code inspection, or with a one-off SQL comparison of `decided_by_user_id` against the submitter/entered-by column on each approvable table (donations, membership_payments, activities, trainings) joined to `users.is_super_admin` — not a manual audit-log review.

## 2. Migration command sequence

- [ ] **Command order invariant** — write the canonical `migrateAllTables()` sequence and have Claude Code verify the implementation matches it step by step. Watch especially: `MigrateBranches → BranchSeeder`; `MigrateUsers` restore-pass placement (after reconciliation block); `lifecycle:reconcile --apply` before `fix:userdata`, then `fix:userdata` before a second `lifecycle:reconcile --apply` (fix:userdata's raw-SQL activity patches are membership/ghost-blind and can overwrite the first pass, so reconcile runs again last to have the final word).
- [ ] **Idempotency check** — run the full pipeline twice on a clean DB and diff the results. Anything differing between run 1 and run 2 is non-idempotent and needs a guard.
- [ ] **Interactive prompt guards** — confirm every command called inside `migrateAllTables()` has an `isInteractive()` guard or `--no-interaction` handling (the `MigrateBranches` fix is the template).
- [ ] **Destructive-command guards & post-run cleanup** — list every command that deletes or overwrites. Confirm each has the right guard or a documented plan: `fix:userdata` Fix 5 (orphan delete — runs after restore pass); `UserTokenSeeder` (breaks printed QR codes if re-run — delete the seeder file after the real migration).
- [ ] **Post-shift reconcile reminder** — confirm the runbook documents that `lifecycle:reconcile --apply` must be run *manually after* `dates:shift`, since the in-migration reconcile runs against pre-shift dates and promotes nobody.
- [ ] **Pending-engagement coverage** — after migration + shift, check the `lifecycle_status` distribution. If a meaningful number remain `pending_engagement`, note that `lifecycle:reconcile` does not scan that status and decide whether a separate pass is needed.

## 3. Security

- [ ] **Route/permission matrix** — enumerate every named route mapped to its middleware / Gate / `can()` check. Flag any write-capable route reachable by `observer_national_level`. Confirm direct permissions (`send_bulk_messages`, `print_idcards`, `print_certificates`, `campaign_request_approve`) are only assignable to `national_db_assistant`.
- [ ] **`scopeSelectableForEntry()` coverage** — grep all places where users are selected for data entry; confirm `scopeSelectableForEntry()` is used, never a raw `User::all()` (which would expose super-admins).
- [ ] **Mass assignment** — review every model's `$fillable` / `$guarded` for anything that shouldn't be user-settable: `is_super_admin`, `lifecycle_status`, `approved_at`, `approval_status`, `id_check_token`.
- [ ] **Campaign opt-out bypass** — verify opt-out infrastructure can't be circumvented by a direct bulk send to a custom recipient list that skips the opt-out filter.
- [ ] **`APP_DEBUG=false` in production** — a visible stack trace given this data sensitivity is an immediate security issue. Verify before exposing the app.

## 4. Authorization edge cases

- [ ] **Self-approval prevention (four-eyes, flow level)** — confirm no action (record approval, role grant, record edit) can be both initiated and approved by the same user in any flow, including bulk. For record approval this is structurally guaranteed by `Approvable::guardNotSelf()` (verifiable by code inspection / a one-off SQL check, not the audit log); confirm the same holds for role grants and record edits, which have no equivalent guard today.
- [ ] **Bulk operation scope leakage** — for every bulk action (approve, archive, assign to campaign, etc.), confirm the underlying query is scoped to the acting user's branch/division. A branch admin must never touch records outside their branch.
- [ ] **Branch-move restriction** — verify a user with an administrative role cannot be moved to a different branch by any role below national level, including via bulk operations.

## 5. UI / UX regression

- [ ] **Mobile table overflow** — confirm the filter-section widening fix holds across *all* index pages (users, organisations, campaigns, dormant-users), not just the one originally fixed.
- [ ] **`x-time-ago` sanity** — final pass to confirm no seeded/migrated timestamps produce absurd elapsed times that would alarm staff (e.g. future dates, "in 6 years"). Note that `dates:shift` interacts with this.
- [ ] **Notification completeness** — map every user-facing action that should trigger a notification (record rejected, campaign approved/rejected, role changed) to its `Notification` class, and verify each dispatches. Confirm the bell dropdown handles each `type` and that unknown types hit the generic `@else` fallback.

## 6. Environment & deployment hygiene

- [ ] **`.env` diff against production** — compare local `.env` keys against the VPS. Missing keys fail silently or throw generic 500s. Check: `MAIL_*`, `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, queue driver, `IMAGE_MIGRATION_SOURCE`, ElevenLabs/analytics keys.
- [ ] **VPS-only / environment-dependent steps** — `images:migrate` needs GD (`php -m | grep gd`) and `IMAGE_MIGRATION_SOURCE`; smoke-test with `--limit=10` first. Confirm `DebugDuplicateEmails` (qualified table names) behaves on the VPS where `old_db` may differ from same-server XAMPP.
- [ ] **Scheduled commands** — confirm `routes/console.php` has `lifecycle:reconcile --apply` (03:00), `stats:snapshot` (02:00), `firstaid:recalculate` (02:30), and any campaign send schedule. Confirm the VPS crontab actually calls `php artisan schedule:run` every minute.
- [ ] **Queue worker** — if any jobs/notifications are queued (campaign sends, TTS generation), confirm a queue worker runs under Supervisor on the VPS and restarts on failure.
- [ ] **First-aid training types flagged** — confirm `FirstAidTrainingTypesSeeder` runs in `db:seed` and that `training_types.is_first_aid` is flagged on the VPS (defaults false; zero results otherwise). (Verified in dev: 10 types flagged.)

## 7. Documentation & runbook

- [ ] **`docs/migration.md`** — update with all rehearsal findings (division_id null expectation, expired ID cards, excluded divisions, `stats:backfill` lifecycle-NULL note, etc.) and the exact production command sequence including the manual post-shift reconcile.
- [ ] **`DECISIONS.md`** — confirm load-bearing decisions are recorded: `fix:userdata` placement after restore pass; `super-admin` no `Gate::before`; `created_at` sourced from old-DB `Timestamp`; dormant-tool dual-mode (dormant vs pending_engagement).
- [ ] **Outstanding feature work** — settings area restructure, migration report refinements. Confirm done or consciously deferred before go-live.

---

## Suggested run order

1 → 2 → 3 → 4 → 5 → 6 → 7. Within each category the items are ordered by
stakes. Categories 1 and 2 are the ones that are hardest to undo after
go-live, so don't skip ahead.
