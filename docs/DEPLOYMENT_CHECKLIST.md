# NRCS Laravel — Deployment Checklist

Generated from the pre-deployment audit (all 7 categories complete).
Work top to bottom within each phase.

> **dates:shift is dev-only.** Never run on production data. Production
> data has real 2016–2019 dates; no shift is needed or wanted.

---

## PHASE 0 — Before the migration run (verify in VPS checkout)

### Code fixes confirmed in repo
- [x] `certificatesPrintedLast7` uses `printed_at`, not `created_at`
      (DashboardController.php ~L205)
- [x] `BackfillStatsSnapshots` membership_payments query includes
      `->where('mp.approval_status', 'approved')` (~L75)
- [x] `UserTokenSeeder` — CASE-based bulk UPDATE, no upsert/INSERT,
      chunked at 500
- [x] Fix 5 (FixUserDataCommand) — `whereDoesntHave` guards for
      membershipPayments, activities, trainings added; hard-delete only
      hits true orphans
- [x] RCU dropdown + validation scoped to active units; edit path retains
      current inactive unit and exempts it from the `is_active,1` rule
- [x] `selectableForEntry()` added to 6 user-selection queries:
      ActivityController:470, TrainingController:484,
      TaskForceController:115, RedCrossUnitController:540,
      UserController:1208, BranchController:94
- [x] Permission whitelist — `UserController::DIRECT_PERMISSION_NAMES`
      constant; `updateRoles()` intersects incoming permissions against
      it before `syncPermissions()`
- [x] Branch-move destination scope — branch admins blocked from moving
      a non-roled user to an out-of-scope branch (UserController::update())
- [x] `DECISIONS.md` — fix:userdata placement after restore pass documented
- [x] `docs/migration.md` — updated with rehearsal findings, production
      tail sequence, restore-pass note, stats:backfill limitation,
      reconcile vs update-from-activity distinction

### Pre-run environment checks
- [ ] VPS git checkout is current: `git log --oneline -5`
- [ ] **php -l sweep** on all files edited this audit cycle — tooling has
      been observed silently converting `"` to curly quotes in PHP strings
      (fatal syntax error): php -l on every recently-changed file
- [ ] `APP_DEBUG=false` and `APP_ENV=production` in VPS `.env`
- [ ] Diff VPS `.env` against local: MAIL_*, APP_KEY,
      IMAGE_MIGRATION_SOURCE, ElevenLabs/analytics keys, queue driver

### Pre-go-live decisions (not blocking migration)
- [ ] **`send_bulk_messages`** — dead permission (gates no action; real
      gate is `campaign_request_create`). Decide: remove from permissions
      table + `DIRECT_PERMISSION_NAMES` constant, or add a code comment
      marking it reserved. Clean up before go-live.
- [ ] **Campaign CLI opt-out gap** — `campaigns:build-recipients` (CLI)
      writes unmasked email/phone with no opt-out check. For live
      campaigns on `both`/`email_fallback_sms` channels, always use the
      HTTP build path (CampaignAdminController::buildRecipients), not
      the CLI command.

---

## PHASE 1 — After `migrate:old-db --table=all`

Run all queries before touching anything else. Record every number.

### Data integrity checks

- [ ] **division_id NULL count** — expect 0 (373 in rehearsal; restore
      pass recovers all):
      ```sql
      SELECT COUNT(*) FROM users
      WHERE division_id IS NULL
        AND is_super_admin = 0
        AND lifecycle_status != 'pending_engagement';
      ```
      If non-zero: investigate before proceeding. Do NOT run fix:userdata
      until source of nulls is identified — Fix 5 will hard-delete them.

- [ ] **Orphaned red_cross_unit_id** — users pointing to inactive units:
      ```sql
      SELECT COUNT(*) FROM users u
      LEFT JOIN red_cross_units rcu ON rcu.id = u.red_cross_unit_id
      WHERE u.red_cross_unit_id IS NOT NULL
        AND (rcu.id IS NULL OR rcu.is_active = 0);
      ```
      Non-zero: note count; users can still save edits (edit exemption
      in place) but unit is invisible in active-unit reporting.

- [ ] **Orphaned membership_payments**:
      ```sql
      SELECT COUNT(*) FROM membership_payments mp
      LEFT JOIN users u ON u.id = mp.user_id
      WHERE u.id IS NULL AND mp.is_deleted = 0;
      ```

- [ ] **Orphaned activities / trainings** (should be 0 — Fix 5 guarded):
      ```sql
      SELECT COUNT(*) FROM activities a
      LEFT JOIN users u ON u.id = a.user_id
      WHERE u.id IS NULL AND a.is_deleted = 0;

      SELECT COUNT(*) FROM trainings t
      LEFT JOIN users u ON u.id = t.user_id
      WHERE u.id IS NULL AND t.is_deleted = 0;
      ```

- [ ] **Campaign recipients pointing to missing users**:
      ```sql
      SELECT COUNT(*) FROM messaging_recipients mr
      LEFT JOIN users u ON u.id = mr.user_id
      WHERE u.id IS NULL;
      ```

- [ ] **created_at integrity** — only seeded admins should carry today's
      date; everyone else 2016–2019:
      ```sql
      SELECT DATE(created_at) as d, COUNT(*) as n
      FROM users
      GROUP BY DATE(created_at)
      ORDER BY n DESC LIMIT 10;
      ```

