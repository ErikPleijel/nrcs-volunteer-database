<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FirstAidTrainingTypesSeeder extends Seeder
{
    /**
     * Canonical first-aid training type names (exact, as flagged on dev 2026-06-15).
     * A new first-aid training type MUST be added here or the next reseed clears its flag.
     */
    private const FIRST_AID_TYPE_NAMES = [
        'Advanced First Aid for instructors',
        'Basic First Aid for instructors',
        'Community Based Health & First Aid (ToT)',
        'Community Based Health and First Aid',
        'Community First Aid',
        'Competence Based Standard First Aid',
        'First Aid at work',
        'First Aid Comprehensive',
        'First Aid for volunteers',
        'First Aid Training of Trainers Course',
    ];

    public function run(): void
    {
        $names = self::FIRST_AID_TYPE_NAMES;

        $flagged = DB::table('training_types')->whereIn('name', $names)->update(['is_first_aid' => true]);
        $cleared = DB::table('training_types')->whereNotIn('name', $names)->update(['is_first_aid' => false]);

        if ($this->command) {
            $this->command->info("FirstAidTrainingTypesSeeder: flagged {$flagged} FA type(s), cleared {$cleared} other(s).");
            $present = DB::table('training_types')->whereIn('name', $names)->pluck('name')->all();
            $missing = array_values(array_diff($names, $present));
            if ($missing) {
                $this->command->warn('  Canonical FA type names not found in training_types: '.implode(', ', $missing));
            }
        }
    }
}
