Migration procedure

## Pre-flight
- [ ] Back up production DB

PREPARE OLD DB:
- php artisan debug:duplicate-emails          # diagnostic
  php artisan users:handle-duplicates --table=persons --dry-run   # review plan
  php artisan users:handle-duplicates --table=persons             # execute
  php artisan debug:duplicate-emails          # confirm clean
 

## Run (in order)

The import runs on the migration machine (local); the finished database is
then uploaded to the new server (DEPLOYMENT_CHECKLIST.md, "Upload DB to VPS").

### A. On the migration machine

1. Time zone check — must print `+01:00` (both DB connections pin the MySQL
   session time zone; see DEPLOYMENT_CHECKLIST.md):
   --> php artisan tinker --execute='echo DB::select("select @@session.time_zone tz")[0]->tz;'

2. Empty schema (runs every migration; the data migrations for division
   names and the Okeigbo merge find no rows yet — the import applies those
   corrections itself):
   --> php artisan migrate:fresh

3. Legacy import — one command does the whole sequence:
   --> php -d memory_limit=2048M artisan migrate:old-db --table=all
   (for a test run: add --limit=15000)
   NOTES while migrating:
    - You have to type "Yes" when it reaches Migrate Branches (the second step).
    - When migrating users, check users in phpMyAdmin to see progress.
   It runs, in this order: RolesTableSeeder, branches, divisions (with the
   name corrections and merges), positions, signature titles, activity /
   training / task force types, membership fees, users,
   **users:fix-phone-letter-o --commit** (letter O typed for 0 in
   telephone1/2; before step 5 so those numbers can join their
   duplicate groups), legacy roles,
   ID card prints, public contacts, organisations, red cross units, task
   forces (+ members), activities, trainings, donations, membership
   payments, **db:seed** (the full DatabaseSeeder), permission:cache-reset,
   last admin activity, lifecycle:reconcile, fix:userdata,
   lifecycle:reconcile again, the first-aid date backfill, and LAST
   **payments:backfill-manual-shares** (income shares on the imported
   manual payments, accounting date = payment date, the Fee distribution
   percentages in force — the default 25/25/50 on a fresh database, a
   payment without a division giving the division's share to the branch;
   stored once, never recalculated). The organisations step sets each
   organisation's division from the legacy DivisionID; any it cannot
   trust are listed as warnings ("division_id left empty").
   Check at the end: the backfill's report must list no payments that
   "could not be filled".

   ⚠️ db:seed is part of this command. Do NOT run `php artisan db:seed`
   (without --class) by hand — not now, and never after go-live: it resets
   admin edits (most settings, campaign purpose templates, role
   permissions, branch/division coordinates, first-aid training types).
   Run a single seeder with --class only where this document says so.

4. Nothing left to migrate (expect "Nothing to migrate"):
   --> php artisan migrate

5. Telephone duplicate clean-up. Handles & archives some, but not all duplications:
   --> php artisan users:report-phone-duplicates
   Small spot-check with --commit: pick 3-5 real phone numbers from the report
   output that cover a mix of cases: a simple 2-account merge, a case with
   moved records, and a role-holder group if one exists.
   --> php artisan users:archive-phone-duplicates --phones="<comma-separated numbers>" --commit
   --> php artisan users:archive-phone-duplicates --limit=25 --commit
   Re-run the report, confirm expected drop in actionable groups, check for surprises.
   --> php artisan users:archive-phone-duplicates --limit=250 --commit
   Re-run the report again.
   --> php artisan users:archive-phone-duplicates --commit
   Full run, check the report again.

6. ID check tokens (fills only missing tokens; existing ones are kept):
   --> php artisan db:seed --class=UserTokenSeeder
   CHECK: SELECT COUNT(*) FROM users WHERE id_check_token IS NULL;  → 0

7. Historical statistics:
   --> php artisan stats:backfill --from=2018-06-01
   Check the Membership & Volunteers graphs on the dashboard; if they are
   straight lines, do this step later.

