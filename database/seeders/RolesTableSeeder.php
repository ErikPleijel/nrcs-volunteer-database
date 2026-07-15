<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;


// php artisan db:seed --class="Database\Seeders\RolesTableSeeder"
class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎭 Seeding roles...');

        // Role names + initial descriptions
        $roles = [

            'super-admin' =>
                'Holds full system-wide authority. Intended for the President, Secretary General, or designated technical system administrators.',



            'national_db_administrator' =>
                'Oversees all national-level data operations. Authorizes roles at national level and appoints branch secretaries and branch/division database assistants.',

            'national_db_assistant' =>
                'Supports the national database administrator with data entry, corrections, and operational record-keeping at national level.',

            'branch_secretary' =>
                'Responsible for branch-level administration, including managing member records and authorizing branch and division database assistants.',

            'branch_db_administrator' =>
                'Handles branch-level administration and data management, with the same authorization level as the branch secretary, including authorizing branch and division database assistants.',

            'branch_db_assistant' =>
                'Supports the branch secretary and branch DB administrator with data entry and local record maintenance at branch level.',

            'division_db_assistant_finance' =>
                'Assists at division level with both operational and financial data entry, including membership payments, donations, volunteering logs, and training records.',

            'division_db_assistant_operations' =>
                'Assists at division level with operational data entry, including volunteering logs and training records. Does not handle financial transactions.',

            'observer_national_level' =>
                'Has read-only access to national-level data, reports, and trends, without permission to edit or modify records.',
        ];




        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($roles as $name => $description) {

            // Look for existing role
            $role = Role::where('name', $name)->first();

            if ($role) {
                // Update description if missing or empty
                if (empty($role->description)) {
                    $role->description = $description;
                    $role->save();
                    $updatedCount++;
                    $this->command->line("  ✏️ Updated description for: {$name}");
                } else {
                    $skippedCount++;
                    $this->command->line("  ⏭️  Skipped (exists with description): {$name}");
                }
            } else {
                // Create role with description
                Role::create([
                    'name'        => $name,
                    'guard_name'  => 'web',
                    'description' => $description,
                ]);
                $createdCount++;
                $this->command->line("  ✅ Created role: {$name}");
            }
        }

        $this->command->info("🎉 Roles seeding complete!");
        $this->command->info("  - Created: {$createdCount}");
        $this->command->info("  - Updated: {$updatedCount}");
        $this->command->info("  - Skipped: {$skippedCount}");
    }
}
