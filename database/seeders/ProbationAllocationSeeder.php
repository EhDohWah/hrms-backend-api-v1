<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\ProbationRecord;
use App\Models\Site;
use App\Services\EmployeeFundingAllocationService;
use App\Services\ProbationRecordService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProbationAllocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedOrgFundedInitialScenario();
            $this->seedOrgGrantSplitExtensionScenario();
            $this->seedMultiGrantPayrollScenario();
        });
    }

    private function seedOrgFundedInitialScenario(): void
    {
        $department = Department::create([
            'name' => 'Org Probation Dept',
            'description' => '100% org-funded probation team',
        ]);

        $position = Position::create([
            'title' => 'Org Funded Analyst',
            'department_id' => $department->id,
            'level' => 2,
            'is_manager' => false,
        ]);

        // Use existing Site instead of creating WorkLocation
        $site = Site::where('code', 'MRM')->first();
        if (! $site) {
            $site = Site::create([
                'name' => 'Org Probation HQ',
                'code' => 'ORG-HQ',
                'description' => 'Organization Probation Headquarters',
                'is_active' => true,
            ]);
        }

        $orgGrant = Grant::create([
            'name' => 'Organization Core Fund',
            'code' => 'ORG-CORE-100',
            'organization' => 'SMRU',
            'description' => 'Internal funding for org-only hires',
        ]);

        $employee = Employee::create([
            'staff_id' => 'EMP-ORG-100',
            'organization' => 'SMRU',
            'first_name_en' => 'Olivia',
            'last_name_en' => 'Orgseed',
            'gender' => 'Female',
            'date_of_birth' => '1992-01-20',
            'status' => 'Local ID',
        ]);

        $employment = Employment::create([
            'employee_id' => $employee->id,
            'pay_method' => 'Transferred to bank',
            'start_date' => '2025-01-01',
            'pass_probation_date' => '2025-04-01',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'site_id' => $site->id,
            'pass_probation_salary' => 36000,
            'probation_salary' => 24000,
            'health_welfare' => true,
            'pvd' => false,
            'saving_fund' => false,
            'status' => true,
        ]);

        app(ProbationRecordService::class)->createInitialRecord($employment);

        // Create allocation using grant_id directly (org_funded type)
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_id' => $orgGrant->id,
            'allocation_type' => 'org_funded',
            'fte' => 1.0,
            'status' => 'active',
            'start_date' => $employment->start_date,
        ]);

        $this->allocationService()
            ->applySalaryContext($allocation, Carbon::parse($employment->start_date))
            ->save();
    }

    private function seedOrgGrantSplitExtensionScenario(): void
    {
        $department = Department::create([
            'name' => 'Hybrid Funding Dept',
            'description' => '70/30 org-grant blended team',
        ]);

        $position = Position::create([
            'title' => 'Hybrid Support Officer',
            'department_id' => $department->id,
            'level' => 3,
            'is_manager' => false,
        ]);

        // Use existing Site instead of creating WorkLocation
        $site = Site::where('code', 'MRM')->first();
        if (! $site) {
            $site = Site::create([
                'name' => 'Hybrid Campus',
                'code' => 'HYB-CAMP',
                'description' => 'Hybrid Campus Location',
                'is_active' => true,
            ]);
        }

        $grant = Grant::create([
            'name' => 'Hybrid Education Grant',
            'code' => 'GRANT-7030',
            'organization' => 'SMRU',
            'description' => 'Covers 30% of the role',
        ]);

        $grantItem = GrantItem::create([
            'grant_id' => $grant->id,
            'grant_position' => 'Hybrid Liaison',
            'grant_salary' => 15000,
            'grant_benefit' => 1500,
            'grant_level_of_effort' => 30,
            'grant_position_number' => 1,
            'budgetline_code' => 'HYB-7030-01',
        ]);

        // Create org funding grant for the 70% portion
        $orgGrant = Grant::create([
            'name' => 'Hybrid Org Fund',
            'code' => 'ORG-HYB-70',
            'organization' => 'SMRU',
            'description' => 'Org funding for 70% of hybrid position',
        ]);

        $employee = Employee::create([
            'staff_id' => 'EMP-HYB-7030',
            'organization' => 'SMRU',
            'first_name_en' => 'Harper',
            'last_name_en' => 'Hybrid',
            'gender' => 'Female',
            'date_of_birth' => '1991-07-12',
            'status' => 'Local ID',
        ]);

        $employment = Employment::create([
            'employee_id' => $employee->id,
            'pay_method' => 'Transferred to bank',
            'start_date' => '2025-02-01',
            'pass_probation_date' => '2025-05-01',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'site_id' => $site->id,
            'pass_probation_salary' => 40000,
            'probation_salary' => 26000,
            'health_welfare' => true,
            'pvd' => true,
            'saving_fund' => false,
            'status' => true,
        ]);

        $initialRecord = app(ProbationRecordService::class)->createInitialRecord($employment);

        $initialRecord->update(['is_active' => false]);
        $extendedEndDate = Carbon::parse('2025-06-15');

        $extensionRecord = ProbationRecord::create([
            'employment_id' => $employment->id,
            'employee_id' => $employment->employee_id,
            'event_type' => ProbationRecord::EVENT_EXTENSION,
            'event_date' => '2025-05-25',
            'decision_date' => '2025-05-25',
            'probation_start_date' => $initialRecord->probation_start_date->toDateString(),
            'probation_end_date' => $extendedEndDate->toDateString(),
            'previous_end_date' => $initialRecord->probation_end_date->toDateString(),
            'extension_number' => 1,
            'decision_reason' => 'Needs additional coaching',
            'evaluation_notes' => 'Improving but requires closer observation',
            'approved_by' => 'HR Seeder',
            'is_active' => true,
            'created_by' => 'seeder',
            'updated_by' => 'seeder',
        ]);

        Employment::withoutEvents(function () use ($employment, $extendedEndDate) {
            $employment->update(['pass_probation_date' => $extendedEndDate->toDateString()]);
        });

        $extensionRecord->update(['is_active' => false]);

        ProbationRecord::create([
            'employment_id' => $employment->id,
            'employee_id' => $employment->employee_id,
            'event_type' => ProbationRecord::EVENT_FAILED,
            'event_date' => '2025-06-20',
            'decision_date' => '2025-06-20',
            'probation_start_date' => $extensionRecord->probation_start_date->toDateString(),
            'probation_end_date' => $extensionRecord->probation_end_date->toDateString(),
            'previous_end_date' => $extensionRecord->probation_end_date->toDateString(),
            'extension_number' => $extensionRecord->extension_number,
            'decision_reason' => 'Failed to meet KPIs',
            'evaluation_notes' => 'Transitioned to exit plan',
            'approved_by' => 'HR Seeder',
            'is_active' => true,
            'created_by' => 'seeder',
            'updated_by' => 'seeder',
        ]);

        $startDate = Carbon::parse($employment->start_date);

        // Create org_funded allocation using grant_id directly (70%)
        $orgAllocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_id' => $orgGrant->id,
            'allocation_type' => 'org_funded',
            'fte' => 0.70,
            'status' => 'active',
            'start_date' => $startDate->toDateString(),
        ]);

        // Create grant allocation using grant_item_id directly (30%)
        $grantAllocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_item_id' => $grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 0.30,
            'status' => 'active',
            'start_date' => $startDate->toDateString(),
        ]);

        $allocationService = $this->allocationService();
        $allocationService->applySalaryContext($orgAllocation, $startDate)->save();
        $allocationService->applySalaryContext($grantAllocation, $startDate)->save();
    }

    private function seedMultiGrantPayrollScenario(): void
    {
        $department = Department::create([
            'name' => 'Advanced Research Dept',
            'description' => 'Tri-source funding for R&D',
        ]);

        $position = Position::create([
            'title' => 'Research Fellow',
            'department_id' => $department->id,
            'level' => 4,
            'is_manager' => false,
        ]);

        // Use existing Site instead of creating WorkLocation
        $site = Site::where('code', 'MRM')->first();
        if (! $site) {
            $site = Site::create([
                'name' => 'R&D Lab',
                'code' => 'RD-LAB',
                'description' => 'Research and Development Laboratory',
                'is_active' => true,
            ]);
        }

        $orgGrant = Grant::create([
            'name' => 'Strategic Org Fund',
            'code' => 'ORG-3030',
            'organization' => 'SMRU',
            'description' => 'Org contribution for research staff',
        ]);

        $grantA = Grant::create([
            'name' => 'Global Health Grant A',
            'code' => 'GRA-30A',
            'organization' => 'SMRU',
            'description' => 'Supports malaria research',
        ]);

        $grantB = Grant::create([
            'name' => 'Global Health Grant B',
            'code' => 'GRA-30B',
            'organization' => 'SMRU',
            'description' => 'Supports TB research',
        ]);

        $grantItemA = GrantItem::create([
            'grant_id' => $grantA->id,
            'grant_position' => 'Research Fellow A',
            'grant_salary' => 18000,
            'grant_benefit' => 1800,
            'grant_level_of_effort' => 30,
            'grant_position_number' => 1,
            'budgetline_code' => 'A-3030-01',
        ]);

        $grantItemB = GrantItem::create([
            'grant_id' => $grantB->id,
            'grant_position' => 'Research Fellow B',
            'grant_salary' => 18000,
            'grant_benefit' => 1800,
            'grant_level_of_effort' => 30,
            'grant_position_number' => 1,
            'budgetline_code' => 'B-3030-01',
        ]);

        $employee = Employee::create([
            'staff_id' => 'EMP-RES-303030',
            'organization' => 'SMRU',
            'first_name_en' => 'Riley',
            'last_name_en' => 'Researcher',
            'gender' => 'Male',
            'date_of_birth' => '1989-03-03',
            'status' => 'Expats',
        ]);

        $employment = Employment::create([
            'employee_id' => $employee->id,
            'pay_method' => 'Transferred to bank',
            'start_date' => '2025-03-15',
            'pass_probation_date' => '2025-06-15',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'site_id' => $site->id,
            'pass_probation_salary' => 52000,
            'probation_salary' => 36000,
            'health_welfare' => true,
            'pvd' => true,
            'saving_fund' => true,
            'status' => true,
        ]);

        $initialRecord = app(ProbationRecordService::class)->createInitialRecord($employment);
        $initialRecord->update(['is_active' => false]);

        ProbationRecord::create([
            'employment_id' => $employment->id,
            'employee_id' => $employment->employee_id,
            'event_type' => ProbationRecord::EVENT_PASSED,
            'event_date' => '2025-06-15',
            'decision_date' => '2025-06-15',
            'probation_start_date' => $initialRecord->probation_start_date->toDateString(),
            'probation_end_date' => $initialRecord->probation_end_date->toDateString(),
            'previous_end_date' => $initialRecord->probation_end_date->toDateString(),
            'extension_number' => 0,
            'evaluation_notes' => 'Passed probation with distinction',
            'approved_by' => 'HR Seeder',
            'is_active' => true,
            'created_by' => 'seeder',
            'updated_by' => 'seeder',
        ]);

        $postProbationStart = Carbon::parse($employment->pass_probation_date);

        // Create org_funded allocation using grant_id directly (30%)
        $orgAllocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_id' => $orgGrant->id,
            'allocation_type' => 'org_funded',
            'fte' => 0.30,
            'status' => 'active',
            'start_date' => $postProbationStart->toDateString(),
        ]);

        // Create grant allocation A using grant_item_id directly (30%)
        $grantAllocationA = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_item_id' => $grantItemA->id,
            'allocation_type' => 'grant',
            'fte' => 0.30,
            'status' => 'active',
            'start_date' => $postProbationStart->toDateString(),
        ]);

        // Create grant allocation B using grant_item_id directly (30%)
        $grantAllocationB = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_item_id' => $grantItemB->id,
            'allocation_type' => 'grant',
            'fte' => 0.30,
            'status' => 'active',
            'start_date' => $postProbationStart->toDateString(),
        ]);

        $allocationService = $this->allocationService();
        $allocationService->applySalaryContext($orgAllocation, $postProbationStart)->save();
        $allocationService->applySalaryContext($grantAllocationA, $postProbationStart)->save();
        $allocationService->applySalaryContext($grantAllocationB, $postProbationStart)->save();

        // NOTE: Payroll record creation commented out
        // Payroll records should be created through the actual payroll creation process
        /*
        $payPeriod = Carbon::parse('2025-07-31');

        foreach ([$orgAllocation, $grantAllocationA, $grantAllocationB] as $allocation) {
            Payroll::create([
                'employment_id' => $employment->id,
                'employee_funding_allocation_id' => $allocation->id,
                'pay_period_date' => $payPeriod->toDateString(),
                'gross_salary' => $employment->pass_probation_salary,
                'gross_salary_by_FTE' => $allocation->allocated_amount,
                'compensation_refund' => 0,
                'thirteen_month_salary' => 0,
                'thirteen_month_salary_accured' => 0,
                'pvd' => 0,
                'saving_fund' => 0,
                'employer_social_security' => 0,
                'employee_social_security' => 0,
                'employer_health_welfare' => 0,
                'employee_health_welfare' => 0,
                'tax' => 0,
                'net_salary' => $allocation->allocated_amount,
                'total_salary' => $allocation->allocated_amount,
                'total_pvd' => 0,
                'total_saving_fund' => 0,
                'salary_bonus' => 0,
                'total_income' => $allocation->allocated_amount,
                'employer_contribution' => 0,
                'total_deduction' => 0,
                'notes' => 'Seeded payroll for allocation '.$allocation->id,
            ]);
        }
        */
    }

    private function allocationService(): EmployeeFundingAllocationService
    {
        return app(EmployeeFundingAllocationService::class);
    }
}
