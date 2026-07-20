<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Covers the unscoped (national) users/index default view:
            // WHERE lifecycle_status IN (...) ORDER BY created_at DESC
            $table->index(['lifecycle_status', 'created_at'], 'users_lifecycle_created_index');

            // Covers branch-scoped admins' default users/index view:
            // WHERE branch_id = ? AND lifecycle_status IN (...) ORDER BY created_at DESC
            $table->index(['branch_id', 'lifecycle_status', 'created_at'], 'users_branch_lifecycle_created_index');

            // Covers division-scoped admins' default users/index view:
            // WHERE division_id = ? AND lifecycle_status IN (...) ORDER BY created_at DESC
            $table->index(['division_id', 'lifecycle_status', 'created_at'], 'users_division_lifecycle_created_index');
        });

        Schema::table('membership_payments', function (Blueprint $table) {
            // Covers membership-payments/index, which always filters approval_status
            // (Approvable global scope) and is_deleted, then optionally branch_id/division_id,
            // ordered by payment_date. Existing composite indexes on this table omit
            // approval_status entirely.
            $table->index(
                ['approval_status', 'is_deleted', 'branch_id', 'division_id', 'payment_date'],
                'membership_payments_approval_scope_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_lifecycle_created_index');
            $table->dropIndex('users_branch_lifecycle_created_index');
            $table->dropIndex('users_division_lifecycle_created_index');
        });

        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropIndex('membership_payments_approval_scope_index');
        });
    }
};
