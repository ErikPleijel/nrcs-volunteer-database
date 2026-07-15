<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->foreignId('purpose_id')
                  ->nullable()
                  ->after('audience_type')
                  ->constrained('campaign_purposes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\CampaignPurpose::class);
            $table->dropColumn('purpose_id');
        });
    }
};
