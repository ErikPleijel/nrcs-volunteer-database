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
        Schema::table('divisions', function (Blueprint $table) {
            $table->string('physical_address', 150)->nullable();
            $table->string('postal_address', 100)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 100)->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['physical_address', 'postal_address', 'telephone', 'email']);
        });
    }
};
