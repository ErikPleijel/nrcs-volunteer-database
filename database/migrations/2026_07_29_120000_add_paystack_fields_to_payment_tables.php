<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Paystack-related fields to the two record tables that can be paid
 * for online. payment_channel distinguishes existing manual entries
 * ('manual', the default) from new online payments ('paystack').
 * gateway_reference/gateway_response are populated only for the latter.
 */
return new class extends Migration
{
    private array $tables = ['donations', 'membership_payments'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('payment_channel')->nullable()->default('manual')->after('reference');
                $table->string('gateway_reference')->nullable()->unique()->after('payment_channel');
                $table->json('gateway_response')->nullable()->after('gateway_reference');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropUnique(['gateway_reference']);
                $table->dropColumn(['payment_channel', 'gateway_reference', 'gateway_response']);
            });
        }
    }
};
