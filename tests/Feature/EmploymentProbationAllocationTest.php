<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
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

    public function test_employment_store_creates_probation_record(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('employment_records.edit', 'web');
        $user->givePermissionTo('employment_records.edit');

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

        $employee = Employee::create([
            'organization' => 'SMRU',
            'staff_id' => 'EMP-UNIT-001',
            'first_name_en' => 'Unit',
            'last_name_en' => 'Tester',
            'gender' => 'Female',
            'date_of_birth' => '1990-01-01',
            'status' => 'Local ID Staff',
        ]);

        $response = $this->postJson('/api/v1/employments', [
            'employee_id' => $employee->id,
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
        ]);

        $response->assertCreated();

        $employmentId = data_get($response->json(), 'data.id');

        $this->assertNotNull($employmentId);

        $this->assertDatabaseHas('probation_records', [
            'employment_id' => $employmentId,
            'event_type' => ProbationRecord::EVENT_INITIAL,
            'is_active' => true,
        ]);
    }
}
