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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions')->onDelete('no action');
            $table->integer('level')->default(1)->comment('Hierarchy level (1 = top level, 2 = reports to level 1, etc.)');
            $table->boolean('is_manager')->default(false)->comment('Is this a managerial position?');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Indexes for better performance
            $table->index(['department_id', 'is_active']);
            $table->index('reports_to_position_id');
            $table->index(['department_id', 'level']);
        });

        // Seed positions data based on your organizational structure
        $this->seedPositionsFromOrganizationalChart();
    }

    /**
     * Seed positions data from the provided organizational chart
     */
    private function seedPositionsFromOrganizationalChart(): void
    {
        // Get department IDs
        $departments = DB::table('departments')->pluck('id', 'name');

        // First, create all positions without reporting relationships
        $positions = [
            // Administration Department
            ['title' => 'Administrator', 'department' => 'Administration', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Administrative Officer', 'department' => 'Administration', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Administrator'],
            ['title' => 'Sr. Administrative Assistant', 'department' => 'Administration', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Administrator'],
            ['title' => 'Administrative Assistant', 'department' => 'Administration', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Administrator'],
            ['title' => 'Referral Supervisor', 'department' => 'Administration', 'is_manager' => true, 'level' => 2, 'reports_to' => 'Administrator'],
            ['title' => 'Referral staff', 'department' => 'Administration', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Administrator'],
            ['title' => 'Cleaner', 'department' => 'Administration', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Administrator'],
            ['title' => 'Cook', 'department' => 'Administration', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Administrator'],

            // Finance Department
            ['title' => 'Accountant Manager/Finance Manager', 'department' => 'Finance', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Sr. Accountant', 'department' => 'Finance', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Finance Manager'],
            ['title' => 'Accountant Assistant/Accountant/Finance Assistant', 'department' => 'Finance', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Finance Manager'],
            ['title' => 'Book keeper', 'department' => 'Finance', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Finance Manager'],
            ['title' => 'Book keeping Assistant.', 'department' => 'Finance', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Finance Manager'],

            // Grant Department
            ['title' => 'Grant Manager', 'department' => 'Grant', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Grant Senior Officer', 'department' => 'Grant', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Grant Manager/Administrator (CO)'],
            ['title' => 'Assistant of Grant Officer', 'department' => 'Grant', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Grant Manager/Administrator (CO)'],

            // Human Resources Department
            ['title' => 'HR Manager', 'department' => 'Human Resources', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Sr.HR Assistant/Sr. Capacity Building Officer', 'department' => 'Human Resources', 'is_manager' => false, 'level' => 2, 'reports_to' => 'HR Manager'],
            ['title' => 'HR Assistant/Capacity Building Officer', 'department' => 'Human Resources', 'is_manager' => false, 'level' => 3, 'reports_to' => 'HR Manager'],
            ['title' => 'HR Junior Assistant.', 'department' => 'Human Resources', 'is_manager' => false, 'level' => 4, 'reports_to' => 'HR Manager'],

            // Logistics Department
            ['title' => 'Logistic Manager', 'department' => 'Logistics', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Senior Logistic Assistant', 'department' => 'Logistics', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Logistics Manager'],
            ['title' => 'Logistic Assistant', 'department' => 'Logistics', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Logistics Manager'],
            ['title' => 'Transportation in charge', 'department' => 'Logistics', 'is_manager' => true, 'level' => 2, 'reports_to' => 'Logistics Manager'],
            ['title' => 'Driver', 'department' => 'Logistics', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Logistics Manager'],
            ['title' => 'Security guard', 'department' => 'Logistics', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Logistics Manager'],

            // Procurement & Store Department
            ['title' => 'Procurement & Store Manager', 'department' => 'Procurement & Store', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Procurement Officer', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Procurement & Store Manager'],
            ['title' => 'Sr. Purchaser', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Procurement & Store Manager'],
            ['title' => 'Purchaser', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Procurement & Store Manager'],
            ['title' => 'Asst. Purchaser', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Procurement & Store Manager'],
            ['title' => 'Inventory & Procurement Officer', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Procurement & Store Manager'],
            ['title' => 'Pharmacist', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Procurement & Store Manager'],
            ['title' => 'Store keeper/Pharmacy stock keeper', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Procurement & Store Manager'],
            ['title' => 'Store Keeper Asst.', 'department' => 'Procurement & Store', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Procurement & Store Manager'],

            // Data management Department
            ['title' => 'Sr. Data Manager', 'department' => 'Data management', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Data Manager', 'department' => 'Data management', 'is_manager' => true, 'level' => 2, 'reports_to' => 'Data management Manager'],
            ['title' => 'Data Officer', 'department' => 'Data management', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Data management Manager'],
            ['title' => 'Data Manager Assistant', 'department' => 'Data management', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Data management Manager'],
            ['title' => 'Data entry/Data clerk', 'department' => 'Data management', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Data management Manager'],

            // IT Department
            ['title' => 'IT Manager', 'department' => 'IT', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'IT Specialist', 'department' => 'IT', 'is_manager' => false, 'level' => 2, 'reports_to' => 'IT Specialist'],
            ['title' => 'Sr. IT System Administrator', 'department' => 'IT', 'is_manager' => false, 'level' => 2, 'reports_to' => 'IT Specialist'],
            ['title' => 'IT System Administrator', 'department' => 'IT', 'is_manager' => false, 'level' => 3, 'reports_to' => 'IT Specialist'],
            ['title' => 'Senior IT Officer', 'department' => 'IT', 'is_manager' => false, 'level' => 3, 'reports_to' => 'IT Specialist'],
            ['title' => 'Senior IT helpdesk', 'department' => 'IT', 'is_manager' => false, 'level' => 3, 'reports_to' => 'IT Specialist'],
            ['title' => 'IT helpdesk', 'department' => 'IT', 'is_manager' => false, 'level' => 4, 'reports_to' => 'IT Specialist'],

            // Clinical Department
            ['title' => 'Physician/Medical doctor', 'department' => 'Clinical', 'is_manager' => true, 'level' => 1, 'reports_to' => null],

            // Research/Study Department
            ['title' => 'Senior Clinician Scientist', 'department' => 'Research/Study', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Researcher', 'department' => 'Research/Study', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Senior Clinician Scientist'],
            ['title' => 'Clinical Research Officer (Senior)', 'department' => 'Research/Study', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Senior Clinician Scientist'],
            ['title' => 'Clinical Research Assistant/Research Assistant', 'department' => 'Research/Study', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Senior Clinician Scientist'],
            ['title' => 'Research Staff', 'department' => 'Research/Study', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Senior Clinician Scientist'],

            // Training Department
            ['title' => 'Trainer', 'department' => 'Training', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Sr. Training Coordinator', 'department' => 'Training', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Trainer'],
            ['title' => 'Training Coordinator', 'department' => 'Training', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Trainer'],
            ['title' => 'Trainer Asst.', 'department' => 'Training', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Trainer'],

            // Research/Study M&E Department
            ['title' => 'M&E Officer', 'department' => 'Research/Study M&E', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'MonitoringM&E Staff', 'department' => 'Research/Study M&E', 'is_manager' => false, 'level' => 2, 'reports_to' => 'M&E Supervisor'],
            ['title' => 'M&E Assistant', 'department' => 'Research/Study M&E', 'is_manager' => false, 'level' => 3, 'reports_to' => 'M&E Supervisor'],

            // MCH Department
            ['title' => 'Physician/OB Doctor', 'department' => 'MCH', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Public Health Manager/MCH Manager', 'department' => 'MCH', 'is_manager' => true, 'level' => 2, 'reports_to' => 'Physician/Clinical'],
            ['title' => 'Public Health Coordinator', 'department' => 'MCH', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Physician/Clinical'],
            ['title' => 'Vaccine Coordinator', 'department' => 'MCH', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Physician/Clinical'],
            ['title' => 'Vaccine Assistant', 'department' => 'MCH', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Physician/Clinical'],
            ['title' => 'Sonographer in charge', 'department' => 'MCH', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Physician/Clinical'],
            ['title' => 'Counsellor Supervisor Senior', 'department' => 'MCH', 'is_manager' => true, 'level' => 3, 'reports_to' => 'Physician/Clinical'],
            ['title' => 'Counsellor Supervisor', 'department' => 'MCH', 'is_manager' => true, 'level' => 4, 'reports_to' => 'Physician/Clinical'],
            ['title' => 'Sonographer in Charge', 'department' => 'MCH', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Physician/Clinical'],

            // M&E Department
            ['title' => 'M&E Supervisor', 'department' => 'M&E', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'M&E Coordinator', 'department' => 'M&E', 'is_manager' => false, 'level' => 2, 'reports_to' => 'M&E Supervisor'],
            ['title' => 'M&E Officer', 'department' => 'M&E', 'is_manager' => false, 'level' => 3, 'reports_to' => 'M&E Supervisor'],
            ['title' => 'M&E Assistant', 'department' => 'M&E', 'is_manager' => false, 'level' => 4, 'reports_to' => 'M&E Supervisor'],

            // Laboratory Department
            ['title' => 'Department Head (Manager)', 'department' => 'Laboratory', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Sr. Research Asst./Department Head Asst./Sr. Entomologist', 'department' => 'Laboratory', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Laboratory Department Head (Manager)'],
            ['title' => 'Research Asst./Department Head Asst./Entomologist', 'department' => 'Laboratory', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Laboratory Department Head (Manager)'],
            ['title' => 'Laboratory In Charge', 'department' => 'Laboratory', 'is_manager' => true, 'level' => 2, 'reports_to' => 'Laboratory Department Head (Manager)'],
            ['title' => 'Sr. Laboratory Technical', 'department' => 'Laboratory', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Laboratory Department Head (Manager)'],
            ['title' => 'Laboratory Technical/Medical Technologist/Microscopist (B Sc.)', 'department' => 'Laboratory', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Laboratory Department Head (Manager)'],
            ['title' => 'Laboratory Technical/Microscopist', 'department' => 'Laboratory', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Laboratory Department Head (Manager)'],
            ['title' => 'Laboratory Administrator', 'department' => 'Laboratory', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Laboratory Department Head (Manager)'],
            ['title' => 'Laboratory Assistant', 'department' => 'Laboratory', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Laboratory Department Head (Manager)'],

            // Malaria Department
            ['title' => 'Department Head/Program Lead', 'department' => 'Malaria', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Department Deputy/Country Representative', 'department' => 'Malaria', 'is_manager' => true, 'level' => 2, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Program Director Assistant', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Liaison officer', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Project Support Officer / Program assistance', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Epidemiologist', 'department' => 'Malaria', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Operation Manager', 'department' => 'Malaria', 'is_manager' => true, 'level' => 2, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Operation Manager Assistant', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Operation Support Officer', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Training Coordinator', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Senior field Officer', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Field Officer', 'department' => 'Malaria', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Logistic and supplier supervisor', 'department' => 'Malaria', 'is_manager' => true, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Logistic and supplies officer', 'department' => 'Malaria', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Surveillance Officer', 'department' => 'Malaria', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Malaria Department Head/Program Lead'],
            ['title' => 'Mosquito catcher', 'department' => 'Malaria', 'is_manager' => false, 'level' => 4, 'reports_to' => 'Malaria Department Head/Program Lead'],

            // Public Engagement Department
            ['title' => 'PE Manager/Head of Department', 'department' => 'Public Engagement', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'PE Specialist', 'department' => 'Public Engagement', 'is_manager' => false, 'level' => 2, 'reports_to' => 'PE Manager/Head of Department'],
            ['title' => 'Social Researcher', 'department' => 'Public Engagement', 'is_manager' => false, 'level' => 2, 'reports_to' => 'PE Manager/Head of Department'],
            ['title' => 'Sr. Project Coordinator', 'department' => 'Public Engagement', 'is_manager' => false, 'level' => 2, 'reports_to' => 'PE Manager/Head of Department'],
            ['title' => 'CE trainer/CE Coordinator/ CE Liaison', 'department' => 'Public Engagement', 'is_manager' => false, 'level' => 3, 'reports_to' => 'PE Manager/Head of Department'],
            ['title' => 'Capacity Building Officer', 'department' => 'Public Engagement', 'is_manager' => false, 'level' => 3, 'reports_to' => 'PE Manager/Head of Department'],
            ['title' => 'CE Assistant/CE facilitator', 'department' => 'Public Engagement', 'is_manager' => false, 'level' => 4, 'reports_to' => 'PE Manager/Head of Department'],

            // TB Department
            ['title' => 'Program Technical Coordinator', 'department' => 'TB', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Researcher/Physician/TB Doctor', 'department' => 'TB', 'is_manager' => false, 'level' => 2, 'reports_to' => 'TB Program Technical Coordinator'],
            ['title' => 'Research Assistant', 'department' => 'TB', 'is_manager' => false, 'level' => 3, 'reports_to' => 'TB Program Technical Coordinator'],
            ['title' => 'Program Manager', 'department' => 'TB', 'is_manager' => true, 'level' => 2, 'reports_to' => 'TB Program Technical Coordinator'],
            ['title' => 'Assistant Program Coordinator/Assistant Coordinator/Program Assistant field manager/ M&E', 'department' => 'TB', 'is_manager' => false, 'level' => 3, 'reports_to' => 'TB Program Technical Coordinator'],
            ['title' => 'Counsellor Supervisor Senior', 'department' => 'TB', 'is_manager' => true, 'level' => 3, 'reports_to' => 'TB Program Technical Coordinator'],

            // Media Group Department
            ['title' => 'Media Group Supervisor', 'department' => 'Media Group', 'is_manager' => true, 'level' => 1, 'reports_to' => null],
            ['title' => 'Content creator', 'department' => 'Media Group', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Media Group Supervisor'],
            ['title' => 'Video Editor & graphic designer', 'department' => 'Media Group', 'is_manager' => false, 'level' => 2, 'reports_to' => 'Media Group Supervisor'],
            ['title' => 'Content creator Asst.', 'department' => 'Media Group', 'is_manager' => false, 'level' => 3, 'reports_to' => 'Media Group Supervisor'],
        ];

        // Insert positions in batches
        $positionData = [];
        foreach ($positions as $position) {
            $departmentId = $departments[$position['department']] ?? null;
            if ($departmentId) {
                $positionData[] = [
                    'title' => $position['title'],
                    'department_id' => $departmentId,
                    'reports_to_position_id' => null, // Will be set later
                    'level' => $position['level'],
                    'is_manager' => $position['is_manager'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert positions without reporting relationships first
        DB::table('positions')->insert($positionData);

        // Now set up reporting relationships
        $this->setupReportingRelationships($positions, $departments);
    }

    /**
     * Set up reporting relationships between positions
     */
    private function setupReportingRelationships($positionsData, $departments): void
    {
        // Get all created positions
        $positions = DB::table('positions')
            ->join('departments', 'positions.department_id', '=', 'departments.id')
            ->select('positions.*', 'departments.name as department_name')
            ->get()
            ->keyBy(function ($item) {
                return $item->department_name.'|'.$item->title;
            });

        // Set up reporting relationships
        foreach ($positionsData as $positionData) {
            if ($positionData['reports_to']) {
                $positionKey = $positionData['department'].'|'.$positionData['title'];
                $position = $positions->get($positionKey);

                if ($position) {
                    // Find the manager this position reports to
                    $managerKey = $this->findManagerKey($positionData['reports_to'], $positionData['department'], $positions);
                    $manager = $managerKey ? $positions->get($managerKey) : null;

                    if ($manager) {
                        DB::table('positions')
                            ->where('id', $position->id)
                            ->update(['reports_to_position_id' => $manager->id]);
                    }
                }
            }
        }
    }

    /**
     * Find the manager key based on the reports_to string
     */
    private function findManagerKey($reportsTo, $department, $positions): ?string
    {
        // Look for exact match first
        $exactKey = $department.'|'.$reportsTo;
        if ($positions->has($exactKey)) {
            return $exactKey;
        }

        // Look for partial matches in the same department
        foreach ($positions as $key => $position) {
            if ($position->department_name === $department &&
                $position->is_manager &&
                (str_contains($position->title, $reportsTo) || str_contains($reportsTo, $position->title))) {
                return $key;
            }
        }

        // Look for department manager
        foreach ($positions as $key => $position) {
            if ($position->department_name === $department &&
                $position->is_manager &&
                $position->level === 1) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
