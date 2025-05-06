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
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type');                // e.g., 'gender', 'employee_status', etc.
            $table->string('value');               // The display value
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });

        // Insert default lookup values
        DB::table('lookups')->insert([
            // Gender options
            ['type' => 'gender', 'value' => 'Male', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'gender', 'value' => 'Female', 'created_at' => now(), 'updated_at' => now()],

            // Subsidiary options
            ['type' => 'subsidiary', 'value' => 'SMRU', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'subsidiary', 'value' => 'BHF', 'created_at' => now(), 'updated_at' => now()],

            // Employee status
            ['type' => 'employee_status', 'value' => 'Expats (Local)', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_status', 'value' => 'Local ID Staff', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_status', 'value' => 'Local non ID Staff', 'created_at' => now(), 'updated_at' => now()],

            // Nationality options
            ['type' => 'nationality', 'value' => 'Thai', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'Burmese', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'Karen', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'French', 'created_at' => now(), 'updated_at' => now()],

            // Religion options
            ['type' => 'religion', 'value' => 'Buddhism', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'religion', 'value' => 'Hinduism', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'religion', 'value' => 'Christianity', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'religion', 'value' => 'Muslim', 'created_at' => now(), 'updated_at' => now()],

            // Marital status options
            ['type' => 'marital_status', 'value' => 'Single', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'marital_status', 'value' => 'Married', 'created_at' => now(), 'updated_at' => now()],

            // Site options
            ['type' => 'site', 'value' => 'MKT', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'WPA', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'MSL', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'MRM', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'MRMTB', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'Headquarters', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'Field Office', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'Mobile Clinic', 'created_at' => now(), 'updated_at' => now()],

            // User status options
            ['type' => 'user_status', 'value' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'user_status', 'value' => 'Inactive', 'created_at' => now(), 'updated_at' => now()],

            // Interview mode options
            ['type' => 'interview_mode', 'value' => 'in-person', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_mode', 'value' => 'virtual', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_mode', 'value' => 'phone', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_mode', 'value' => 'hybrid', 'created_at' => now(), 'updated_at' => now()],

            // Interview status options
            ['type' => 'interview_status', 'value' => 'scheduled', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_status', 'value' => 'completed', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_status', 'value' => 'cancelled', 'created_at' => now(), 'updated_at' => now()],

            // Identification types options
            ['type' => 'identification_types', 'value' => 'passport', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => 'thai_id', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => '10years_id', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => 'other', 'created_at' => now(), 'updated_at' => now()],

            // Employment type options
            ['type' => 'employment_type', 'value' => 'full_time', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employment_type', 'value' => 'part_time', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employment_type', 'value' => 'contract', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employment_type', 'value' => 'temporary', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
