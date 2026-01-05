<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get section departments from lookups if they exist
        $lookupSections = DB::table('lookups')
            ->where('type', 'section_department')
            ->get();

        if ($lookupSections->isNotEmpty()) {
            foreach ($lookupSections as $lookup) {
                // Try to find matching department
                $department = DB::table('departments')
                    ->where('name', 'LIKE', '%'.$lookup->value.'%')
                    ->first();

                if ($department) {
                    // Check if already exists
                    $exists = DB::table('section_departments')
                        ->where('name', $lookup->value)
                        ->where('department_id', $department->id)
                        ->exists();

                    if (! $exists) {
                        DB::table('section_departments')->insert([
                            'name' => $lookup->value,
                            'department_id' => $department->id,
                            'description' => $lookup->description ?? "Sub-department: {$lookup->value}",
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'created_by' => 'Seeder',
                            'updated_by' => 'Seeder',
                        ]);
                    }
                }
            }
        } else {
            // Fallback: Create some common section departments
            $commonSections = [
                ['name' => 'Training', 'description' => 'Training and Development Section'],
                ['name' => 'Data Management', 'description' => 'Data Collection and Management'],
                ['name' => 'M&E', 'description' => 'Monitoring and Evaluation'],
                ['name' => 'Administration', 'description' => 'Administrative Support'],
                ['name' => 'Finance', 'description' => 'Financial Operations'],
                ['name' => 'HR', 'description' => 'Human Resources'],
                ['name' => 'IT Support', 'description' => 'Information Technology Support'],
                ['name' => 'Procurement', 'description' => 'Procurement and Supply'],
                ['name' => 'Research', 'description' => 'Research and Analysis'],
                ['name' => 'Outreach', 'description' => 'Community Outreach'],
            ];

            // Get the first department to associate these with
            $firstDept = DB::table('departments')->first();
            if ($firstDept) {
                foreach ($commonSections as $section) {
                    $exists = DB::table('section_departments')
                        ->where('name', $section['name'])
                        ->where('department_id', $firstDept->id)
                        ->exists();

                    if (! $exists) {
                        DB::table('section_departments')->insert(array_merge($section, [
                            'department_id' => $firstDept->id,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'created_by' => 'Seeder',
                            'updated_by' => 'Seeder',
                        ]));
                    }
                }
            }
        }

        $this->command->info('Section departments seeded successfully.');
    }
}
