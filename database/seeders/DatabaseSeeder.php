<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            RolesTableSeeder::class,
            PermissionsTableSeeder::class,
            UserSeeder::class,
            SuperAdminSeeder::class,
            ReportMonthsSeeder::class,
            SettingsTableSeeder::class,
            CampaignPurposesSeeder::class,
            DivisionCoordinatesSeeder::class,
            // Runs after training types exist (imported by migrate:old-db before this db:seed call).
            FirstAidTrainingTypesSeeder::class,
        ]);
    }
}
