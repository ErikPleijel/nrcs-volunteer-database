<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('heat_score', 5, 4)->nullable();
            $table->timestamp('heat_computed_at')->nullable();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->decimal('heat_score', 5, 4)->nullable();
            $table->timestamp('heat_computed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['heat_score', 'heat_computed_at']);
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['heat_score', 'heat_computed_at']);
        });
    }
};
