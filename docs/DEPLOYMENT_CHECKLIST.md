# NRCS Laravel — Deployment Checklist

## Pre-flight Check 
[ ] Check any outstanding issues in PRE_DEPLOYMENT.md
[ ] Check any outstanding issues in one note

## Take the old system offline
CLARIFY 1: Is the new db going to be hosted on same VPS as the old?
CLARIFY 2: Does VPS run Apache or Nginx.
ADAPT procedure accordingly. This might need changes: 
- [ ] Locate the old system's document root on the VPS (the folder the
  domain currently points to)
- [ ] Create a simple static holding page there, e.g. `maintenance.html`,
  with a "Back soon, we're migrating" message
- [ ] Add an `.htaccess` rule in that document root redirecting all traffic
  to the holding page — except your own IP, if you want to keep testing
  the old system briefly (e.g. to re-verify data before wiping it)
- [ ] Confirm: visit the domain in a normal browser tab — should show the
  holding page, not the old app
- [ ] Leave this in place for the entire migration + deploy process below

## Database

### Migration
- [ ] Download old database to local environment
- [ ] php artisan key:generate and put APP_KEY on local .env  + NIN_HASH_KEY
- [ ] MySQL time zone: both the app and the legacy (`old_db`) connections
  set the MySQL session time zone to `+01:00` (`DB_TIMEZONE` in
  config/database.php, default `+01:00` = Africa/Lagos, no summer time).
  Leave `DB_TIMEZONE` unset in .env unless there is a reason. Check before
  the import:
  `php artisan tinker --execute='echo DB::select("select @@session.time_zone tz")[0]->tz;'`
  must print `+01:00`. (A local import once ran while MySQL was on a
  European zone with summer time, which put legacy `decided_at` values one
  hour early for summer dates; left as it is by decision, 2026-10-11.)
- [ ] Do the data migration procedure in docs/migration.md, section
  "Run (in order)", part A, step by step. In short:
  1. time zone check (`+01:00`)
  2. `php artisan migrate:fresh`
  3. `php -d memory_limit=2048M artisan migrate:old-db --table=all` —
     this runs **db:seed itself** and, last,
     `payments:backfill-manual-shares` (Income Distribution Tab 2 shares on
     the imported manual payments; its report must list no payments that
     "could not be filled")
  4. `php artisan migrate` → "Nothing to migrate"
  5. phone duplicate clean-up
  6. `php artisan db:seed --class=UserTokenSeeder`
  7. `php artisan stats:backfill --from=2018-06-01`
  8. photos (`php artisan images:migrate`)
  9. `php artisan lifecycle:reconcile --apply`
  10. final checks: `php artisan payments:backfill-manual-shares --dry-run`
      → "Nothing to fill"; `php artisan divisions:check-coordinates` →
      review; encrypt check; the data integrity checks below
- [ ] ⚠️ Never run `php artisan db:seed` without `--class` by hand — it is
  already part of migrate:old-db, and after go-live it would reset admin
  edits (most settings, campaign purpose templates, role permissions,
  branch/division coordinates, first-aid training types). Only the single
  seeders named in this checklist / docs/migration.md, with `--class`.
- [ ] Legacy Income Distribution shares use the default percentages
  (25/25/50; a payment without a division gives the division's share to
  the branch). If NRCS wants other percentages for the comparison
  quarters, decide that before the import — stored shares are never
  recalculated.


### Data integrity checks


- [ ] Check that superadmin accounts were created.
- [ ] Set training validity years 1->3 years
- [ ] Clarify which fee categories are for volunteers (maybe bronze, silver, gold for influencers?)

```sql
      SELECT COUNT(*) FROM users
      WHERE division_id IS NULL
        AND is_super_admin = 0
        AND lifecycle_status != 'pending_engagement';
```
      Non-zero: **do not** run `fix:userdata` — Fix 5 hard-deletes users
      with NULL `division_id` AND `organisation_id` unconditionally.
      Identify the source of NULLs first; the restore pass must complete
      before Fix 5 runs.

- [ ] **Orphaned `red_cross_unit_id`** (users pointing to inactive units):
```sql
      SELECT COUNT(*) FROM users u
      LEFT JOIN red_cross_units rcu ON rcu.id = u.red_cross_unit_id
      WHERE u.red_cross_unit_id IS NOT NULL
        AND (rcu.id IS NULL OR rcu.is_active = 0);
```
      Non-zero: users can still edit (exemption in place) but the unit is
      invisible in active-unit reporting.

