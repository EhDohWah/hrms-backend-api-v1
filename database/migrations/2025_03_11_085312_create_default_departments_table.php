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
        // Insert default departments
        DB::table('departments')->insert([
            [
                'name' => 'Admin',
                'description' => 'Handles administrative tasks, office management, and general organizational support',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'HR',
                'description' => 'Manages recruitment, employee relations, benefits, and organizational development',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'DATA-MANAGEMENT',
                'description' => 'Responsible for data collection, analysis, storage, and reporting across the organization',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'IT',
                'description' => 'Provides technical support, infrastructure management, and software development',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'FINANCE',
                'description' => 'Oversees budgeting, accounting, financial reporting, and fiscal management',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'LAB',
                'description' => 'Conducts research, testing, and scientific analysis for organizational projects',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to drop the table as we're just inserting data
        // We can clear the inserted records if needed
        DB::table('departments')->whereIn('name', ['Admin', 'HR', 'DATA-MANAGEMENT', 'IT', 'FINANCE', 'LAB'])
            ->where('created_by', 'Migration')
            ->delete();
    }
};
