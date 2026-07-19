# NRCS Laravel — Deployment Checklist


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
- [ ] Do data migration procedure in MIGRATION.md

### Data integrity checks

- [ ] **`division_id` NULL count** — expect 0. In rehearsal, 373 users had
  NULL `division_id` after `migrate:users`; a built-in restore pass
  inside `migrate:users` recovered all of them, so 0 is expected on a
  clean production run.
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


### Set .env VPS
- [ ] APP_ENV=production
- [ ] Diff VPS `.env` against local: MAIL_*, APP_KEY, IMAGE_MIGRATION_SOURCE,
  ElevenLabs/analytics keys, queue driver
- [ ] `super_admin_emails` set in `.env`
- [ ] `NRCS_DB_MIGRATION_DATE=2026-08-01` set in `.env`
- [ ] **APP_KEY**:
    - Generate fresh per environment: `php artisan key:generate` — never
      copy a key from sandbox or local into production.
    - ⚠️ This key protects more than national IDs. Once real data exists,
      rotating it will:
        - Make `national_id_number` and `personal_info` unreadable
          (encrypted columns) unless decrypted and re-encrypted first
        - Log out every active user and break in-flight CSRF tokens
        - Permanently invalidate the QR code on any certificate already
          printed or exported as PDF (signed verification links can't be
          regenerated for a document that's already out the door)
    - [ ] Back up APP_KEY in a secure password manager, separate from `.env`.
- php artisan config:clear
- php artisan config:cache

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

