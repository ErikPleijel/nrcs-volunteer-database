<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedInteger('first_aid_count')->nullable()->after('heat_computed_at');
            $table->decimal('first_aid_avg_days', 8, 2)->nullable()->after('first_aid_count');
            $table->timestamp('first_aid_computed_at')->nullable()->after('first_aid_avg_days');
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->unsignedInteger('first_aid_count')->nullable()->after('heat_computed_at');
            $table->decimal('first_aid_avg_days', 8, 2)->nullable()->after('first_aid_count');
            $table->timestamp('first_aid_computed_at')->nullable()->after('first_aid_avg_days');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['first_aid_count', 'first_aid_avg_days', 'first_aid_computed_at']);
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['first_aid_count', 'first_aid_avg_days', 'first_aid_computed_at']);
        });
    }
};
