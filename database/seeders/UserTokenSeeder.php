<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str; // Import the Str facade

class UserTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default admin user
        /* User::create([
             'first_name' => 'Admin',
             'last_name' => 'User',
             'email' => 'admin@example.com',
             'password' => Hash::make('password'),
             'email_verified_at' => now(),
             // The booted method in the User model will automatically set id_check_token
         ]);*/

        // Populate id_check_token for any existing users that might be missing it
        // This is useful if the column was added later or for legacy data.
        //Important!! Delete this after final migration. (if this is run by accident, all printed ID-cart QR code verifications will not work!)
        $ids = DB::table('users')->whereNull('id_check_token')->pluck('id');
        if ($ids->isEmpty()) {
            $this->command->info('No users need an id_check_token.');
            return;
        }

        $taken = DB::table('users')
            ->whereNotNull('id_check_token')
            ->pluck('id_check_token')
            ->flip();   // for O(1) lookups

        $map = [];
        foreach ($ids as $id) {
            do {
                $token = Str::random(32);
            } while ($taken->has($token));
            $taken->put($token, true);
            $map[$id] = $token;
        }

        $generated = 0;
        foreach (array_chunk($map, 500, true) as $chunk) {
            $cases = '';
            $bindings = [];
            foreach ($chunk as $id => $token) {
                $cases .= ' WHEN ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $token;
            }
            $chunkIds = array_keys($chunk);
            $in = implode(',', array_fill(0, count($chunkIds), '?'));
            $sql = "UPDATE users SET id_check_token = CASE id{$cases} END
                    WHERE id IN ({$in})";
            DB::update($sql, array_merge($bindings, $chunkIds));
            $generated += count($chunk);
        }

        $this->command->info("Generated id_check_token for {$generated} users.");
    }
}
