<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->date('deactivated_date')->nullable()->after('branch_id');
            $table->unsignedBigInteger('deactivated_by_id')->nullable()->after('deactivated_date');
            $table->softDeletes()->after('deactivated_by_id');

            $table->foreign('deactivated_by_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropForeign(['deactivated_by_id']);
            $table->dropColumn(['deactivated_date', 'deactivated_by_id', 'deleted_at']);
        });
    }
};
