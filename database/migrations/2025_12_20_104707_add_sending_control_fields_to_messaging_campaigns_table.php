<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            // NEW: reply-to email
            $table->string('reply_to_email')->nullable()->after('from_email');

            $table->timestamp('send_started_at')->nullable()->after('rejected_by');
            $table->timestamp('send_completed_at')->nullable()->after('send_started_at');
            $table->timestamp('last_send_run_at')->nullable()->after('send_completed_at');

            $table->unsignedInteger('daily_sent_count')->default(0)->after('last_send_run_at');
            $table->date('daily_sent_date')->nullable()->after('daily_sent_count');

            $table->index(['send_started_at']);
            $table->index(['send_completed_at']);
            $table->index(['daily_sent_date']);
        });
    }

    public function down(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->dropIndex(['send_started_at']);
            $table->dropIndex(['send_completed_at']);
            $table->dropIndex(['daily_sent_date']);

            $table->dropColumn([
                'reply_to_email',
                'send_started_at',
                'send_completed_at',
                'last_send_run_at',
                'daily_sent_count',
                'daily_sent_date',
            ]);
        });
    }
};
