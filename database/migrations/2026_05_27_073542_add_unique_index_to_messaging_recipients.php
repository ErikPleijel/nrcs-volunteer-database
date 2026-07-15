<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_recipients', function (Blueprint $table) {
            $table->unique(
                ['messaging_campaign_id', 'recipient_type', 'recipient_id'],
                'messaging_recipients_campaign_recipient_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('messaging_recipients', function (Blueprint $table) {
            $table->dropUnique('messaging_recipients_campaign_recipient_unique');
        });
    }
};
