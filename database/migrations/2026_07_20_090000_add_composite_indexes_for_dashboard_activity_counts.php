<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_recipients', function (Blueprint $table) {
            // Covers the dashboard's "messages sent in the last 7 days" count:
            // WHERE status = 'sent' AND sent_at >= ?
            // The existing (messaging_campaign_id, status) index doesn't lead with
            // status, so this query can't use it and falls back to a full scan.
            $table->index(['status', 'sent_at'], 'messaging_recipients_status_sent_at_index');
        });

        Schema::table('id_card_prints', function (Blueprint $table) {
            // Covers the dashboard's "ID cards printed in the last 7 days" count:
            // WHERE status = 'printed' AND printed_at >= ?
            // Neither column is indexed on this table today.
            $table->index(['status', 'printed_at'], 'id_card_prints_status_printed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('messaging_recipients', function (Blueprint $table) {
            $table->dropIndex('messaging_recipients_status_sent_at_index');
        });

        Schema::table('id_card_prints', function (Blueprint $table) {
            $table->dropIndex('id_card_prints_status_printed_at_index');
        });
    }
};
