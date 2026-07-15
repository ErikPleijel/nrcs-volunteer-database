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
        Schema::table('training_types', function (Blueprint $table) {
            $table->boolean('is_first_aid')->default(false)->after('certificate_hq_only');
            // After running, set is_first_aid = true on relevant training types via tinker or DB admin.
        });
    }

    public function down(): void
    {
        Schema::table('training_types', function (Blueprint $table) {
            $table->dropColumn('is_first_aid');
        });
    }
};
