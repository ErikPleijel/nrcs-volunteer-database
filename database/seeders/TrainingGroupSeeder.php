<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainingGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define the Groups
        $groups = [
            'Health and First Aid',
            'Disaster and Risk Reduction (DRR)',
            'Water, Sanitation, and Hygiene (WASH)',
            'Management and Organizational Skills',
            'Community and Cross-Cutting Issues',
            'Technical and Data Skills',
        ];

        // Insert the groups and get the group IDs
        DB::table('training_groups')->upsert(
            array_map(fn($name) => ['group_name' => $name], $groups),
            ['group_name'],
            ['group_name'] // Only updating 'group_name' is redundant but ensures consistency
        );

        // Fetch the inserted IDs for mapping
        $groupMap = DB::table('training_groups')
            ->pluck('id', 'group_name')
            ->toArray();

        // 2. Define the Training Type Mappings
        // We link the actual training names to their group name keys.
        $trainingMappings = [
            'Health and First Aid' => [
                'Advanced First Aid for instructors',
                'Basic First Aid for instructors',
                'Basic Life Support',
                'Community First Aid',
                'Competence Based Standard First Aid',
                'First Aid at work',
                'First Aid Comprehensive',
                'First Aid for volunteers',
                'First Aid Training of Trainers Course',
                'Community Based Health & First Aid (ToT)',
                'Community Based Health and First Aid',
                'EFAT', // Emergency First Aid Training
                'Home based care',
                'HIV Testing and Counselling',
                'Malaria training',
                'Nutritional management',
                'Nutritional Management (ToT)',
                'Sexual Reproductive Health and Rights',
                'TB training',
                'Dead-body management',
                'Health Care in Danger',
                'Psychosocial support',
            ],
            'Disaster and Risk Reduction (DRR)' => [
                'Basic disaster management',
                'Disaster management and response',
                'National Disaster Response Team',
                'Community Based Disaster Risk Reduction (CBDRR)',
                'Community Based Disaster Risk Reduction (CBDRR) - ...',
                'Basic risk reduction and climate change',
                'Distribution of NFIs',
                'Post distribution monitoring',
                'Rapid assessment and distribution',
                'Relief distribution',
                'Building construction and roofing',
                'Molding of bricks',
                'Participator Approach on Self Shelter Awareness (P...',
                'Shelter & Camp Management',
                'Shelter Project Implementation & Coordination',
                'Explosive hazard risk awareness TOT',
                'Weapon Contamination Risk Awareness (WEC)',
                'Safer Access',
                'Stay Safe',
            ],
            'Water, Sanitation, and Hygiene (WASH)' => [
                'Community Led Total Sanitation (CLTS)',
                'Hygiene Promotion',
                'Rural sanitation management',
                'PHAST',
                'Community Based Management training for water poin...',
                'Management of water points',
                'Pump repairs training',
            ],
            'Management and Organizational Skills' => [
                'Monitoring & Evaluation',
                'PMER',
                'Project Management',
                'Team management',
                'Finance Management',
                'Fleet Management',
                'Fundraising',
                'Resource Mobilization',
                'Leadership training',
                'Volunteer Database Management',
                'Volunteer management',
            ],
            'Community and Cross-Cutting Issues' => [
                'Basic OVC training',
                'Child protection',
                'Early childhood development',
                'Gender training',
                'Protection (RFL)',
                'Restoring Family Links',
                'Girl Group leader training',
                'Peer educator',
                'Junior Red Cross Training',
                'Basic Food Security',
                'Economic security',
                'Livelihood',
                'Livelihood registration',
                'Community engagement and accountability',
                'Communication skill and photography',
                'Radio Training',
                'Life Skills',
            ],
            'Technical and Data Skills' => [
                'Basics of QGIS',
                'Kobo collect',
                'Mega V',
            ],
        ];

        // 3. Update the existing training_types table
        foreach ($trainingMappings as $groupName => $trainingTypes) {
            $groupId = $groupMap[$groupName] ?? null;

            if ($groupId) {
                // Bulk update the training_types table
                DB::table('training_types')
                    ->whereIn('name', $trainingTypes)
                    ->update(['group_id' => $groupId]);
            }
        }
    }
}
