<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_check_token', 32)->nullable()->unique()->after('id');
        });

        // Generate tokens for existing users with a uniqueness guarantee
        $users = User::whereNull('id_check_token')->get();

        // Use a transaction for safety (optional but good practice)
        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $token = Str::random(32);

                // Loop until a truly unique token is generated
                while (User::where('id_check_token', $token)->exists()) {
                    $token = Str::random(32);
                }

                $user->id_check_token = $token;
                $user->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id_check_token');
        });
    }
};
