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
            $table->string('staff_id', 50)->unique(); // staff_id is unique
            //add subsdiary id
            $table->enum('subsidiary', ['SMRU', 'BHF'])->default('SMRU'); // subsidiary is required
            $table->unsignedBigInteger('user_id')->nullable(); // user_id is optional
            $table->string('first_name', 255); // first_name is required
            $table->string('middle_name', 255)->nullable(); // middle_name is optional
            $table->string('last_name', 255); // last_name is required
            $table->string('email', 255)->nullable()->unique(); // email is optional must be unique
            $table->string('profile_picture', 255)->nullable(); // profile_picture is optional
            $table->string('gender', 10); // Gender is required
            $table->date('date_of_birth'); // date_of_birth is required
            $table->enum('status', [
                'Expats',
                'Local ID',
                'Local non ID'
            ])->default('Expats'); // status is required
            $table->string('religion', 100)->nullable(); // religion is optional
            $table->string('birth_place', 100)->nullable(); // birth_place is optional
            $table->string('identification_number', 50)->nullable(); // identification_number is optional
            $table->string('social_security_number', 50)->nullable(); // social_security_number is optional
            $table->string('tax_number', 50)->nullable(); // tax_number is optional
            $table->string('passport_number', 50)->nullable(); // passport_number is optional
            $table->string('bank_name', 100)->nullable(); // bank_name is optional
            $table->string('bank_branch', 100)->nullable(); // bank_branch is optional
            $table->string('bank_account_name', 100)->nullable(); // bank_account_name is optional
            $table->string('bank_account_number', 100)->nullable(); // bank_account_number is optional
            $table->string('office_phone', 20)->nullable(); // office_phone is optional
            $table->string('mobile_phone', 20)->nullable(); // mobile_phone is optional
            $table->string('permanent_address')->nullable(); // permanent_address is optional
            $table->string('current_address')->nullable(); // current_address is optional
            $table->string('stay_with', 100)->nullable(); // stay_with is optional
            $table->boolean('military_status')->default(false); // military_status is required
            $table->string('marital_status', 20)->nullable(); // marital_status is optional
            $table->string('spouse_name', 100)->nullable(); // spouse_name is optional
            $table->string('spouse_occupation', 100)->nullable(); // spouse_occupation is optional
            $table->string('father_name', 100)->nullable(); // father_name is optional
            $table->string('father_occupation', 100)->nullable(); // father_occupation is optional
            $table->string('mother_name', 100)->nullable(); // mother_name is optional
            $table->string('mother_occupation', 100)->nullable(); // mother_occupation is optional
            $table->string('driver_license_number', 50)->nullable(); // driver_license_number is optional
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->nullable();
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
