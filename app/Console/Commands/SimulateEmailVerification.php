<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Dev-only convenience command for simulating SendGrid email verification locally. Not intended for production use.
 */
class SimulateEmailVerification extends Command
{
    protected $signature = 'users:verify-email {id} {--unverify}';

    protected $description = 'Simulate email verification for a user by setting or clearing email_verified_at';

    public function handle(): int
    {
        $user = User::find($this->argument('id'));

        if (! $user) {
            $this->error("User #{$this->argument('id')} not found.");
            return self::FAILURE;
        }

        if (! $user->email) {
            $this->error("User #{$user->id} has no email — nothing to verify");
            return self::FAILURE;
        }

        if ($this->option('unverify')) {
            $user->email_verified_at = null;
            $user->save();

            $this->info("{$user->full_name} ({$user->email}) is now unverified.");
            return self::SUCCESS;
        }

        $user->email_verified_at = now();
        $user->save();

        $this->info("{$user->full_name} ({$user->email}) verified at {$user->email_verified_at}.");
        return self::SUCCESS;
    }
}
