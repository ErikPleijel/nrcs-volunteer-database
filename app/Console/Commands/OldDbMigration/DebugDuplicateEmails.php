<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugDuplicateEmails extends Command
{
    protected $signature = 'debug:duplicate-emails';
    protected $description = 'Debug duplicate email detection differences';

    public function handle()
    {
        $oldDbName = config('database.connections.old_db.database');

        $this->info('ðŸ” Debugging duplicate email detection...');

        // 1. Your original query - basic duplicates
        $this->info("\n1ï¸âƒ£ BASIC DUPLICATES (your query):");
        $basicDuplicates = DB::select("
            SELECT Email, COUNT(*) as duplicate_count
            FROM {$oldDbName}.persons
            WHERE Email IS NOT NULL
              AND TRIM(Email) != ''
              AND Email LIKE '%@%'
            GROUP BY Email
            HAVING COUNT(*) > 1
            ORDER BY duplicate_count DESC, Email
            LIMIT 10
        ");

        foreach ($basicDuplicates as $dup) {
            $this->line("   {$dup->Email} -> {$dup->duplicate_count} accounts");
        }
        $totalBasic = DB::selectOne("
            SELECT COUNT(*) as count FROM (
                SELECT Email
                FROM {$oldDbName}.persons
                WHERE Email IS NOT NULL AND TRIM(Email) != '' AND Email LIKE '%@%'
                GROUP BY Email
                HAVING COUNT(*) > 1
            ) as duplicates
        ")->count;
        $this->info("Total basic duplicates: {$totalBasic}");

        // 2. Case-insensitive duplicates
        $this->info("\n2ï¸âƒ£ CASE-INSENSITIVE DUPLICATES:");
        $caseInsensitiveDuplicates = DB::select("
            SELECT LOWER(TRIM(Email)) as normalized_email, COUNT(*) as duplicate_count
            FROM {$oldDbName}.persons
            WHERE Email IS NOT NULL
              AND TRIM(Email) != ''
              AND Email LIKE '%@%'
            GROUP BY LOWER(TRIM(Email))
            HAVING COUNT(*) > 1
            ORDER BY duplicate_count DESC
            LIMIT 10
        ");

        foreach ($caseInsensitiveDuplicates as $dup) {
            $this->line("   {$dup->normalized_email} -> {$dup->duplicate_count} accounts");
        }
        $totalCaseInsensitive = DB::selectOne("
            SELECT COUNT(*) as count FROM (
                SELECT LOWER(TRIM(Email)) as normalized_email
                FROM {$oldDbName}.persons
                WHERE Email IS NOT NULL AND TRIM(Email) != '' AND Email LIKE '%@%'
                GROUP BY LOWER(TRIM(Email))
                HAVING COUNT(*) > 1
            ) as duplicates
        ")->count;
        $this->info("Total case-insensitive duplicates: {$totalCaseInsensitive}");

        // 3. With all filters (like the command)
        $this->info("\n3ï¸âƒ£ WITH ALL FILTERS (command query):");
        $filteredDuplicates = DB::select("
            SELECT
                LOWER(TRIM(Email)) as normalized_email,
                COUNT(*) as duplicate_count
            FROM {$oldDbName}.persons
            WHERE IsOrganisation = 0
            AND AccountActivated = 1
            AND Inactive = 0
            AND Email IS NOT NULL
            AND TRIM(Email) != ''
            AND Email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
            GROUP BY LOWER(TRIM(Email))
            HAVING duplicate_count > 1
            ORDER BY duplicate_count DESC
        ");

        foreach ($filteredDuplicates as $dup) {
            $this->line("   {$dup->normalized_email} -> {$dup->duplicate_count} accounts");
        }
        $this->info("Total filtered duplicates: " . count($filteredDuplicates));

        // 4. Show what filters are removing
        $this->info("\n4ï¸âƒ£ FILTER ANALYSIS:");

        $totalPersons = DB::selectOne("SELECT COUNT(*) as count FROM {$oldDbName}.persons")->count;
        $this->line("Total persons: {$totalPersons}");

        $withEmail = DB::selectOne("
            SELECT COUNT(*) as count FROM {$oldDbName}.persons
            WHERE Email IS NOT NULL AND TRIM(Email) != ''
        ")->count;
        $this->line("With email: {$withEmail}");

        $notOrganisation = DB::selectOne("
            SELECT COUNT(*) as count FROM {$oldDbName}.persons
            WHERE IsOrganisation = 0 AND Email IS NOT NULL AND TRIM(Email) != ''
        ")->count;
        $this->line("Not organisation: {$notOrganisation}");

        $activated = DB::selectOne("
            SELECT COUNT(*) as count FROM {$oldDbName}.persons
            WHERE IsOrganisation = 0 AND AccountActivated = 1
            AND Email IS NOT NULL AND TRIM(Email) != ''
        ")->count;
        $this->line("Account activated: {$activated}");

        $notInactive = DB::selectOne("
            SELECT COUNT(*) as count FROM {$oldDbName}.persons
            WHERE IsOrganisation = 0 AND AccountActivated = 1 AND Inactive = 0
            AND Email IS NOT NULL AND TRIM(Email) != ''
        ")->count;
        $this->line("Not inactive: {$notInactive}");

        $validEmail = DB::selectOne("
            SELECT COUNT(*) as count FROM {$oldDbName}.persons
            WHERE IsOrganisation = 0 AND AccountActivated = 1 AND Inactive = 0
            AND Email IS NOT NULL AND TRIM(Email) != ''
            AND Email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
        ")->count;
        $this->line("Valid email format: {$validEmail}");

        // 5. Show examples of filtered out emails
        $this->info("\n5ï¸âƒ£ EXAMPLES OF FILTERED OUT EMAILS:");
        $filteredOut = DB::select("
            SELECT Email, IsOrganisation, AccountActivated, Inactive
            FROM {$oldDbName}.persons
            WHERE Email IS NOT NULL AND TRIM(Email) != ''
            AND (
                IsOrganisation = 1
                OR AccountActivated = 0
                OR Inactive = 1
                OR Email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
            )
            LIMIT 10
        ");

        foreach ($filteredOut as $filtered) {
            $reasons = [];
            if ($filtered->IsOrganisation) $reasons[] = 'Organisation';
            if (!$filtered->AccountActivated) $reasons[] = 'Not Activated';
            if ($filtered->Inactive) $reasons[] = 'Inactive';
            if (!preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $filtered->Email)) {
                $reasons[] = 'Invalid Email Format';
            }

            $this->line("   {$filtered->Email} -> " . implode(', ', $reasons));
        }

        return Command::SUCCESS;
    }
}
