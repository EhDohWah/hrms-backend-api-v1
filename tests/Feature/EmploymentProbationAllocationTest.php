<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\Position;
use App\Models\ProbationRecord;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmploymentProbationAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employment_store_creates_probation_record_and_probation_aware_allocations(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('employment.create', 'web');
        $user->givePermissionTo('employment.create');

        Sanctum::actingAs($user);

        $department = Department::create([
            'name' => 'IT Test',
            'description' => 'Test department',
            'is_active' => true,
        ]);

        $position = Position::create([
            'title' => 'IT Helpdesk Tester',
            'department_id' => $department->id,
            'level' => 1,
            'is_manager' => false,
        ]);

        $site = Site::create([
            'name' => 'HQ',
            'organization' => 'SMRU',
        ]);

        $grant = Grant::create([
            'code' => 'UNIT-GRANT-001',
            'name' => 'Unit Test Grant',
            'organization' => 'SMRU',
        ]);

        $grantItem = GrantItem::create([
            'grant_id' => $grant->id,
            'grant_position' => 'Medic',
            'grant_salary' => 50000,
            'grant_benefit' => 5000,
            'grant_level_of_effort' => 40,
            'grant_position_number' => 1,
            'budgetline_code' => 'UNIT-40',
        ]);

        $employee = Employee::create([
            'organization' => 'SMRU',
            'staff_id' => 'EMP-UNIT-001',
            'first_name_en' => 'Unit',
            'last_name_en' => 'Tester',
            'gender' => 'Female',
            'date_of_birth' => '1990-01-01',
            'status' => 'Local ID',
        ]);

        $response = $this->postJson('/api/v1/employments', [
            'employee_id' => $employee->id,
            'employment_type' => 'Full-time',
            'pay_method' => 'Transferred to bank',
            'start_date' => '2025-01-01',
            'pass_probation_date' => '2025-04-01',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'site_id' => $site->id,
            'pass_probation_salary' => 32000,
            'probation_salary' => 20000,
            'health_welfare' => false,
            'pvd' => false,
            'saving_fund' => false,
            'allocations' => [
                [
                    'allocation_type' => 'org_funded',
                    'grant_id' => $grant->id,
                    'fte' => 60,
                ],
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $grantItem->id,
                    'fte' => 40,
                ],
            ],
        ]);

        $response->assertCreated();

        $employmentId = data_get($response->json(), 'data.employment.id');

        $this->assertNotNull($employmentId);

        $this->assertDatabaseHas('probation_records', [
            'employment_id' => $employmentId,
            'event_type' => ProbationRecord::EVENT_INITIAL,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employment_id' => $employmentId,
            'allocation_type' => 'org_funded',
            'salary_type' => 'probation_salary',
            'allocated_amount' => 20000 * 0.60,
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employment_id' => $employmentId,
            'allocation_type' => 'grant',
            'salary_type' => 'probation_salary',
            'allocated_amount' => 20000 * 0.40,
        ]);
    }
}
