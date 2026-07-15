<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('lifecycle_status', [
                'pending_engagement',
                'active',
                'dormant',
                'archived',
            ])
                ->default('pending_engagement')
                ->after('email_verified_at')  // adjust if you prefer another position
                ->index(); // important for filtering
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['lifecycle_status']);
            $table->dropColumn('lifecycle_status');
        });
    }
};
