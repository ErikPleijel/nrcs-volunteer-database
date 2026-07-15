<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Speeds up members/volunteers counts that filter on both columns together.
            // Without this, MySQL intersects two large single-column index scans (12–35s per query).
            $table->index(['lifecycle_status', 'red_cross_unit_id'], 'users_lifecycle_rcu_index');
        });

        Schema::table('membership_payments', function (Blueprint $table) {
            // Covers the EXISTS subquery in scopeHasValidMembership:
            // WHERE user_id = ? AND is_deleted = ? AND approval_status = ? AND expiry_date >= ?
            // The existing (is_deleted, payment_date, expiry_date, user_id) index doesn't lead
            // with user_id and omits approval_status, so the lookup falls back to the user_id
            // single-column index and re-filters each row.
            $table->index(
                ['user_id', 'is_deleted', 'approval_status', 'expiry_date'],
                'mp_valid_by_user_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_lifecycle_rcu_index');
        });

        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropIndex('mp_valid_by_user_index');
        });
    }
};
