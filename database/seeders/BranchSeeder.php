<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting to update branches with latitude and longitude data...');

        // Nigerian states with their coordinates
        $statesData = [
            ['name' => 'Abia', 'latitude' => 5.4523, 'longitude' => 7.5248],
            ['name' => 'Adamawa', 'latitude' => 9.3265, 'longitude' => 12.3984],
            ['name' => 'Akwa Ibom', 'latitude' => 4.9057, 'longitude' => 7.8537],
            ['name' => 'Anambra', 'latitude' => 6.2103, 'longitude' => 7.0739],
            ['name' => 'Bauchi', 'latitude' => 10.7761, 'longitude' => 9.9992],
            ['name' => 'Bayelsa', 'latitude' => 4.7719, 'longitude' => 6.0699],
            ['name' => 'Benue', 'latitude' => 7.1907, 'longitude' => 8.1349],
            ['name' => 'Borno', 'latitude' => 11.8333, 'longitude' => 13.15],
            ['name' => 'Cross River', 'latitude' => 5.8702, 'longitude' => 8.5988],
            ['name' => 'Delta', 'latitude' => 5.532, 'longitude' => 5.8987],
            ['name' => 'Ebonyi', 'latitude' => 6.177, 'longitude' => 8.0498],
            ['name' => 'Edo', 'latitude' => 6.6342, 'longitude' => 6.3403],
            ['name' => 'Ekiti', 'latitude' => 7.6656, 'longitude' => 5.3103],
            ['name' => 'Enugu', 'latitude' => 6.4527, 'longitude' => 7.5103],
            ['name' => 'Gombe', 'latitude' => 10.2897, 'longitude' => 11.17],
            ['name' => 'Imo', 'latitude' => 5.572, 'longitude' => 7.0588],
            ['name' => 'Jigawa', 'latitude' => 12.228, 'longitude' => 9.5616],
            ['name' => 'Kaduna', 'latitude' => 10.3764, 'longitude' => 7.7096],
            ['name' => 'Kano', 'latitude' => 12.0022, 'longitude' => 8.5919],
            ['name' => 'Katsina', 'latitude' => 12.9855, 'longitude' => 7.6171],
            ['name' => 'Kebbi', 'latitude' => 11.6781, 'longitude' => 4.0695],
            ['name' => 'Kogi', 'latitude' => 7.733, 'longitude' => 6.6906],
            ['name' => 'Kwara', 'latitude' => 8.9669, 'longitude' => 4.3874],
            ['name' => 'Lagos', 'latitude' => 6.5244, 'longitude' => 3.3792],
            ['name' => 'Nasarawa', 'latitude' => 8.538, 'longitude' => 8.3224],
            ['name' => 'Niger', 'latitude' => 9.93, 'longitude' => 5.62],
            ['name' => 'Ogun', 'latitude' => 6.998, 'longitude' => 3.4737],
            ['name' => 'Ondo', 'latitude' => 7.1, 'longitude' => 5.1],
            ['name' => 'Osun', 'latitude' => 7.5629, 'longitude' => 4.52],
            ['name' => 'Oyo', 'latitude' => 8.1574, 'longitude' => 3.6147],
            ['name' => 'Plateau', 'latitude' => 9.2182, 'longitude' => 9.5179],
            ['name' => 'Rivers', 'latitude' => 4.8436, 'longitude' => 6.9112],
            ['name' => 'Sokoto', 'latitude' => 13.0059, 'longitude' => 5.2476],
            ['name' => 'Taraba', 'latitude' => 7.997, 'longitude' => 10.977],
            ['name' => 'Yobe', 'latitude' => 12.294, 'longitude' => 11.439],
            ['name' => 'Zamfara', 'latitude' => 12.1704, 'longitude' => 6.6591],
            ['name' => 'FCT', 'latitude' => 8.8941, 'longitude' => 7.186],
        ];

        $updatedCount = 0;
        $notFoundCount = 0;

        // Update existing branches with coordinates
        foreach ($statesData as $state) {
            $result = DB::table('branches')
                ->where('name', $state['name'])
                ->update([
                    'latitude' => $state['latitude'],
                    'longitude' => $state['longitude'],
                    'updated_at' => now()
                ]);

            if ($result > 0) {
                $updatedCount++;
                $this->command->line("  ✅ Updated coordinates for: {$state['name']}");
            } else {
                $notFoundCount++;
                $this->command->line("  ❌ Branch not found: {$state['name']}");
            }
        }

        $this->command->info("🎉 Branch coordinates update completed!");
        $this->command->info("  - Updated: {$updatedCount} branches");
        $this->command->info("  - Not found: {$notFoundCount} branches");
    }
}
