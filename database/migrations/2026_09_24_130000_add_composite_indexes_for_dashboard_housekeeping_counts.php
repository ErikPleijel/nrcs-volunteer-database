<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering indexes for the dashboard counts that were still slow at ~300K users.
 *
 * ~82% of users are pending_engagement, so lifecycle_status alone barely narrows anything;
 * when the second filter column (can_contribute_*, gender, is_form_registration,
 * email_verified_at...) isn't in the same index, MySQL fetches ~250K full rows one by one.
 * Putting that column in the index lets the count be answered from the index alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Pending volunteers / pending members (lifecycle card), branch and national scope.
            $table->index(
                ['branch_id', 'lifecycle_status', 'can_contribute_volunteering', 'can_contribute_member'],
                'users_branch_lifecycle_contrib_index'
            );
            $table->index(
                ['lifecycle_status', 'can_contribute_volunteering', 'can_contribute_member'],
                'users_lifecycle_contrib_index'
            );

            // Hanging registrations (housekeeping). branch_id trails so the branch-scoped
            // version is also answered from the index alone.
            $table->index(
                ['is_form_registration', 'lifecycle_status', 'is_super_admin', 'organisation_id', 'created_at', 'branch_id'],
                'users_hanging_registration_index'
            );

            // Unassigned ghosts (housekeeping).
            $table->index(['red_cross_unit_id', 'assigned_rcu_date'], 'users_rcu_assigned_date_index');

            // Unverified emails (housekeeping) — previously a full table scan.
            $table->index(['email_verified_at', 'email'], 'users_email_verified_index');

            // Average members per active unit (extended view): join on unit, filter on lifecycle.
            $table->index(['red_cross_unit_id', 'lifecycle_status'], 'users_rcu_lifecycle_index');

            // Member/volunteer gender breakdowns. Widens users_lifecycle_rcu_index (a strict prefix
            // of this one) rather than keeping both.
            $table->index(['lifecycle_status', 'red_cross_unit_id', 'gender'], 'users_lifecycle_rcu_gender_index');
            $table->dropIndex('users_lifecycle_rcu_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['lifecycle_status', 'red_cross_unit_id'], 'users_lifecycle_rcu_index');
            $table->dropIndex('users_lifecycle_rcu_gender_index');
            $table->dropIndex('users_rcu_lifecycle_index');
            $table->dropIndex('users_email_verified_index');
            $table->dropIndex('users_rcu_assigned_date_index');
            $table->dropIndex('users_hanging_registration_index');
            $table->dropIndex('users_lifecycle_contrib_index');
            $table->dropIndex('users_branch_lifecycle_contrib_index');
        });
    }
};
