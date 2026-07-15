<?php

// database/migrations/2025_10_01_000001_create_messaging_recipients_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('messaging_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messaging_campaign_id')
                ->constrained('messaging_campaigns')
                ->cascadeOnDelete();
            $table->string('recipient_type'); // e.g. App\Models\User
            $table->unsignedBigInteger('recipient_id');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('payload_json')->nullable(); // snapshot of tokens
            $table->enum('status', ['pending','sent','failed','bounced','undeliverable'])->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_type','recipient_id']);
            $table->index(['messaging_campaign_id','status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('messaging_recipients');
    }
};
