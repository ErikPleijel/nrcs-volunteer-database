<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('consent_obtained_at')->nullable()->after('code_of_conduct_accepted_at');
            $table->unsignedBigInteger('consent_obtained_by_id')->nullable()->after('consent_obtained_at');
            $table->text('consent_notes')->nullable()->after('consent_obtained_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['consent_obtained_at', 'consent_obtained_by_id', 'consent_notes']);
        });
    }
};
