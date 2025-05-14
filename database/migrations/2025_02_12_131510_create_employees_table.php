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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('department_position_id')->nullable();
            $table->string('subsidiary', 5)->index();
            $table->string('staff_id', 50)->index();
            $table->unique(['staff_id','subsidiary']);
            $table->string('initial_en', 5)->nullable();
            $table->string('initial_th', 20)->nullable();
            $table->string('first_name_en', 255)->nullable();
            $table->string('last_name_en', 255)->nullable();
            $table->string('first_name_th', 255)->nullable();
            $table->string('last_name_th', 255)->nullable();
            $table->string('gender', 10)->index();
            $table->date('date_of_birth')->index();
            $table->string('status', 20)->index();
            $table->string('nationality', 100)->nullable();
            $table->string('religion', 100)->nullable();
            $table->string('social_security_number', 50)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('mobile_phone', 10)->nullable();
            $table->text('permanent_address')->nullable();
            $table->text('current_address')->nullable();
            $table->string('military_status', 50)->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->string('spouse_name', 200)->nullable();
            $table->string('spouse_phone_number', 10)->nullable();
            $table->string('emergency_contact_person_name', 100)->nullable();
            $table->string('emergency_contact_person_relationship', 100)->nullable();
            $table->string('emergency_contact_person_phone', 10)->nullable();
            $table->string('father_name', 200)->nullable();
            $table->string('father_occupation', 200)->nullable();
            $table->string('father_phone_number', 10)->nullable();
            $table->string('mother_name', 200)->nullable();
            $table->string('mother_occupation', 200)->nullable();
            $table->string('mother_phone_number', 10)->nullable();
            $table->string('driver_license_number', 100)->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('department_position_id')->references('id')->on('department_positions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
