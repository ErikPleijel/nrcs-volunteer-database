<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('title', 50)->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->smallInteger('birth_year')->nullable();
            $table->enum('marital_status', ['single', 'married', 'other'])->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('user_code')->unique()->nullable();
            $table->string('national_id_number')->unique()->nullable();
            $table->string('red_cross_id_number')->nullable();
            $table->string('telephone1')->nullable();
            $table->string('telephone2')->nullable();
            $table->string('organisation')->nullable();
            $table->string('occupation')->nullable();
            $table->string('residential_address')->nullable();
            $table->string('workplace_address')->nullable();
            $table->string('disciplin')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('red_cross_unit_id')->nullable();




            // Public contact information
            $table->boolean('is_public_contact')->nullable();
            $table->string('public_contact_position')->nullable();

            // Personal information and contributions
            $table->text('personal_info')->nullable();
            $table->boolean('can_contribute_volunteering')->nullable();
            $table->boolean('can_contribute_member')->nullable();

            // Image and signature fields
            $table->string('picture', 100)->nullable();
            $table->boolean('is_picture_confirmed')->nullable();
            $table->date('image_upload_date')->nullable();
            $table->unsignedBigInteger('image_upload_id')->nullable();

            $table->string('signature', 100)->nullable();
            $table->boolean('is_signature_confirmed')->nullable();

            // ID card information
            $table->date('id_card_timestamp')->nullable();
            $table->unsignedTinyInteger('id_card_valid_years')->nullable();

            // Activity tracking
            $table->datetime('last_login_at')->nullable();
            $table->datetime('last_admin_activity_at')->nullable();
            $table->date('last_activity_at')->nullable();


            // Form registration
            $table->unsignedBigInteger('form_reg_id')->nullable();
            $table->boolean('is_form_registration')->nullable();

            // Deactivation information
            $table->date('deactivated_date')->nullable();
            $table->unsignedBigInteger('deactivated_by_id')->nullable();
            $table->boolean('is_inactive')->default(false)->comment('Legacy');;
            $table->boolean('is_account_activated')->default(false);

            // RCU assignment
            $table->date('assigned_rcu_date')->nullable();
            $table->unsignedBigInteger('assigned_rcu_by_id')->nullable();

            // Position information
        //    $table->unsignedSmallInteger('position_id')
         //       ->nullable()
         //       ->comment('Legacy');

       //     $table->unsignedTinyInteger('position_geo_level')
        //        ->nullable()
        //        ->comment('Legacy');

            // Custom timestamp field (in addition to Laravel's created_at/updated_at)
            $table->timestamp('custom_timestamp')->nullable()->useCurrent();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('code_of_conduct_accepted_at')->nullable();
            $table->string('password');
            $table->string('legacy_password_hash')->nullable()->comment('Password hash from old database system');
            $table->rememberToken();
            $table->timestamps();

            // **FIXED LINE: Removed after('password')**
            $table->boolean('is_super_admin')->default(false);
            // **FIXED LINE: Moved legacy_role definition here (it was implicitly after is_super_admin)**
            $table->string('legacy_role')->nullable()->comment('Legacy role from old database - use Spatie permissions instead');

            // Add indexes for frequently searched fields
            $table->index('first_name');
            $table->index('last_name');
            $table->index('user_code');
            $table->index('organisation');
            $table->index('occupation');
            $table->index('telephone1');
            $table->index('telephone2');
            $table->index('branch_id');
            $table->index('division_id');
            $table->index('red_cross_unit_id');


            $table->index('form_reg_id');
            $table->index('is_inactive');
            $table->index('is_account_activated');
            $table->index('deactivated_date');
            $table->index('deactivated_by_id');
            $table->index('last_login_at');
            $table->index('last_admin_activity_at');
            $table->index('last_activity_at');
            $table->index('assigned_rcu_date');
            $table->index('assigned_rcu_by_id');
      //      $table->index('position_id');
      //      $table->index('position_geo_level');
            $table->index('custom_timestamp');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Convert legacy is_account_activated to email_verified_at using created_at timestamp
        DB::statement('
            UPDATE users
            SET email_verified_at = created_at
            WHERE is_account_activated = 1
            AND email_verified_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
