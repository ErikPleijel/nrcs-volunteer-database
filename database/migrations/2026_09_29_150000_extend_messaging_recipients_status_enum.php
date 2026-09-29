<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the SMS-era recipient statuses to the messaging_recipients.status ENUM.
 *
 * queued                  — claimed by the send runner (or handed to the provider),
 *                           final result not yet recorded. Never auto-retried, so a
 *                           crash between send and write cannot cause a re-send.
 * delivered / expired     — provider delivery reports (status polling, later).
 * skipped_shared_number   — another recipient in the campaign gets the SMS at this number.
 * skipped_invalid_number  — needs SMS, but neither telephone is a valid Nigerian mobile.
 *
 * Kept as an ENUM (see Decisions.md) so the database still rejects unknown values.
 * MySQL-only raw ALTER; other drivers (the unused sqlite test config) are skipped.
 */
return new class extends Migration
{
    private const OLD = ['pending', 'sent', 'failed', 'bounced', 'undeliverable'];

    private const NEW = [
        'pending', 'queued', 'sent', 'delivered', 'failed', 'bounced', 'undeliverable', 'expired',
        'skipped_shared_number', 'skipped_invalid_number',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->setEnum(self::NEW);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Map new values onto the nearest old one before shrinking the ENUM.
        DB::table('messaging_recipients')->where('status', 'queued')->update(['status' => 'pending']);
        DB::table('messaging_recipients')->where('status', 'delivered')->update(['status' => 'sent']);
        DB::table('messaging_recipients')
            ->whereIn('status', ['expired', 'skipped_shared_number', 'skipped_invalid_number'])
            ->update(['status' => 'undeliverable']);

        $this->setEnum(self::OLD);
    }

    private function setEnum(array $values): void
    {
        $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));

        DB::statement("ALTER TABLE messaging_recipients MODIFY status ENUM({$list}) NOT NULL DEFAULT 'pending'");
    }
};
