<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_removed_fields_to_membership_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('membership_payments', 'removed_date')) {
                $table->dateTime('removed_date')->nullable()->after('is_deleted');
            }

            if (!Schema::hasColumn('membership_payments', 'removed_by_user_id')) {
                $table->unsignedBigInteger('removed_by_user_id')->nullable()->after('removed_date');
                $table->index('removed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            if (Schema::hasColumn('membership_payments', 'removed_by_user_id')) {
                $table->dropIndex(['removed_by_user_id']);
                $table->dropColumn('removed_by_user_id');
            }
            if (Schema::hasColumn('membership_payments', 'removed_date')) {
                $table->dropColumn('removed_date');
            }
        });
    }
};

