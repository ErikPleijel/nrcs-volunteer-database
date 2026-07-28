<?php

// database/seeders/SuperAdminSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon; // 1. Added Carbon for date handling

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $emails = config('superadmin.emails');

        foreach ($emails as $email) {
            if (! $email) {
                continue;
            }

            $existing = User::where('email', $email)->first();

            if ($existing) {
                $this->command->error(
                    "SuperAdminSeeder stopped: an account already exists for "
                    . "{$email} (user id {$existing->id}). Review this account "
                    . "manually — if it should not hold super-admin, delete or "
                    . "reassign it, then re-run this seeder. No further emails "
                    . "in the list were processed."
                );
                return;
            }

            // Ensure all required fields are provided for creation
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => 'System Super Admin',
                    'last_name'  => 'User',
                    'password'   => Hash::make(Str::random(32)), // they can reset later
                ]
            );

            // 2. Set flags (is_super_admin and email_verified_at) if needed
            $needsUpdate = false;

            if (! $user->is_super_admin) {
                $user->is_super_admin = true;
                $needsUpdate = true;
            }

            // Set email_verified_at to today's date if it's currently null
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = Carbon::now();
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $user->save();
            }

            if (! $user->hasRole('super-admin')) {
                $user->assignRole('super-admin');
            }
        }
    }
}
