<?php

namespace App\Console\Commands;

use App\Models\Log as AuditLog;
use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Set a user's password from the shell — for servers with no email
 * provider, where "Forgot password" can't deliver a reset link.
 *
 * The plaintext is assigned as-is: the User 'hashed' cast hashes it.
 * email_verified_at and lifecycle_status are deliberately left alone —
 * clearing verification on a server without email would strand the user
 * on the verification-required page.
 */
class ResetUserPassword extends Command
{
    use ConfirmableTrait;

    protected $signature = 'users:reset-password
                            {id : The user ID}
                            {password? : The new password (omit to be prompted, keeping it out of shell history)}
                            {--yes : Skip the user-details confirmation}
                            {--force : Run in production without the production prompt}';

    protected $aliases = ['resetpw'];

    protected $description = 'Set a user\'s password, sign them out everywhere and clear their login lockout';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $user = User::find($this->argument('id'));

        if (! $user) {
            $this->error("User #{$this->argument('id')} not found.");
            return self::FAILURE;
        }

        $this->showUser($user);

        $password = $this->argument('password');

        if ($password !== null) {
            $this->warn('The password was passed as an argument, so it is now in your shell history. Omit it next time to be prompted instead.');
        } else {
            $password = $this->secret('New password');
            $confirmation = $this->secret('Confirm new password');

            if ($password !== $confirmation) {
                $this->error('The passwords do not match.');
                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm("Reset the password for user #{$user->id}?", false)) {
            $this->warn('Command cancelled.');
            return self::FAILURE;
        }

        $sessionsDeleted = DB::transaction(function () use ($user, $password) {
            $user->password = $password;
            $user->legacy_password_hash = null;
            $user->remember_token = Str::random(60);
            $user->save();

            $sessionsDeleted = config('session.driver') === 'database'
                ? DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete()
                : 0;

            $operator = get_current_user().'@'.gethostname();

            AuditLog::write(
                'password_reset_cli',
                $user,
                ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
                null,
                ['via' => 'users:reset-password', 'os_user' => get_current_user(), 'host' => gethostname(), 'sessions_deleted' => $sessionsDeleted],
                "Password reset from the command line for user #{$user->id} by {$operator}."
            );

            return $sessionsDeleted;
        });

        LoginThrottle::clearFor($user);

        $this->info("Password reset for {$user->full_name} (#{$user->id}). {$sessionsDeleted} session(s) signed out, login lockout cleared.");

        return self::SUCCESS;
    }

    private function showUser(User $user): void
    {
        $user->loadMissing(['branch', 'division']);

        $this->table(['Field', 'Value'], [
            ['ID', $user->id],
            ['Name', $user->full_name],
            ['Email', $user->email ?? '—'],
            ['Phone', $user->telephone1 ?? '—'],
            ['Roles', $user->role_names_string ?: '—'],
            ['Status', $user->lifecycle_status],
            ['Branch', $user->branch?->name ?? '—'],
            ['Division', $user->division?->name ?? '—'],
        ]);

        if ($user->lifecycle_status === 'archived') {
            $this->warn('This user is archived: they still cannot sign in after the reset. Reactivate them separately if needed.');
        }

        if ($user->is_super_admin || $user->hasAnyRole(User::NATIONAL_ROLES)) {
            $this->warn('This user has a national-level role. Make sure this reset is authorised.');
        }

        if (config('maintenance.login_gate_enabled')
            && ! in_array((int) $user->id, array_map('intval', config('maintenance.allowed_user_ids', [])), true)) {
            $this->warn('The maintenance login gate is on and this user is not on its allow list, so they cannot sign in until it is lifted.');
        }
    }
}
