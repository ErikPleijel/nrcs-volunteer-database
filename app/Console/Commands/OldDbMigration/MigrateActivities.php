<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MigrateActivities extends Command
{
    protected $signature = 'migrate:activities
                            {--chunk=1000 : Number of records to process per chunk}
                            {--clear : Clear existing activities before migration}
                            {--dry-run : Run without making changes}
                            {--skip-validation : Skip foreign key validation}';

    protected $description = 'Migrate activities from old database, preserving ActivityID as id (assignable_type/id)';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');
        $skipValidation = $this->option('skip-validation');

        $this->info('ðŸšš Starting activities migration (polymorphic assignable)...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing activities. Continue?')) {
            DB::table('activities')->delete();
            $this->info('âœ… Existing activities cleared');
        }

        // Check if activities table exists
        if (!Schema::hasTable('activities')) {
            $this->error('âŒ Activities table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        // Validate related tables exist (unless skipped)
        if (!$skipValidation) {
            if (!Schema::hasTable('activity_types')) {
                $this->error('âŒ Activity types table does not exist. Please migrate activity types first.');
                return Command::FAILURE;
            }
        }

        try {
            $totalCount = DB::connection('old_db')->table('activities')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $validationErrors = [];

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No activities found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ”Ž Found {$totalCount} activities to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Get valid activity type IDs for validation
            $validActivityTypeIds = $skipValidation ? [] :
                DB::table('activity_types')->pluck('id')->toArray();

            // Process in chunks
            DB::connection('old_db')
                ->table('activities')
                ->orderBy('ActivityID')
                ->chunk($chunk, function ($activities) use (&$migratedCount, &$skippedCount, &$errorCount, &$validationErrors, $progressBar, $dryRun, $skipValidation, $validActivityTypeIds) {

                    $activityData = [];

                    foreach ($activities as $activity) {
                        try {
                            // Check if activity already exists
                            if (!$dryRun) {
                                $exists = DB::table('activities')
                                    ->where('id', $activity->ActivityID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            // Validate activity type exists (unless validation skipped)
                            if (
                                !$skipValidation
                                && $activity->ActivityTypeID
                                && !in_array($activity->ActivityTypeID, $validActivityTypeIds, true)
                            ) {
                                $validationErrors[] = "Activity {$activity->ActivityID}: Invalid ActivityTypeID {$activity->ActivityTypeID}";
                                $errorCount++;
                                $progressBar->advance();
                                continue;
                            }

                            // Map old UnitID + RCU flag to polymorphic pair
                            [$assignableType, $assignableId] = $this->mapAssignable(
                                $activity->UnitID ?? null,
                                $activity->RCU ?? null
                            );

                            $newActivity = [
                                'id'                   => $activity->ActivityID, // Preserve original ID
                                'activity_type_id'     => $activity->ActivityTypeID,
                                'user_id'              => $activity->PersonID, // Changed from person_id
                                'date'                 => $this->convertDate($activity->FromDate),
                                'hours'                => $this->convertHours($activity->Hours),
                                'is_deleted'           => $this->convertBoolean($activity->IsDeleted, false),
                                'submitted_at'         => $this->convertTimestamp($activity->Timestamp),
                                'submission_name'      => $this->cleanString($activity->SubmissionName),
                                'reference'            => $this->cleanString($activity->Reference),
                                'submitted_by_user_id' => $activity->SubmissionID, // Changed from submitted_by_id
                                'branch_id'            => $activity->BranchID,
                                'division_id'          => $activity->DivisionID, // Required field
                                // ðŸ”¸ new polymorphic pair
                                'assignable_type'      => $assignableType,
                                'assignable_id'        => $assignableId,
                                'created_at'           => $this->convertTimestamp($activity->Timestamp) ?? now(),
                                'updated_at'           => $this->convertTimestamp($activity->Timestamp) ?? now(),
                                // Legacy records are pre-approved (no approval step existed).
                                'approval_status'      => 'approved',
                                'decided_at'           => $this->convertDate($activity->FromDate) ?? $this->convertTimestamp($activity->Timestamp) ?? now(),
                                'decided_by_user_id'   => null,
                            ];

                            $activityData[] = $newActivity;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $validationErrors[] = "Activity {$activity->ActivityID}: " . $e->getMessage();
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($activityData)) {
                        DB::table('activities')->insert($activityData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('activities')->max('id') ?? 0;
                DB::statement("ALTER TABLE activities AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("ðŸ”§ Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show validation errors (first 10)
            if (!empty($validationErrors)) {
                $this->warn('âš ï¸ Validation errors encountered:');
                foreach (array_slice($validationErrors, 0, 10) as $error) {
                    $this->line("  - {$error}");
                }
                if (count($validationErrors) > 10) {
                    $remaining = count($validationErrors) - 10;
                    $this->line("  ... and {$remaining} more errors");
                }
            }

            // Show results
            $this->info('âœ… Activities migration completed!');
            $this->table(['Metric', 'Count'], [
                ['Total Records', $totalCount],
                ['Successfully Processed', $migratedCount],
                ['Skipped (Already Exist)', $skippedCount],
                ['Validation Errors', $errorCount],
                ['Success Rate', $totalCount > 0 ? round(($migratedCount / $totalCount) * 100, 2) . '%' : '0%']
            ]);

            $this->showStatistics();

        } catch (\Exception $e) {
            $this->error('âŒ Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Map legacy UnitID + RCU flag to (assignable_type, assignable_id).
     * - If UnitID is null or 0 â†’ both null.
     * - If RCU = 1 â†’ RedCrossUnit
     * - If RCU = 0 â†’ TaskForce
     * - If RCU is null but UnitID present â†’ leave both null (ambiguous) OR default to one (commented).
     */
    private function mapAssignable($unitId, $rcu): array
    {
        if ($unitId === null || $unitId === '' || (int) $unitId === 0) {
            return [null, null];
        }

        // Normalize the flag
        $flag = $this->convertBoolean($rcu, null);

        if ($flag === true) {
            return ['App\\Models\\RedCrossUnit', (int) $unitId];
        }
        if ($flag === false) {
            return ['App\\Models\\TaskForce', (int) $unitId];
        }

        // Ambiguous: RCU is null but UnitID exists.
        // Option A (safe): keep nulls so you can inspect later:
        return [null, null];

        // Option B (default to TaskForce, for example):
        // return ['App\\Models\\TaskForce', (int) $unitId];
    }

    private function cleanString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function convertBoolean($value, $defaultIfNull = null): ?bool
    {
        if ($value === null) {
            return $defaultIfNull;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return (bool) $value;
    }

    private function convertDate($value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function convertTimestamp($value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function convertHours($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $hours = (int) $value;

        // Validation: should be reasonable (0-168 hours per activity)
        if ($hours < 0 || $hours > 168) {
            return null;
        }

        return $hours;
    }

    private function showStatistics()
    {
        $totalActivities  = DB::table('activities')->count();
        $deletedActivities = DB::table('activities')->where('is_deleted', true)->count();
        $activeActivities  = DB::table('activities')->where('is_deleted', false)->count();

        $this->info('ðŸ“Š Activities Statistics:');
        $this->line("  - Total activities: {$totalActivities}");
        $this->line("  - Active activities: {$activeActivities}");
        $this->line("  - Deleted activities: {$deletedActivities}");

        // Distribution by activity type (top 10)
        $typeDistribution = DB::table('activities')
            ->leftJoin('activity_types', 'activities.activity_type_id', '=', 'activity_types.id')
            ->selectRaw('COALESCE(activity_types.name, "Unknown") as name, COUNT(*) as count')
            ->where('activities.is_deleted', false)
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if ($typeDistribution->isNotEmpty()) {
            $this->line("  - Top activity types:");
            foreach ($typeDistribution as $dist) {
                $this->line("    â€¢ {$dist->name}: {$dist->count} activities");
            }
        }

        // Date range
        $dateRange = DB::table('activities')
            ->selectRaw('MIN(date) as earliest, MAX(date) as latest')
            ->where('is_deleted', false)
            ->whereNotNull('date')
            ->first();

        if ($dateRange && $dateRange->earliest) {
            $this->line("  - Date range: {$dateRange->earliest} to {$dateRange->latest}");
        }

        // Hours stats
        $hoursStats = DB::table('activities')
            ->selectRaw('SUM(hours) as total_hours, AVG(hours) as avg_hours, MAX(hours) as max_hours')
            ->where('is_deleted', false)
            ->whereNotNull('hours')
            ->first();

        if ($hoursStats && $hoursStats->total_hours) {
            $this->line("  - Total volunteer hours: " . number_format($hoursStats->total_hours));
            $this->line("  - Average hours per activity: " . round($hoursStats->avg_hours, 1));
            $this->line("  - Maximum hours in single activity: {$hoursStats->max_hours}");
        }

        // New: stats by polymorphic target
        $assignableStats = DB::table('activities')
            ->selectRaw('
                SUM(CASE WHEN assignable_type = "App\\\\Models\\\\RedCrossUnit" THEN 1 ELSE 0 END) as red_cross_unit_count,
                SUM(CASE WHEN assignable_type = "App\\\\Models\\\\TaskForce" THEN 1 ELSE 0 END) as task_force_count,
                SUM(CASE WHEN assignable_type IS NULL THEN 1 ELSE 0 END) as unassigned_count
            ')
            ->where('is_deleted', false)
            ->first();

        if ($assignableStats) {
            $this->line("  - Linked to Red Cross Units: {$assignableStats->red_cross_unit_count}");
            $this->line("  - Linked to Task Forces: {$assignableStats->task_force_count}");
            if ($assignableStats->unassigned_count > 0) {
                $this->line("  - Unassigned (no type): {$assignableStats->unassigned_count}");
            }
        }
    }
}