- [ ] **Orphaned `membership_payments`**:
```sql
      SELECT COUNT(*) FROM membership_payments mp
      LEFT JOIN users u ON u.id = mp.user_id
      WHERE u.id IS NULL AND mp.is_deleted = 0;
```

- [ ] **Orphaned activities / trainings** (expect 0 — Fix 5 guarded):
```sql
      SELECT COUNT(*) FROM activities a
      LEFT JOIN users u ON u.id = a.user_id
      WHERE u.id IS NULL AND a.is_deleted = 0;

      SELECT COUNT(*) FROM trainings t
      LEFT JOIN users u ON u.id = t.user_id
      WHERE u.id IS NULL AND t.is_deleted = 0;
```

- [ ] **`created_at` integrity** — only seeded admins should carry today's
  date; everyone else 2016–2019:
```sql
      SELECT DATE(created_at) as d, COUNT(*) as n
      FROM users GROUP BY DATE(created_at) ORDER BY n DESC LIMIT 10;

- [ ] mysql -u root -e "USE redcross_volunteer; SELECT
  SUM(CASE WHEN personal_info IS NOT NULL AND personal_info NOT LIKE 'eyJpdiI6%' THEN 1 ELSE 0 END) AS personal_info_unencrypted,
  SUM(CASE WHEN national_id_number IS NOT NULL AND national_id_number NOT LIKE 'eyJpdiI6%' THEN 1 ELSE 0 END) AS national_id_unencrypted
FROM users;"
Expected result: both columns show 0.

  07060683265 (DB-273857)
For DB-273857: since it holds a finance role and would be the one archived, don't run it through the automated tool. Have someone who knows this person look at both accounts directly — compare the winner and DB-273857 by hand (name, branch, activity) to confirm they really are the same person, then decide whether to manually merge (moving DB-273857's finance role onto the winner, if needed) or leave both as separate legitimate accounts. Not worth automating for one case


```
### Upload DB to VPS
- [ ] Upload the migrated database to NRCS VPS
- [ ] Spot-check a few row counts (users, activities, trainings) — confirm
  nothing got lost or truncated in transfer

## Laravel files

### Deploy files 
- [ ] Deploy the new Laravel app files to a NEW folder on the VPS
- [ ] Copy the app code to the VPS
- [ ] Install dependencies (`composer install`)
- [ ] Set correct file/folder permissions (`storage/`, `bootstrap/cache/`)
- [ ] Run Laravel schema migrations: `php artisan migrate`
- [ ] Copy the .env used on dev.itacenmu.org (or use .env.example)  Remember: php artisan config:cache


### Set .env VPS
--> Remember: after changing .env: php artisan config:cache
- [ ] APP_ENV=production
- [ ] APP_DEBUG=false
- [ ] Diff VPS `.env` against local: MAIL_*, APP_KEY, IMAGE_MIGRATION_SOURCE,
  ElevenLabs/analytics keys, queue driver
