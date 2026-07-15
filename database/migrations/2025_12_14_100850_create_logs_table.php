<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();

            // Who performed the action
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // What happened (e.g. payment_deleted, member_branch_changed)
            $table->string('action', 100);

            // Polymorphic target of the action
            $table->nullableMorphs('subject'); // creates subject_id (bigint) & subject_type (string) nullable

            // Context: branch and division, if applicable
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();

            // Human-readable description
            $table->string('description', 255)->nullable();

            // Before/after snapshots (JSON)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->timestamps();

            // Helpful indexes for filtering
            $table->index('action');
            $table->index(['branch_id', 'division_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
