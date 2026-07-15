<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Unique key, e.g. "membership.dormant_after_months"
            $table->string('key')->unique();

            // All values stored as text; you can JSON-encode if needed
            $table->text('value')->nullable();

            // Optional helpers
            $table->string('type')->default('string');      // string, int, bool, json, etc.
            $table->string('group')->nullable();            // membership, site, social, etc.
            $table->string('label')->nullable();            // friendly label for admin UI
            $table->text('description')->nullable();        // help text in admin UI

            // If true, you might autoload it into cache/app config
            $table->boolean('autoload')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
