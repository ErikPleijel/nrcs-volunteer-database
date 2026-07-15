<?php

namespace App\Console\Commands\OldDbMigration;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class MigrateLegacyUserPermissionsFromOldDb extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'permissions:migrate-legacy-from-old
                            {--chunk=500 : Number of records to process per chunk}
                            {--dry-run : Run without writing to the database}';

    /**
     * The console command description.
     */
    protected $description = 'Assign division roles to users based on legacy Auth_* fields â€” skips users who already hold a senior branch role';

    public function handle(): int
    {
        $chunk  = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('ðŸ” Starting legacy role migration from old_db.Persons...');

        if ($dryRun) {
            $this->warn('DRY RUN â€“ no changes will be written to the database');
        }

        // Role names we need
        $roleNames = [
            'division_db_assistant_finance',
            'division_db_assistant_operations',
        ];

        // Fetch roles from new DB
        $roles = Role::whereIn('name', $roleNames)->get()->keyBy('name');

        $missingRoles = array_diff($roleNames, $roles->keys()->toArray());
        if (!empty($missingRoles)) {
            $this->error('âŒ Missing roles in roles table: ' . implode(', ', $missingRoles));
            $this->error('Create these roles first, then rerun this command.');
            return self::FAILURE;
        }

        // Count persons with ANY of the auth flags set
        $personsQuery = DB::connection('old_db')->table('Persons')
            ->where(function ($q) {
                $columns = [
                    'Auth_membership_payment',
                    'Auth_donations',
                    'Auth_volunteering',
                    'Auth_training',
                ];

                foreach ($columns as $column) {
                    $q->orWhere($column, 1)
                        ->orWhere($column, true)
                        ->orWhere($column, '1');
                }
            });

        $total = $personsQuery->count();

        if ($total === 0) {
            $this->warn('âš ï¸ No Persons found with any Auth_* flags set in old_db.Persons');
            return self::SUCCESS;
        }

        $this->info("ðŸ“Š Found {$total} records in old_db.Persons with legacy auth flags.");
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $assignedNoRole    = 0;  // had no role â†’ division role assigned
        $assignedSuperseded = 0; // had only branch_db_assistant â†’ replaced with division role
        $alreadyCorrect    = 0;  // already holds the intended division role â†’ no-op
        $skippedConflict   = 0;  // holds senior/conflicting role â†’ skipped, needs review
        $skippedNoUser     = 0;  // no matching user in new DB

        $needsReview = [];

        $seniorBranchRoles = ['branch_secretary', 'branch_db_administrator'];

        $personsQuery
            ->orderBy('PersonID')
            ->chunk($chunk, function ($persons) use (
                $roles,
                $progressBar,
                $seniorBranchRoles,
                &$assignedNoRole,
                &$assignedSuperseded,
                &$alreadyCorrect,
                &$skippedConflict,
                &$skippedNoUser,
                &$needsReview,
                $dryRun
            ) {
                foreach ($persons as $person) {
                    // Find corresponding user in NEW DB
                    $user = User::find($person->PersonID);

                    if (!$user) {
                        $skippedNoUser++;
                        $progressBar->advance();
                        continue;
                    }

                    // Determine which role to assign (unchanged logic)
                    $hasMembershipPayment = $this->isTruthy($person->Auth_membership_payment ?? null);
                    $hasDonations         = $this->isTruthy($person->Auth_donations ?? null);
                    $hasVolunteering      = $this->isTruthy($person->Auth_volunteering ?? null);
                    $hasTraining          = $this->isTruthy($person->Auth_training ?? null);

                    // Finance role if any finance-related flag
                    if ($hasMembershipPayment || $hasDonations) {
                        $role = $roles['division_db_assistant_finance'];
                    }
                    // Operations role only if no finance flags, but operations-related flags exist
                    elseif ($hasVolunteering || $hasTraining) {
                        $role = $roles['division_db_assistant_operations'];
                    } else {
                        // No qualifying flag combination â€” should not happen given the query, but guard
                        $progressBar->advance();
                        continue;
                    }

                    // Classify by current role state and act accordingly
                    $currentRoles = $user->getRoleNames()->toArray();

                    $hasSeniorBranch    = count(array_intersect($currentRoles, $seniorBranchRoles)) > 0;
                    $hasBranchAssistant = in_array('branch_db_assistant', $currentRoles, true);
                    $hasIntendedRole    = in_array($role->name, $currentRoles, true);

                    if (empty($currentRoles)) {
                        // No role at all â†’ safe to assign
                        if (!$dryRun) {
                            $user->syncRoles([$role->name]);
                        }
                        $assignedNoRole++;

                    } elseif ($hasBranchAssistant && count($currentRoles) === 1) {
                        // Holds only branch_db_assistant â†’ supersede with division role (Group A rule)
                        if (!$dryRun) {
                            $user->syncRoles([$role->name]);
                        }
                        $assignedSuperseded++;

                    } elseif ($hasSeniorBranch) {
                        // Holds branch_secretary or branch_db_administrator â†’ do not touch (Group B rule)
                        $needsReview[] = [
                            'user_id'          => $user->id,
                            'name'             => $user->first_name . ' ' . $user->last_name,
                            'current_roles'    => implode(', ', $currentRoles),
                            'intended_role'    => $role->name,
                            'reason'           => 'senior branch role',
                        ];
                        $skippedConflict++;

                    } elseif ($hasIntendedRole && count($currentRoles) === 1) {
                        // Already holds exactly the intended division role â†’ idempotent no-op
                        $alreadyCorrect++;

                    } else {
                        // Unexpected combination (different division role, multiple roles, etc.)
                        $needsReview[] = [
                            'user_id'          => $user->id,
                            'name'             => $user->first_name . ' ' . $user->last_name,
                            'current_roles'    => implode(', ', $currentRoles),
                            'intended_role'    => $role->name,
                            'reason'           => 'unexpected role combination',
                        ];
                        $skippedConflict++;
                    }

                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('ðŸŽ‰ Legacy roles migration completed.');
        $this->table(['Metric', 'Count'], [
            ['Total Persons with flags',                          $total],
            ['Users assigned (no prior role)',                    $assignedNoRole],
            ['Users assigned (superseded branch_db_assistant)',   $assignedSuperseded],
            ['Users already correct (idempotent no-op)',          $alreadyCorrect],
            ['Users skipped â€” needs review (senior/conflicting)', $skippedConflict],
            ['Persons without matching user',                     $skippedNoUser],
        ]);

        if (!empty($needsReview)) {
            $this->newLine();
            $this->warn('âš ï¸  The following users were NOT modified and need manual review:');
            $this->table(
                ['User ID', 'Name', 'Current Role(s)', 'Intended Role (not assigned)', 'Reason'],
                array_map(fn($r) => [
                    $r['user_id'],
                    $r['name'],
                    $r['current_roles'],
                    $r['intended_role'],
                    $r['reason'],
                ], $needsReview)
            );
        }

        if ($dryRun) {
            $this->warn('This was a DRY RUN â€“ no roles were changed.');
            $this->line('â„¹ï¸  Run without --dry-run to apply, then run: php artisan permission:cache-reset');
        } else {
            $this->call('permission:cache-reset');
            $this->line('âœ… Permission cache reset.');
        }

        return self::SUCCESS;
    }

    /**
     * Convert legacy "truthy" values to boolean.
     */
    protected function isTruthy($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
