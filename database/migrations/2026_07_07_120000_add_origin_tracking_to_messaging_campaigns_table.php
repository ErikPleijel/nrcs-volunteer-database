<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->enum('origin_level', ['national', 'branch'])->nullable()->after('scope_id');
            $table->unsignedBigInteger('origin_branch_id')->nullable()->after('origin_level');

            $table->foreign('origin_branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->dropForeign(['origin_branch_id']);
            $table->dropColumn(['origin_level', 'origin_branch_id']);
        });
    }
};
