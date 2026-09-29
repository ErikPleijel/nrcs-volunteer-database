<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering index for the branch-level volunteering hours on the public
 * certificate verification page (VerificationStatsService::forBranch()).
 *
 * That query filters on branch_id / approval_status / is_deleted and sums
 * hours by YEAR(date). Without this, MySQL picks activities_is_deleted_date_index
 * and reads every live activity row; with the three equality columns first
 * and date + hours trailing, the sums are answered from the index alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->index(
                ['branch_id', 'approval_status', 'is_deleted', 'date', 'hours'],
                'activities_branch_hours_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_branch_hours_index');
        });
    }
};