8. Photos (check the file first and run a small batch to test; this needs
   some prep, it has never been tested on the NRCS VPS):
   --> php artisan images:migrate
   --> Close the old nrcs database picture and signature folders from public access!
   (ndpa:encrypt-national-ids is NOT needed: the old DB holds no NINs.)

9. Lifecycle once more (the import ran it, but the phone-duplicate merges
   in step 5 changed users after that):
   --> php artisan lifecycle:reconcile --apply

10. Final checks before upload:
   --> php artisan payments:backfill-manual-shares --dry-run
       → "Nothing to fill" (every imported manual payment has its shares)
   --> php artisan divisions:check-coordinates
       → review the list. Until the coordinates fix is done it reports the
         known suspects (and exits with failure); new names on the list
         mean something changed in the import.
   --> Encrypt check (below), then the "Data integrity checks" in
       DEPLOYMENT_CHECKLIST.md.

### B. On the new server (after the database is uploaded)

Follow DEPLOYMENT_CHECKLIST.md ("Upload DB to VPS", "Deploy files", "Set
.env VPS"). The order of commands there:
   --> php artisan config:cache                     (after setting .env; do not set DB_TIMEZONE)
   --> php artisan tinker --execute='echo DB::select("select @@session.time_zone tz")[0]->tz;'
       → +01:00
   --> php artisan migrate --force                  → "Nothing to migrate" (or only migrations newer than the import)
   --> php artisan db:seed --class=SuperAdminSeeder (the only seeder to run here)
   --> php artisan payments:backfill-manual-shares --dry-run   → "Nothing to fill"
   --> php artisan divisions:check-coordinates      → same list as on the migration machine
   --> spot-check row counts (users, activities, trainings, membership_payments)
   --> MANUAL_GO_LIVE_DATE stays empty in .env until manual payments go live.
       On go-live day: set MANUAL_GO_LIVE_DATE=YYYY-MM-DD (that day), then
       php artisan config:cache. Never change it afterwards.

### C. A server imported BEFORE organisations had a division (the sandbox)

Not needed after a fresh import (the import sets organisations.division_id
itself). On a database imported earlier, the division is filled from the
legacy persons.DivisionID without re-importing. The server usually cannot
reach the legacy database, so the data travels as a file.

1. On the migration machine (needs the legacy database):
   --> php artisan organisations:fill-divisions --export=storage/app/organisation-divisions.csv
       → "Wrote 1003 legacy organisations …" (storage/app is git-ignored)
2. Copy the file into the server's storage/app/, e.g.
   --> scp storage/app/organisation-divisions.csv <user>@<vps>:/var/www/[actual-path]/storage/app/
3. On the server, in the app folder, after deploying the new code:
   --> php artisan migrate --force                  (adds organisations.division_id)
   --> php artisan organisations:fill-divisions --file=storage/app/organisation-divisions.csv --dry-run
   --> php artisan organisations:fill-divisions --file=storage/app/organisation-divisions.csv
   Organisations are matched on id and only filled when name and branch
   match the file and the division is in the organisation's branch. Every
   other case is listed by id and left alone (the run then exits with
   failure): set those divisions by hand on the organisation's edit page.
   Organisations created on the server after the import show up as "Not in
   the file". A second run reports everything as "Already had this
   division". Each change is in the Audit Log (organisation_division_filled).
   --> rm storage/app/organisation-divisions.csv

Encrypt check: - [ ] mysql -u root -e "USE redcross_volunteer; SELECT
SUM(CASE WHEN personal_info IS NOT NULL AND personal_info NOT LIKE 'eyJpdiI6%' THEN 1 ELSE 0 END) AS personal_info_unencrypted,
SUM(CASE WHEN national_id_number IS NOT NULL AND national_id_number NOT LIKE 'eyJpdiI6%' THEN 1 ELSE 0 END) AS national_id_unencrypted
FROM users;"
Expected result: both columns show 0.


TODO IMPORTANT, after final production migration, go to UserTokenSeeder, and delete file. If seeder run accidentally, the printed ID cards will be useless!

ONLY ON DEV, not real migration! php artisan dates:shift 2360 + days from 1 jan 2026
php artisan dates:shift 2560
?? run   php artisan lifecycle:reconcile --apply
