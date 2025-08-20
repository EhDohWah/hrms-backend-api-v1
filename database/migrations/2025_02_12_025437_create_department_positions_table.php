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
        Schema::create('department_positions', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->string('position');
            $table->string('report_to')->nullable()->comment('Name or identifier of the manager position');
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        DB::table('department_positions')->insert([
            // Administration
            ['department' => 'Administration', 'position' => 'Administrator', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Administration', 'position' => 'Administrative Officer', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Administration', 'position' => 'Sr. Administrative Assistant', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Administration', 'position' => 'Administrative Assistant', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Administration', 'position' => 'Referral Supervisor', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Administration', 'position' => 'Referral staff', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Administration', 'position' => 'Cleaner', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Administration', 'position' => 'Cook', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],

            // Finance
            ['department' => 'Finance', 'position' => 'Accountant Manager/Finance Manager', 'report_to' => 'Finance Manager', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Finance', 'position' => 'Sr. Accountant', 'report_to' => '9', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Finance', 'position' => 'Accountant Assistant/Accountant/Finance Assistant', 'report_to' => '9', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Finance', 'position' => 'Book keeper', 'report_to' => '9', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Finance', 'position' => 'Book keeping Assistant.', 'report_to' => '9', 'created_at' => now(), 'updated_at' => now()],

            // Grant
            ['department' => 'Grant', 'position' => 'Grant Manager', 'report_to' => '14', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Grant', 'position' => 'Grant Senior Officer', 'report_to' => '14', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Grant', 'position' => 'Assistant of Grant Officer', 'report_to' => '14', 'created_at' => now(), 'updated_at' => now()],

            // Human Resources
            ['department' => 'Human Resources', 'position' => 'HR Manager', 'report_to' => '17', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Human Resources', 'position' => 'Sr.HR Assistant/Sr. Capacity Building Officer', 'report_to' => '17', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Human Resources', 'position' => 'HR Assistant/Capacity Building Officer', 'report_to' => '17', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Human Resources', 'position' => 'HR Junior Assistant.', 'report_to' => '17', 'created_at' => now(), 'updated_at' => now()],

            // Logistics
            ['department' => 'Logistics', 'position' => 'Logistic Manager', 'report_to' => '21', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Logistics', 'position' => 'Senior Logistic Assistant', 'report_to' => '21', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Logistics', 'position' => 'Logistic Assistant', 'report_to' => '21', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Logistics', 'position' => 'Transportation in charge', 'report_to' => '21', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Logistics', 'position' => 'Driver', 'report_to' => '21', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Logistics', 'position' => 'Security guard', 'report_to' => '21', 'created_at' => now(), 'updated_at' => now()],

            // Procurement & Store
            ['department' => 'Procurement & Store', 'position' => 'Procurement & Store Manager', 'report_to' => '27', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Procurement Officer', 'report_to' => '27', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Sr. Purchaser', 'report_to' => '27', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Purchaser', 'report_to' => '27', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Asst. Purchaser', 'report_to' => '27', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Inventory & Procurement Officer', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Pharmacist', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Store keeper/Pharmacy stock keeper', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Procurement & Store', 'position' => 'Store Keeper Asst.', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],

            // Data management
            ['department' => 'Data management', 'position' => 'Sr. Data Manager', 'report_to' => '36', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Data management', 'position' => 'Data Manager', 'report_to' => '36', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Data management', 'position' => 'Data Officer', 'report_to' => '36', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Data management', 'position' => 'Data Manager Assistant', 'report_to' => '36', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Data management', 'position' => 'Data entry/Data clerk', 'report_to' => '36', 'created_at' => now(), 'updated_at' => now()],

            // IT
            ['department' => 'IT', 'position' => 'IT Specialist', 'report_to' => '41', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'IT Manager', 'report_to' => '41', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'Sr. IT System Administrator', 'report_to' => '41', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'IT System Administrator', 'report_to' => '41', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'Senior IT Officer', 'report_to' => '41', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'Senior IT helpdesk', 'report_to' => '41', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'IT helpdesk', 'report_to' => '41', 'created_at' => now(), 'updated_at' => now()],

            // Clinical
            ['department' => 'Clinical', 'position' => 'Physician/Medical doctor', 'report_to' => 'Director', 'created_at' => now(), 'updated_at' => now()],

            // Research/Study
            ['department' => 'Research/Study', 'position' => 'Senior Clinician Scientist', 'report_to' => 'Senior Clinician Scientist', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Research/Study', 'position' => 'Researcher', 'report_to' => '49', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Research/Study', 'position' => 'Clinical Research Officer (Senior)', 'report_to' => '49', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Research/Study', 'position' => 'Clinical Research Assistant/Research Assistant', 'report_to' => '49', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Research/Study', 'position' => 'Research Staff', 'report_to' => '49', 'created_at' => now(), 'updated_at' => now()],

            // Training
            ['department' => 'Training', 'position' => 'Trainer', 'report_to' => '54', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Training', 'position' => 'Sr. Training Coordinator', 'report_to' => '54', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Training', 'position' => 'Training Coordinator', 'report_to' => '54', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Training', 'position' => 'Trainer Asst.', 'report_to' => '54', 'created_at' => now(), 'updated_at' => now()],

            // Research/Study M&E
            ['department' => 'Research/Study M&E', 'position' => 'M&E Supervisor', 'report_to' => '58', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Research/Study M&E', 'position' => 'Sr. M&E Officer', 'report_to' => '58', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Research/Study M&E', 'position' => 'Monitor/M&E Staff', 'report_to' => '58', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Research/Study M&E', 'position' => 'M&E Assistant', 'report_to' => '58', 'created_at' => now(), 'updated_at' => now()],

            // MCH
            ['department' => 'MCH', 'position' => 'Physician/OB Doctor', 'report_to' => 'Deputy Director', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'MCH', 'position' => 'Public Health Manager/MCH Manager', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'MCH', 'position' => 'Public Health Coordinator', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'MCH', 'position' => 'Vaccine Coordinator', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'MCH', 'position' => 'Vaccine Assistant', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'MCH', 'position' => 'Sonographer in charge', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'MCH', 'position' => 'Counsellor Supervisor Senior', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],

            // M&E
            ['department' => 'M&E', 'position' => 'M&E Supervisor', 'report_to' => '69', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'M&E', 'position' => 'M&E Coordinator', 'report_to' => '69', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'M&E', 'position' => 'M&E Officer', 'report_to' => '69', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'M&E', 'position' => 'M&E Assistant', 'report_to' => '69', 'created_at' => now(), 'updated_at' => now()],

            // Laboratory
            ['department' => 'Laboratory', 'position' => 'Department Head (Manager)', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Sr. Research Asst./Department Head Asst./Sr. Entomologist', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Research Asst./Department Head Asst./Entomologist', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Laboratory In Charge', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Sr. Laboratory Technical', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Laboratory Technical/Medical Technologist/Microscopist (B Sc.)', 'report_to' => '74', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Laboratory Technical/Microscopist', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Laboratory Administrator', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Laboratory', 'position' => 'Laboratory Assistant', 'report_to' => '73', 'created_at' => now(), 'updated_at' => now()],

            // Malaria
            ['department' => 'Malaria', 'position' => 'Department Head/Program Lead', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Department Deputy/Country Representative', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Program Director Assistant', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Liaison officer', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Project Support Officer / Program assistance', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Epidemiologist', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Operation Manager', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Operation Manager Assistant', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Operation Support Officer', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Training Coordinator', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Senior field Officer', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Field Officer', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Logistic and supplier supervisor', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Logistic and supplies officer', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Surveillance Officer', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Malaria', 'position' => 'Mosquito catcher', 'report_to' => '80', 'created_at' => now(), 'updated_at' => now()],

            // Public Engagement
            ['department' => 'Public Engagement', 'position' => 'PE Manager/Head of Department', 'report_to' => '98', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Public Engagement', 'position' => 'PE Specialist', 'report_to' => '98', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Public Engagement', 'position' => 'Social Researcher', 'report_to' => '98', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Public Engagement', 'position' => 'Sr. Project Coordinator', 'report_to' => '98', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Public Engagement', 'position' => 'CE trainer/CE Coordinator/ CE Liaison', 'report_to' => '98', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Public Engagement', 'position' => 'Capacity Building Officer', 'report_to' => '98', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Public Engagement', 'position' => 'CE Assistant/CE facilitator', 'report_to' => '98', 'created_at' => now(), 'updated_at' => now()],

            // TB
            ['department' => 'TB', 'position' => 'Program Technical Coordinator', 'report_to' => '105', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'TB', 'position' => 'Researcher/Physician/TB Doctor', 'report_to' => '105', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'TB', 'position' => 'Research Assistant', 'report_to' => '105', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'TB', 'position' => 'Program Manager', 'report_to' => '105', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'TB', 'position' => 'Assistant Program Coordinator/Assistant Coordinator/Program Assistant field manager/ M&E', 'report_to' => '105', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'TB', 'position' => 'Counsellor Supervisor Senior', 'report_to' => '105', 'created_at' => now(), 'updated_at' => now()],

            // Media Group
            ['department' => 'Media Group', 'position' => 'Media Group Supervisor', 'report_to' => '111', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Media Group', 'position' => 'Content creator', 'report_to' => '111', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Media Group', 'position' => 'Video Editor & graphic designer', 'report_to' => '111', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Media Group', 'position' => 'Content creator Asst.', 'report_to' => '111', 'created_at' => now(), 'updated_at' => now()],
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_positions');
    }
};
