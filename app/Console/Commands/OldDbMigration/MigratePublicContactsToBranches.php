<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use App\Models\Branch;
use App\Models\User;

class MigratePublicContactsToBranches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Run with: php artisan migrate:public-contacts
     */
    protected $signature = 'migrate:public-contacts';

    /**
     * The console command description.
     */
    protected $description = 'Fill branch public contact slots from users with is_public_contact = 1';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting migration of public contacts from users to branches...');

        $totalBranches = Branch::count();
        $this->info("Found {$totalBranches} branches.");

        Branch::chunkById(50, function ($branches) {
            foreach ($branches as $branch) {
                // Get all public contacts for this branch
                $contacts = User::where('branch_id', $branch->id)
                    ->where('is_public_contact', 1)
                    ->orderBy('id')
                    ->get();

                if ($contacts->isEmpty()) {
                    $this->line("Branch #{$branch->id} ({$branch->name}): no public contacts found, skipping.");
                    continue;
                }

                $this->line("Branch #{$branch->id} ({$branch->name}): found {$contacts->count()} public contacts.");

                // Optional: clear existing slots first
                for ($i = 1; $i <= 6; $i++) {
                    $branch->{"public_contact_user_id_{$i}"} = null;
                    $branch->{"public_contact_position_{$i}"} = null;
                }

                // Fill up to 6 slots
                $slot = 1;
                foreach ($contacts as $contact) {
                    if ($slot > 6) {
                        $this->warn("Branch #{$branch->id}: more than 6 contacts, extra ones are ignored.");
                        break;
                    }

                    $branch->{"public_contact_user_id_{$slot}"} = $contact->id;
                    $branch->{"public_contact_position_{$slot}"} = $contact->public_contact_position;

                    $slot++;
                }

                $branch->save();
            }
        });

        $this->info('Public contacts migration completed.');
        return Command::SUCCESS;
    }
}
