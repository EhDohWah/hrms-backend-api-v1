<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the departments table with the organizational structure.
 *
 * Environment: Production + Development
 * Idempotent: Yes — skips if departments already exist
 * Dependencies: None
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('departments')->count() > 0) {
            $this->command->info('Departments already seeded — skipping.');

            return;
        }

        DB::table('departments')->insert([
            ['name' => 'Administration', 'description' => 'Administrative operations and support services', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Finance', 'description' => 'Financial management and accounting operations', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Grant', 'description' => 'Grant management and funding oversight', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Human Resources', 'description' => 'Human Resources operations and employee management', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Logistics', 'description' => 'Logistics and transportation operations', 'created_at' => now(), 'updated_at' => now()],
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

        $this->command->info('Departments seeded: '.DB::table('departments')->count().' records.');
    }
}
