<?php

// File: 2025_02_12_025437_create_departments_table.php
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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // Seed departments data based on your organizational structure
        DB::table('departments')->insert([
            ['name' => 'Administration', 'description' => 'Administrative operations and support services', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Finance', 'description' => 'Financial management and accounting operations', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Grant', 'description' => 'Grant management and funding oversight', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'HR', 'description' => 'Human Resources operations and employee management', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Logistic', 'description' => 'Logistics and transportation operations', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Procurement & Store', 'description' => 'Procurement and inventory management', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Data Management', 'description' => 'Data operations and management systems', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'IT', 'description' => 'Information Technology services and support', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Clinical', 'description' => 'Clinical services and healthcare delivery', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Medical', 'description' => 'Medical services and physician oversight', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Research/Study', 'description' => 'Research operations and clinical studies', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Training', 'description' => 'Training programs and capacity building', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Research/Study M&E', 'description' => 'Research monitoring and evaluation', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'MCH', 'description' => 'Maternal and Child Health programs', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'M&E', 'description' => 'Monitoring and Evaluation operations', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Laboratory', 'description' => 'Laboratory services and testing', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Malaria', 'description' => 'Malaria prevention and control programs', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Public Engagement', 'description' => 'Public engagement and community outreach', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'TB', 'description' => 'Tuberculosis prevention and treatment programs', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Media Group', 'description' => 'Media and communications management', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Referral', 'description' => 'Patient referral services and coordination', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
