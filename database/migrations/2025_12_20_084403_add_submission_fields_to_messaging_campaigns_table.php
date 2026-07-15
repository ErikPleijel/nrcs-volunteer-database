<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('lifecycle')->index();
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users');

            // Optional but very useful:
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');

            $table->dropConstrainedForeignId('rejected_by');
        });
    }
};
