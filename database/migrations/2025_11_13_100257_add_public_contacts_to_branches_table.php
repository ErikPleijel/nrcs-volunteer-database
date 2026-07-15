<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {

            // Add 6 contact person slots
            for ($i = 1; $i <= 6; $i++) {
                $table->foreignId("public_contact_user_id_$i")
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string("public_contact_position_$i")
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {

            for ($i = 1; $i <= 6; $i++) {
                $table->dropForeign(["public_contact_user_id_$i"]);
                $table->dropColumn([
                    "public_contact_user_id_$i",
                    "public_contact_position_$i"
                ]);
            }
        });
    }
};
