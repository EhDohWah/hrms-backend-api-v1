<?php

namespace Database\Seeders;

use App\Models\BudgetLine;
use Illuminate\Database\Seeder;

class BudgetLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $budgetLines = [
            // Research & Development Budget Lines
            [
                'budget_line_code' => 'RD001',
                'description' => 'Marine Research - Scientific Personnel',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'RD002',
                'description' => 'Biomedical Research - Laboratory Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'RD003',
                'description' => 'Environmental Studies - Field Researchers',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'RD004',
                'description' => 'Data Science & Analytics - Technical Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'RD005',
                'description' => 'Clinical Research - Medical Personnel',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],

            // Administrative & Support Budget Lines
            [
                'budget_line_code' => 'AS001',
                'description' => 'Administrative Support - General Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'AS002',
                'description' => 'Human Resources - HR Personnel',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'AS003',
                'description' => 'Finance & Accounting - Financial Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'AS004',
                'description' => 'IT Support & Services - Technical Support',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'AS005',
                'description' => 'Facilities Management - Maintenance Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],

            // Project Management Budget Lines
            [
                'budget_line_code' => 'PM001',
                'description' => 'Project Management - Senior Managers',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'PM002',
                'description' => 'Program Coordination - Program Officers',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'PM003',
                'description' => 'Quality Assurance - QA Personnel',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],

            // Academic & Education Budget Lines
            [
                'budget_line_code' => 'AE001',
                'description' => 'Academic Faculty - Professors & Lecturers',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'AE002',
                'description' => 'Student Support - Teaching Assistants',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'AE003',
                'description' => 'Library & Archives - Information Specialists',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],

            // Specialized Operations Budget Lines
            [
                'budget_line_code' => 'SO001',
                'description' => 'Marine Operations - Vessel Crew',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'SO002',
                'description' => 'Laboratory Operations - Lab Technicians',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'SO003',
                'description' => 'Field Operations - Field Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'SO004',
                'description' => 'Equipment Maintenance - Technical Specialists',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],

            // External Relations Budget Lines
            [
                'budget_line_code' => 'ER001',
                'description' => 'Communications & Outreach - Communications Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'ER002',
                'description' => 'Partnership Development - Business Development',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'ER003',
                'description' => 'Grant Management - Grant Officers',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],

            // Emergency & Contingency Budget Lines
            [
                'budget_line_code' => 'EC001',
                'description' => 'Emergency Response - Emergency Personnel',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'EC002',
                'description' => 'Contingency Operations - Temporary Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],

            // Legacy Budget Lines (for backward compatibility)
            [
                'budget_line_code' => 'BL001',
                'description' => 'General Operations - Standard Personnel',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'BL002',
                'description' => 'Research Support - Research Assistants',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'budget_line_code' => 'BL003',
                'description' => 'Administrative Operations - Admin Staff',
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
        ];

        // Create budget lines (using firstOrCreate to avoid duplicates)
        $this->command->info('Creating budget lines...');
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($budgetLines as $budgetLineData) {
            $budgetLine = BudgetLine::firstOrCreate(
                ['budget_line_code' => $budgetLineData['budget_line_code']],
                $budgetLineData
            );

            if ($budgetLine->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $skippedCount++;
            }
        }

        // Display summary
        $this->command->info('Budget Line seeding completed!');
        $this->command->info('Summary:');
        $this->command->info('- New Budget Lines Created: '.$createdCount);
        $this->command->info('- Existing Budget Lines Skipped: '.$skippedCount);
        $this->command->info('- Total Budget Lines in Database: '.BudgetLine::count());

        // Show budget lines by category
        $this->command->info('');
        $this->command->info('Budget Lines by Category:');

        $categories = [
            'RD' => 'Research & Development',
            'AS' => 'Administrative & Support',
            'PM' => 'Project Management',
            'AE' => 'Academic & Education',
            'SO' => 'Specialized Operations',
            'ER' => 'External Relations',
            'EC' => 'Emergency & Contingency',
            'BL' => 'Legacy Operations',
        ];

        foreach ($categories as $prefix => $categoryName) {
            $count = BudgetLine::where('budget_line_code', 'LIKE', $prefix.'%')->count();
            $this->command->info("- {$categoryName}: {$count} budget lines");
        }

        // Display some examples
        $this->command->info('');
        $this->command->info('Sample Budget Lines:');
        $samples = BudgetLine::take(5)->get();
        foreach ($samples as $sample) {
            $this->command->info("- {$sample->budget_line_code}: {$sample->description}");
        }
    }
}
