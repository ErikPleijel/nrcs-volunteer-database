<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_opt_out')->default(false)->after('email');
            $table->timestamp('email_opt_out_at')->nullable()->after('email_opt_out');
            $table->boolean('sms_opt_out')->default(false)->after('email_opt_out_at');
            $table->timestamp('sms_opt_out_at')->nullable()->after('sms_opt_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_opt_out', 'email_opt_out_at', 'sms_opt_out', 'sms_opt_out_at']);
        });
    }
};
