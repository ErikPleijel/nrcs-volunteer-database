<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('division_id')->nullable();

            // Lifecycle counts — NULL means "not known" (backfilled rows)
            $table->unsignedInteger('pending_engagement')->nullable();
            $table->unsignedInteger('active')->nullable();
            $table->unsignedInteger('dormant')->nullable();
            $table->unsignedInteger('archived')->nullable();

            // Members (canonical User::members() definition)
            $table->unsignedInteger('members_total')->nullable();
            $table->unsignedInteger('members_men')->nullable();
            $table->unsignedInteger('members_women')->nullable();

            // Volunteers (canonical User::volunteers() definition)
            $table->unsignedInteger('volunteers_total')->nullable();
            $table->unsignedInteger('volunteers_men')->nullable();
            $table->unsignedInteger('volunteers_women')->nullable();

            // Archive-hygiene indicator
            $table->decimal('dormant_avg_days_inactive', 8, 1)->nullable();

            $table->boolean('is_backfilled')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->unique(['snapshot_date', 'branch_id', 'division_id'], 'snapshot_scope_unique');
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_snapshots');
    }
};
