Migration procedure

## Pre-flight
- [ ] Back up production DB

PREPARE OLD DB:
- php artisan debug:duplicate-emails          # diagnostic
  php artisan users:handle-duplicates --table=persons --dry-run   # review plan
  php artisan users:handle-duplicates --table=persons             # execute
  php artisan debug:duplicate-emails          # confirm clean

- [] Run SELECT * FROM `persons` where DivisionID is null; Are there any - if so what to check if they are imortant (Roles, Records, have login). If not: delete.  

## Run (in order)
Migration, seeding
--> Php artisan migrate:fresh
--> php artisan migrate:old-db --table=all ((--limit=15000))
NOTES while migrating: 
 - You have to type "Yes" when it reaches Migrate Branches (the second step)
 - When migrating users, Check users phpMyAdmin to see progress.
 - migrate:organisations now runs automatically as part of this step. 
--> SELECT * FROM `users` where division_id is null; the migration might have missed some division_id; if not too many set manually. 


--> php artisan fix:userdata  (VARIOUS SQL RUN)
--> php artisan db:seed --class=UserTokenSeeder (may need to run several times, CHECK: SELECT * FROM `users` where id_check_token is null; should be 0)

--> Photo migration (check file first and run a small batch to test, this will need some prep before running, since it has never been tested on nrcs vps): php artisan images:migrate
--> Close the old nrcs database picture and signature folders from public access!
migrate (runs all pending migrations including the column widen)
(→ ndpa:encrypt-national-ids  (NOT needed since no nin in old db, ))

--> Backfill historical stats data: php artisan stats:backfill --from=2018-06-01
Check Membership & Volunteers graphs on dashboard if straight lines do this step later. 
--> Check divisions: any coordinate missing? (Nasarawa?)

[ ] Run php artisan lifecycle:reconcile --apply

TODO IMPORTANT, after final production migration, go to UserTokenSeeder, and delete file. If seeder run accidentally, the printed ID cards will be useless!



