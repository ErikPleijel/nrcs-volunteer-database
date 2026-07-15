<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — two-step approval workflow foundation.
 *
 * Adds an identical approval layer to the four record tables (donations,
 * membership_payments, activities, trainings). This is intentionally
 * independent of the existing deletion mechanism: rejection is a status,
 * NOT a delete. The is_deleted / SoftDeletes divergence is left untouched.
 *
 * All pre-existing rows are backfilled to 'approved' so they remain visible
 * once the ApprovedScope global scope goes live.
 */
return new class extends Migration
{
    /**
     * The four module tables that receive the approval layer.
     *
     * @var string[]
     */
    private array $tables = ['donations', 'membership_payments', 'activities', 'trainings'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('approval_status')->default('pending')->index()->after('id');
                $table->foreignId('decided_by_user_id')->nullable()->after('approval_status')
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable()->after('decided_by_user_id');
                $table->text('rejection_reason')->nullable()->after('decided_at');
            });

            // Backfill every existing row so current data stays visible under the
            // ApprovedScope. decided_at mirrors created_at; no human made the call,
            // so decided_by_user_id stays NULL.
            DB::table($t)->update([
                'approval_status'    => 'approved',
                'decided_by_user_id' => null,
                'decided_at'         => DB::raw('created_at'),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('decided_by_user_id');
                $table->dropColumn(['approval_status', 'decided_at', 'rejection_reason']);
            });
        }
    }
};
