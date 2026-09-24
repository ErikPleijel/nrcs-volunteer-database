<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Opaque token identifying a Red Cross Unit in its membership certificate's
 * QR verification link — mirrors users.id_check_token
 * (2025_11_07_132758_add_id_check_token_to_users_table.php), including its
 * retry-until-unique generation. Existing units are backfilled in chunks via
 * the query builder, so no model events fire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('red_cross_units', function (Blueprint $table) {
            $table->string('id_check_token', 32)->nullable()->unique()->after('id');
        });

        DB::table('red_cross_units')
            ->whereNull('id_check_token')
            ->orderBy('id')
            ->chunkById(500, function ($units) {
                DB::transaction(function () use ($units) {
                    foreach ($units as $unit) {
                        $token = Str::random(32);

                        // Loop until a truly unique token is generated
                        while (DB::table('red_cross_units')->where('id_check_token', $token)->exists()) {
                            $token = Str::random(32);
                        }

                        DB::table('red_cross_units')
                            ->where('id', $unit->id)
                            ->update(['id_check_token' => $token]);
                    }
                });
            });
    }

    public function down(): void
    {
        Schema::table('red_cross_units', function (Blueprint $table) {
            $table->dropUnique(['id_check_token']);
            $table->dropColumn('id_check_token');
        });
    }
};
