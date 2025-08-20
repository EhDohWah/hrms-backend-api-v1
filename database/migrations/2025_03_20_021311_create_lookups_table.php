<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            ['type' => 'gender', 'value' => 'M', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'gender', 'value' => 'F', 'created_at' => now(), 'updated_at' => now()],

            // Subsidiary options
            ['type' => 'subsidiary', 'value' => 'SMRU', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'subsidiary', 'value' => 'BHF', 'created_at' => now(), 'updated_at' => now()],

            // Employee status
            ['type' => 'employee_status', 'value' => 'Expats (Local)', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_status', 'value' => 'Local ID Staff', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_status', 'value' => 'Local non ID Staff', 'created_at' => now(), 'updated_at' => now()],

            // Nationality options
            ['type' => 'nationality', 'value' => 'American', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'Australian', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'Burmese', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'N/A', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'Stateless', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'Taiwanese', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'nationality', 'value' => 'Thai', 'created_at' => now(), 'updated_at' => now()],

            // Religion options
            ['type' => 'religion', 'value' => 'Buddhist', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'religion', 'value' => 'Hindu', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'religion', 'value' => 'Christian', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'religion', 'value' => 'Muslim', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'religion', 'value' => 'Other', 'created_at' => now(), 'updated_at' => now()],

            // Marital status options
            ['type' => 'marital_status', 'value' => 'Single', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'marital_status', 'value' => 'Married', 'created_at' => now(), 'updated_at' => now()],

            // Site options
            ['type' => 'site', 'value' => 'Expat', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'MRM', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'WPA', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'KKH', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'TB-MRM', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'TB-KK', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'MKT', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'MSL', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'site', 'value' => 'Mutraw', 'created_at' => now(), 'updated_at' => now()],

            // User status options
            ['type' => 'user_status', 'value' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'user_status', 'value' => 'Inactive', 'created_at' => now(), 'updated_at' => now()],

            // Interview mode options
            ['type' => 'interview_mode', 'value' => 'In-person', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_mode', 'value' => 'Virtual', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_mode', 'value' => 'Phone', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_mode', 'value' => 'Hybrid', 'created_at' => now(), 'updated_at' => now()],

            // Interview status options
            ['type' => 'interview_status', 'value' => 'scheduled', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_status', 'value' => 'completed', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'interview_status', 'value' => 'cancelled', 'created_at' => now(), 'updated_at' => now()],

            // Identification types options
            ['type' => 'identification_types', 'value' => 'Certificate of Identity', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => 'Thai ID', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => '10 Years Card', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => 'Passport', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => 'Myanmar ID', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'identification_types', 'value' => 'N/A', 'created_at' => now(), 'updated_at' => now()],

            // Employment type options
            ['type' => 'employment_type', 'value' => 'Full-time', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employment_type', 'value' => 'Part-time', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employment_type', 'value' => 'Contract', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employment_type', 'value' => 'Temporary', 'created_at' => now(), 'updated_at' => now()],

            // Employee language options
            ['type' => 'employee_language', 'value' => 'English', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_language', 'value' => 'Thai', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_language', 'value' => 'Burmese', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_language', 'value' => 'Karen', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_language', 'value' => 'French', 'created_at' => now(), 'updated_at' => now()],

            // Employee education options
            ['type' => 'employee_education', 'value' => 'Bachelor', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_education', 'value' => 'Master', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_education', 'value' => 'PhD', 'created_at' => now(), 'updated_at' => now()],

            // Employee Initial in English (Mr., Mrs., Ms., Dr., etc.)
            ['type' => 'employee_initial_en', 'value' => 'Mr', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_initial_en', 'value' => 'Mrs', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_initial_en', 'value' => 'Ms', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_initial_en', 'value' => 'Dr', 'created_at' => now(), 'updated_at' => now()],

            // คำนำหน้านามพนักงาน (นาย, นาง, นางสาว, ดร. ฯลฯ)
            ['type' => 'employee_initial_th', 'value' => 'นาย',      'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_initial_th', 'value' => 'นางสาว',      'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_initial_th', 'value' => 'นาง',   'created_at' => now(), 'updated_at' => now()],
            ['type' => 'employee_initial_th', 'value' => 'ดร',      'created_at' => now(), 'updated_at' => now()],

            // Pay method options
            ['type' => 'pay_method', 'value' => 'Transferred to bank', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'pay_method', 'value' => 'Cash cheque', 'created_at' => now(), 'updated_at' => now()],

            // Section Department options
            ['type' => 'section_department', 'value' => 'Training', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Procurement & Stores', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Data', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Malaria Invitro', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Entomology', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Research', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Clinical', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'M&E', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Security', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Transportation', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Ultrasound', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'section_department', 'value' => 'Delivery', 'created_at' => now(), 'updated_at' => now()],

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
