<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Opaque token identifying an organisation in its membership and donation
 * certificates' QR verification links — mirrors red_cross_units.id_check_token
 * (2026_09_25_110001_add_id_check_token_to_red_cross_units_table.php), including
 * its retry-until-unique generation. Existing organisations (soft-deleted ones
 * too) are backfilled in chunks via the query builder, so no model events fire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('id_check_token', 32)->nullable()->unique()->after('id');
        });

        DB::table('organisations')
            ->whereNull('id_check_token')
            ->orderBy('id')
            ->chunkById(500, function ($organisations) {
                DB::transaction(function () use ($organisations) {
                    foreach ($organisations as $organisation) {
                        $token = Str::random(32);

                        // Loop until a truly unique token is generated
                        while (DB::table('organisations')->where('id_check_token', $token)->exists()) {
                            $token = Str::random(32);
                        }

                        DB::table('organisations')
                            ->where('id', $organisation->id)
                            ->update(['id_check_token' => $token]);
                    }
                });
            });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropUnique(['id_check_token']);
            $table->dropColumn('id_check_token');
        });
    }
};
