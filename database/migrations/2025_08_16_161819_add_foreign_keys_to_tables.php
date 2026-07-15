<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (!Schema::hasColumn('divisions', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable();
            }
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'division_id')) {
                $table->unsignedBigInteger('division_id')->nullable();
            }

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('division_id')->references('id')->on('divisions')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['division_id']);
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });
    }
};