- [ ] `super_admin_emails` set in `.env`
- php artisan db:seed --class=SuperAdminSeeder  (this command also checks if the emails set in .env are not alreade in users-email, sec.gen@redcross.org might have opened and account with that mail. If this happens change the official email for that account to his personal email. )
- [ ] `NRCS_DB_MIGRATION_DATE=2026-08-01` set in `.env`
- [ ] **APP_KEY**:
    - COPY FROM LOCAL .env, the one used during migration.
    - ⚠️ This key protects more than national IDs. Once real data exists,
      rotating it will:
        - Make `national_id_number` and `personal_info` unreadable
          (encrypted columns) unless decrypted and re-encrypted first
        - Log out every active user and break in-flight CSRF tokens
        - Permanently invalidate the QR code on any certificate already
          printed or exported as PDF (signed verification links can't be
          regenerated for a document that's already out the door)
    - [ ] Back up APP_KEY in a secure password manager, separate from `.env`.
- [ ] Set IMAGE_MIGRATION_SOURCE in .env — this is read by config/services.php and MigrateUserImages.php but has never actually been configured in any environment. Confirm the expected value/format by checking MigrateUserImages.php before setting it."
- [ ] Set NRCS_DB_MIGRATION_DATE
- [ ] `MANUAL_GO_LIVE_DATE` (Income Distribution, manual payments): leave
  it empty until the day manual (branch-collected) payments start being
  shared out for real. On that go-live day set it to that date
  (`MANUAL_GO_LIVE_DATE=YYYY-MM-DD`), then `php artisan config:cache`.
  Quarters before it are shown as estimates; never change it afterwards.
- [ ] Do not set `DB_TIMEZONE` (the default `+01:00` is right). After
  config:cache, check the VPS connection prints `+01:00`:
  `php artisan tinker --execute='echo DB::select("select @@session.time_zone tz")[0]->tz;'`
  A mysqldump/restore keeps TIMESTAMP values correct on its own (the dump
  is written in UTC), so imported times read the same on the VPS.
- php artisan config:clear
- php artisan config:cache

### Final checks on the server (in this order)
- [ ] `php artisan tinker --execute='echo DB::select("select @@session.time_zone tz")[0]->tz;'` → `+01:00`
- [ ] `php artisan migrate --force` → "Nothing to migrate" (or only
  migrations newer than the import)
- [ ] `php artisan db:seed --class=SuperAdminSeeder` (see above; the only
  seeder to run on the server)
- [ ] If `PermissionsTableSeeder` is ever run on the server: always after
  `php artisan migrate`. It stops with an error if migration
  `2026_10_17_120000_move_branch_financial_approval_to_direct_permissions`
  has not run (it would otherwise take financial approval away from every
  branch DB administrator).
- [ ] `php artisan payments:backfill-manual-shares --dry-run` → "Nothing to fill"
- [ ] `php artisan divisions:check-coordinates` → the same list as on the
  migration machine
- [ ] Spot-check row counts (users, activities, trainings, membership_payments)

### Server imported before organisations had a division (sandbox only)
Skip after a fresh import. Details: docs/migration.md, section C.
- [ ] Locally: `php artisan organisations:fill-divisions --export=storage/app/organisation-divisions.csv`
- [ ] Copy it to the server's `storage/app/` (e.g. `scp storage/app/organisation-divisions.csv <user>@<vps>:/var/www/[actual-path]/storage/app/`)
- [ ] On the server, after deploying the code: `php artisan migrate --force`
- [ ] `php artisan organisations:fill-divisions --file=storage/app/organisation-divisions.csv --dry-run` → review the report
- [ ] `php artisan organisations:fill-divisions --file=storage/app/organisation-divisions.csv` → listed ids (if any): set their division by hand
- [ ] `rm storage/app/organisation-divisions.csv`

### Other
  [ ] Set cron jobs (as www-data user):
  `* * * * * cd /var/www/[actual-path] && /usr/bin/php artisan schedule:run >> /var/log/laravel-scheduler.log 2>&1`



## Point the domain at the new Laravel app
- [ ] Update the web server's virtual host / site config so the domain's
  document root points at the new Laravel app's `public/` folder
  (not the app root — Laravel's entry point is always inside `public/`)
- [ ] Reload the web server config (e.g. `sudo systemctl reload apache2`
  or `nginx`, whichever this VPS runs)
- [ ] Visit the domain in a normal browser tab — should now show the new
  Laravel app, not the old PHP site or the holding page
- [ ] Confirm the old holding-page redirect no longer intercepts anything

## Retire the old system
- [ ] Close the old NRCS database's picture and signature folders from
  public access
- [ ] Confirm a safety copy of the old database exists on localhost (or
  elsewhere) BEFORE deleting anything on the VPS
- [ ] Delete the old database on the VPS
- [ ] Archive the old PHP code folder somewhere for reference — low risk,
  fine to keep long-term

## Housekeeping

### On the new database site
[ ] Training types. Check is_first_aid is applied correctly
[ ] Training types. Check if expiry dates are set correctly. Probably change 1 year to 3 years for first aid training. 
[ ] NRCS must review `COMPLIANCE.md`

- [ ] Zero users with >1 role (check Database Team report, or query).
- [ ] Set fees for Organisations.
- [ ] What is the maximum upload file size for images on the VPS? (had problem with itacenmu.org treeplanting app, when user uploaded selfie)
- [ ] Check e.g. heat map and see if any division has missing coordinates


### 24 & 36 hours after deployment
[ ] Check laravel log and see if cron job is working.
[ ] Delete `UserTokenSeeder` once production tokens are set — if re-run
accidentally, all printed QR codes become invalid.
`database/seeders/UserTokenSeeder.php`

