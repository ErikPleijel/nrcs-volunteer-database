<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * skipped_no_longer_eligible — a pending recipient whose user no longer matches the
 * campaign filter when recipients are rebuilt (CampaignRecipientBuilder).
 */
return new class extends Migration
{
    private const WITHOUT = [
        'pending', 'queued', 'sent', 'delivered', 'failed', 'bounced', 'undeliverable', 'expired',
        'skipped_shared_number', 'skipped_invalid_number',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->setEnum([...self::WITHOUT, 'skipped_no_longer_eligible']);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('messaging_recipients')
            ->where('status', 'skipped_no_longer_eligible')
            ->update(['status' => 'undeliverable']);

        $this->setEnum(self::WITHOUT);
    }

    private function setEnum(array $values): void
    {
        $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));

        DB::statement("ALTER TABLE messaging_recipients MODIFY status ENUM({$list}) NOT NULL DEFAULT 'pending'");
    }
};