- [ ] **Four-eyes integrity** — no super-admin self-approved records
      (all four should return 0):
      ```sql
      SELECT COUNT(*) FROM activities
      WHERE approved_by = entered_by
        AND approved_by IN (SELECT id FROM users WHERE is_super_admin = 1);

      SELECT COUNT(*) FROM trainings
      WHERE approved_by = entered_by
        AND approved_by IN (SELECT id FROM users WHERE is_super_admin = 1);

      SELECT COUNT(*) FROM donations
      WHERE approved_by = entered_by
        AND approved_by IN (SELECT id FROM users WHERE is_super_admin = 1);

      SELECT COUNT(*) FROM membership_payments
      WHERE approved_by = entered_by
        AND approved_by IN (SELECT id FROM users WHERE is_super_admin = 1);
      ```

### Lifecycle baseline

- [ ] **`lifecycle:reconcile --dry-run`** — record output. Non-zero
      discrepancies here are expected (pre-reconcile state). Document count.

- [ ] **`lifecycle_status` distribution**:
      ```sql
      SELECT lifecycle_status, COUNT(*) as n
      FROM users GROUP BY lifecycle_status;
      ```
      Record numbers — compare against post-reconcile.

---

## PHASE 2 — After `lifecycle:reconcile --apply`

- [ ] **Re-run `lifecycle:reconcile --dry-run`** — expect 0 discrepancies
      (real dates, no shift applied). Non-zero = investigate.
- [ ] **Re-check `lifecycle_status` distribution** — confirm meaningful
      active population. If large numbers remain `pending_engagement`,
      note that reconcile does not scan that status — decide if a separate
      pass is needed.
- [ ] **x-time-ago sanity** — spot-check 10–15 user profiles for future
      dates or absurd elapsed times. Production dates are real 2016–2019.
- [ ] **Stats snapshot consistency** — run `stats:backfill` and spot-check
      2–3 historical months. Note: lifecycle scope evaluates current state,
      not point-in-time — any delta is a known limitation, not a migration
      error. Document but do not treat as blocking.

---

## PHASE 3 — Final pre-go-live (VPS environment)

- [ ] **`APP_DEBUG=false`** live — hit a 404, confirm clean error page
      (no stack trace)
- [ ] **GD extension**: `php -m | grep gd`
- [ ] **images:migrate smoke test**: `php artisan images:migrate --limit=10`
- [ ] **Scheduled commands** in `routes/console.php`:
      - `lifecycle:reconcile --apply` at 03:00
      - `stats:snapshot` at 02:00
      - `firstaid:recalculate` at 02:30
      - Campaign send schedule (if applicable)
- [ ] **VPS crontab**: `crontab -l | grep artisan` — fires every minute
- [ ] **Queue worker** under Supervisor (if jobs queued):
      `supervisorctl status`
- [ ] **FirstAidTrainingTypesSeeder** — confirm `is_first_aid` flagged:
      ```sql
      SELECT COUNT(*) FROM training_types WHERE is_first_aid = 1;
      ```
      Expected: 10. Zero = seeder did not run; run before go-live.


⚠️  APP_KEY must never be rotated after ndpa:encrypt-national-ids has run
without first decrypting and re-encrypting all rows.
Back up APP_KEY separately from the .env file.

OBS OBS!! NRCS must review COMPLIANCE.md 
---

## PHASE 4 — Post-go-live (first 48 hours)

- [ ] **Next-morning stats snapshot** — confirm `stats:snapshot` ran and
      produced a row for today with plausible numbers
- [ ] **First scheduled reconcile** — confirm
      `lifecycle:reconcile --apply` ran cleanly
      (`storage/logs/laravel.log`)
- [ ] **Notification smoke test** — reject a test record; confirm bell
      dropdown shows notification with correct type (not falling through
      to generic fallback)
- [ ] **Mail delivery** — trigger one real notification; confirm arrival
      (validates MAIL_* end-to-end)
- [ ] **Delete UserTokenSeeder** — if re-run accidentally, all printed
      QR codes become invalid. Once production tokens are set, delete:
      `database/seeders/UserTokenSeeder.php`
      Add entry to DECISIONS.md when done.

---

## FUTURE FEATURE GATES

Complete before shipping the named feature — not blocking for go-live.

- [ ] **Delete-user UI** — before any UI action that hard-deletes users,
      implement a `deleting` observer on User that cleans up
      `taskforce_members` rows. FK constraints are commented out; no DB
      cascade exists. Failing to do this leaves dangling rows that corrupt
      taskforce stats and may block re-adds if IDs are reused.
- [ ] **Campaign archived-users warning** — add a yellow banner in the
      campaign review step when `archived_filter` is `'all'` or
      `'archived'`, alerting staff they are targeting lapsed users.

---

## Appendix — audit summary

All 7 categories complete.

| Category | Items | Fixes applied |
|---|---|---|
| 1. Data integrity | 7 | 2 (stat columns, BackfillStatsSnapshots approval filter) |
| 2. Migration sequence | 6 | 0 (all verified correct or N/A) |
| 3. Security | 5 | 3 (selectableForEntry ×6, permission whitelist, Fix 5 guard) |
| 4. Authorization | 3 | 2 (RCU active scope, branch-move destination) |
| 5. UI/UX regression | 3 | 0 (all verified correct) |
| 6. Environment/deployment | 5 | 0 (captured in Phase 3 above) |
| 7. Docs/runbook | 3 | 1 (docs/migration.md updated) |

**Deferred (not blocking go-live):**
- Stats snapshot consistency (Cat 1.4) — Phase 2 checklist item
- send_bulk_messages cleanup — Phase 0 decision item
- Campaign archived-users UI warning — future feature gate
- Delete-user observer — future feature gate
- Settings area restructure — consciously deferred
- Migration report refinements — consciously deferred
