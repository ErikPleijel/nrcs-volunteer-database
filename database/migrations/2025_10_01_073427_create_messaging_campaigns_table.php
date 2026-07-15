<?php
// database/migrations/2025_10_01_000000_create_messaging_campaigns_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('messaging_campaigns', function (Blueprint $table) {
            $table->id();

            // Core
            $table->string('channel', 20); // 'email' | 'sms' | 'both' (if you later allow both)
            $table->string('audience_type', 40); // 'member' | 'volunteer' | ...
            $table->string('title')->nullable(); // internal label
            $table->string('subject')->nullable(); // email only
            $table->longText('body'); // template (email HTML or SMS text)
            $table->json('filter_json'); // the originating filter

            // Sender identity
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_phone')->nullable(); // SMS sender ID if applicable

            // Governance + scope (NEW)
            $table->string('scope_level', 40)->nullable(); // 'national'|'division'|'branch'|'unit' (match getAccessLevel())
            $table->unsignedBigInteger('scope_id')->nullable(); // match getScopedId(); NULL for national
            $table->string('lifecycle', 40)->nullable(); // awaiting_assignment|active|dormant|archived (optional but useful)

            // Approval (NEW)
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('review_note')->nullable();

            // Status (UPDATED)
            $table->enum('status', [
                'draft',     // creator is working
                'proposed',  // submitted for approval
                'approved',  // approved, ready to queue/send
                'rejected',  // rejected (with note)
                'queued',    // queued for processing
                'sending',   // currently sending
                'sent',      // finished
                'cancelled', // stopped before completion
            ])->default('draft');

            // Delivery stats
            $table->unsignedInteger('stats_total')->default(0);
            $table->unsignedInteger('stats_sent')->default(0);
            $table->unsignedInteger('stats_failed')->default(0);

            // Ownership
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            // Helpful indexes
            $table->index(['status']);
            $table->index(['scope_level', 'scope_id']);
            $table->index(['lifecycle']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('messaging_campaigns');
    }
};
