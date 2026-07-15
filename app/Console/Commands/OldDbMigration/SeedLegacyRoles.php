<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\User;
use Spatie\Permission\Models\Role;

class SeedLegacyRoles extends Command
{
    protected $signature = 'seed:legacy-roles
                            {--chunk=500 : Number of records to process per chunk}
                            {--dry-run : Run without making changes}';

    protected $description = 'Seed model_has_roles table from users legacy_role field';

    private array $roleMapping = [
        'National DB Administrator' => 'national_db_administrator',
        'Branch Secretary' => 'branch_secretary',
        'Branch Secreteray' => 'branch_secretary', // Handle the typo
        'Branch DB Administrator' => 'branch_db_administrator',
        'Observer National Level' => 'observer_national_level',
        'National DB Assistant' => 'national_db_assistant',
        'Branch DB Assistant' => 'branch_db_assistant',


    ];

    public function handle()
    {
        $chunk = $this->option('chunk');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸŽ­ Starting model_has_roles seeding from legacy_role field...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        try {
            // Get users with legacy_role field
            $usersWithLegacyRoles = User::whereNotNull('legacy_role')
                ->where('legacy_role', '!=', '')
                ->get();

            if ($usersWithLegacyRoles->isEmpty()) {
                $this->warn('âš ï¸ No users found with legacy_role field');
                return Command::SUCCESS;
            }

            $totalCount = $usersWithLegacyRoles->count();
            $assignedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $unmappedRoles = [];

            $this->info("ðŸ“Š Found {$totalCount} users with legacy roles to process");

            // Show role mapping
            $this->info('ðŸ—ºï¸ Role Mapping:');
            $this->table(['Legacy Role', 'New Role'], collect($this->roleMapping)->map(function($newRole, $legacyRole) {
                return [$legacyRole, $newRole];
            })->toArray());

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            $usersWithLegacyRoles->chunk($chunk)->each(function ($users) use (&$assignedCount, &$skippedCount, &$errorCount, &$unmappedRoles, $progressBar, $dryRun) {
                foreach ($users as $user) {
                    try {
                        $legacyRole = $user->legacy_role;

                        // Check if we have a mapping for this legacy role
                        if (!isset($this->roleMapping[$legacyRole])) {
                            if (!in_array($legacyRole, $unmappedRoles)) {
                                $unmappedRoles[] = $legacyRole;
                            }
                            $skippedCount++;
                            $progressBar->advance();
                            continue;
                        }

                        $newRoleName = $this->roleMapping[$legacyRole];

                        // Find the role by name
                        $role = Role::where('name', $newRoleName)->first();
                        if (!$role) {
                            $this->error("Role '{$newRoleName}' not found in roles table");
                            $errorCount++;
                            $progressBar->advance();
                            continue;
                        }

                        // Idempotent no-op: user already holds exactly this one mapped role.
                        if ($user->hasRole($newRoleName) && $user->getRoleNames()->count() === 1) {
                            $skippedCount++;
                            $progressBar->advance();
                            continue;
                        }

                        // syncRoles replaces ALL of the user's roles with this single mapped role.
                        // This is intentional: legacy_role is the authoritative single role from
                        // the old system, and replace semantics prevent role stacking.
                        if (!$dryRun) {
                            $user->syncRoles([$newRoleName]);
                        }

                        $assignedCount++;

                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("Failed to process user ID {$user->id}: " . $e->getMessage());
                    }

                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine(2);

            // Show unmapped roles
            if (!empty($unmappedRoles)) {
                $this->warn('âš ï¸ Unmapped Legacy Roles Found:');
                foreach ($unmappedRoles as $unmappedRole) {
                    $this->line("  - {$unmappedRole}");
                }
            }

            // Show results
            $this->info('ðŸŽ‰ Role assignment completed!');
            $this->table(['Metric', 'Count'], [
                ['Total Users Processed', $totalCount],
                ['Roles Successfully Assigned', $assignedCount],
                ['Skipped (Already Assigned/No Mapping)', $skippedCount],
                ['Errors', $errorCount],
                ['Success Rate', $totalCount > 0 ? round(($assignedCount / $totalCount) * 100, 2) . '%' : '0%']
            ]);

            if ($dryRun) {
                $this->warn('This was a DRY RUN - no data was actually updated');
                $this->line('â„¹ï¸  Run without --dry-run to apply, then: php artisan permission:cache-reset');
            } else {
                $this->call('permission:cache-reset');
                $this->line('âœ… Permission cache reset.');
            }

            $this->showRoleAssignmentStatistics($dryRun);

        } catch (\Exception $e) {
            $this->error('âŒ Role assignment failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Show statistics about role assignments
     */
    private function showRoleAssignmentStatistics($dryRun = false)
    {
        if ($dryRun) {
            // For dry run, show what would be assigned
            $this->info('ðŸ“Š Legacy Role Distribution (would be assigned):');

            $roleStats = User::whereNotNull('legacy_role')
                ->where('legacy_role', '!=', '')
                ->select('legacy_role', DB::raw('COUNT(*) as user_count'))
                ->groupBy('legacy_role')
                ->orderByDesc('user_count')
                ->get();

        } else {
            // Show actual assignments from model_has_roles table
            $this->info('ðŸ“Š Current Role Assignments:');

            $roleStats = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('roles.name as role_name', DB::raw('COUNT(*) as user_count'))
                ->where('model_has_roles.model_type', User::class)
                ->groupBy('roles.name')
                ->orderByDesc('user_count')
                ->get();
        }

        if ($roleStats->isEmpty()) {
            $this->line('  - No role assignments found');
            return;
        }

        $tableData = [];
        foreach ($roleStats as $stat) {
            $roleName = $dryRun ? $stat->legacy_role : $stat->role_name;
            $tableData[] = [$roleName, $stat->user_count];
        }

        $this->table([$dryRun ? 'Legacy Role' : 'Assigned Role', 'User Count'], $tableData);

        // Show total assignments
        $totalAssignments = $dryRun
            ? $roleStats->sum('user_count')
            : DB::table('model_has_roles')->where('model_type', User::class)->count();

        $this->line("  - Total role assignments: {$totalAssignments}");
    }
}
