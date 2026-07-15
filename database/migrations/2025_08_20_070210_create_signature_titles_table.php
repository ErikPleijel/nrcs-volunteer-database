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
        Schema::create('signature_titles', function (Blueprint $table) {
            $table->id(); // SignatureTitleID as primary key
            $table->string('name', 60); // SignatureTitleName
            $table->unsignedTinyInteger('level')->default(2); // Level (default 2)
            $table->boolean('include_in_list')->default(true); // Include_in_list (default 1)
            $table->timestamps();

            // Add indexes for better performance
            $table->index('level');
            $table->index('include_in_list');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signature_titles');
    }
};
