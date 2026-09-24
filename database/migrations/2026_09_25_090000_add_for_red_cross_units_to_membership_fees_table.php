<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_fees', function (Blueprint $table) {
            $table->boolean('for_red_cross_units')->default(false)->after('for_organizations')->comment('Whether this membership type is for Red Cross Units');

            $table->index(['is_active', 'for_red_cross_units']);
        });
    }

    public function down(): void
    {
        Schema::table('membership_fees', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'for_red_cross_units']);
            $table->dropColumn('for_red_cross_units');
        });
    }
};
