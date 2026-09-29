<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient delivery tracking for real providers (SMSLive247 first).
 * provider_message_id is what delivery-status polling will look rows up by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_recipients', function (Blueprint $table) {
            $table->string('provider', 40)->nullable()->after('status');
            $table->string('provider_message_id')->nullable()->after('provider');
            $table->string('provider_batch_id')->nullable()->after('provider_message_id');
            $table->string('channel_used', 10)->nullable()->after('provider_batch_id'); // email | sms
            $table->unsignedSmallInteger('segments')->nullable()->after('channel_used');
            $table->decimal('cost', 10, 4)->nullable()->after('segments');
            $table->string('provider_status')->nullable()->after('cost');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');

            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('messaging_recipients', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn([
                'provider', 'provider_message_id', 'provider_batch_id', 'channel_used',
                'segments', 'cost', 'provider_status', 'delivered_at',
            ]);
        });
    }
};
