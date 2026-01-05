<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\Position;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmploymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Employee $employee;

    protected Department $department;

    protected Position $position;

    protected Site $site;

    protected Grant $grant;

    protected GrantItem $grantItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate user
        $this->user = User::factory()->create();

        // Create permissions
        $permissions = [
            'employment.read',
            'employment.create',
            'employment.update',
            'employment.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->user->givePermissionTo($permissions);
        Sanctum::actingAs($this->user);

        // Create test data
        $this->department = Department::create([
            'name' => 'IT Department',
            'description' => 'Information Technology',
            'is_active' => true,
        ]);

        $this->position = Position::create([
            'title' => 'Software Developer',
            'department_id' => $this->department->id,
            'level' => 2,
            'is_manager' => false,
        ]);

        $this->site = Site::create([
            'name' => 'Headquarters',
            'organization' => 'SMRU',
        ]);

        $this->employee = Employee::create([
            'organization' => 'SMRU',
            'staff_id' => 'EMP001',
            'first_name_en' => 'John',
            'last_name_en' => 'Doe',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'status' => 'Active',
        ]);

        $this->grant = Grant::create([
            'code' => 'G001',
            'name' => 'Test Grant',
            'organization' => 'SMRU',
        ]);

        $this->grantItem = GrantItem::create([
            'grant_id' => $this->grant->id,
            'grant_position' => 'Developer',
            'grant_salary' => 50000,
            'grant_benefit' => 5000,
            'grant_level_of_effort' => 100,
            'grant_position_number' => 2,
            'budgetline_code' => 'BL001',
        ]);
    }

    /** @test */
    public function it_can_list_employments()
    {
        Employment::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/employments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'employee_id',
                        'employment_type',
                        'start_date',
                        'department_id',
                        'position_id',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_create_employment_with_grant_allocation()
    {
        $employmentData = [
            'employee_id' => $this->employee->id,
            'employment_type' => 'Full-time',
            'pay_method' => 'Monthly',
            'start_date' => '2025-01-01',
            'pass_probation_date' => '2025-04-01',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'site_id' => $this->site->id,
            'pass_probation_salary' => 35000,
            'probation_salary' => 25000,
            'health_welfare' => true,
            'pvd' => true,
            'saving_fund' => false,
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employments', $employmentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'employee_id',
                    'employment_type',
                    'allocations' => [
                        '*' => [
                            'id',
                            'grant_item_id',
                            'allocation_type',
                            'fte',
                            'allocated_amount',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('employments', [
            'employee_id' => $this->employee->id,
            'pass_probation_salary' => 35000,
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'grant_item_id' => $this->grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 1.00, // Stored as decimal
        ]);
    }

    /** @test */
    public function it_can_create_employment_with_org_funded_allocation()
    {
        $employmentData = [
            'employee_id' => $this->employee->id,
            'employment_type' => 'Full-time',
            'pay_method' => 'Monthly',
            'start_date' => '2025-01-01',
            'pass_probation_date' => '2025-04-01',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'site_id' => $this->site->id,
            'pass_probation_salary' => 35000,
            'probation_salary' => 25000,
            'health_welfare' => true,
            'pvd' => false,
            'saving_fund' => false,
            'allocations' => [
                [
                    'allocation_type' => 'org_funded',
                    'grant_id' => $this->grant->id,
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employments', $employmentData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'grant_id' => $this->grant->id,
            'grant_item_id' => null,
            'allocation_type' => 'org_funded',
            'fte' => 1.00,
        ]);
    }

    /** @test */
    public function it_can_create_employment_with_mixed_allocations()
    {
        $employmentData = [
            'employee_id' => $this->employee->id,
            'employment_type' => 'Full-time',
            'pay_method' => 'Monthly',
            'start_date' => '2025-01-01',
            'pass_probation_date' => '2025-04-01',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'site_id' => $this->site->id,
            'pass_probation_salary' => 40000,
            'probation_salary' => 28000,
            'health_welfare' => true,
            'pvd' => true,
            'saving_fund' => true,
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 60,
                ],
                [
                    'allocation_type' => 'org_funded',
                    'grant_id' => $this->grant->id,
                    'fte' => 40,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employments', $employmentData);

        $response->assertStatus(201);

        $employment = Employment::latest()->first();
        $this->assertCount(2, $employment->employeeFundingAllocations);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employment_id' => $employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 0.60,
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employment_id' => $employment->id,
            'grant_id' => $this->grant->id,
            'fte' => 0.40,
        ]);
    }

    /** @test */
    public function it_validates_total_fte_equals_100_percent()
    {
        $employmentData = [
            'employee_id' => $this->employee->id,
            'employment_type' => 'Full-time',
            'pay_method' => 'Monthly',
            'start_date' => '2025-01-01',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'pass_probation_salary' => 35000,
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 80, // Only 80%, not 100%
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employments', $employmentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations']);
    }

    /** @test */
    public function it_can_show_employment_details()
    {
        $employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->getJson("/api/v1/employments/{$employment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'employee_id',
                    'employment_type',
                    'start_date',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $employment->id,
                ],
            ]);
    }

    /** @test */
    public function it_can_update_employment()
    {
        $employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
            'pass_probation_salary' => 30000,
        ]);

        $updateData = [
            'employment_type' => 'Part-time',
            'pass_probation_salary' => 35000,
        ];

        $response = $this->putJson("/api/v1/employments/{$employment->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employments', [
            'id' => $employment->id,
            'employment_type' => 'Part-time',
            'pass_probation_salary' => 35000,
        ]);
    }

    /** @test */
    public function it_can_delete_employment()
    {
        $employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->deleteJson("/api/v1/employments/{$employment->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('employments', [
            'id' => $employment->id,
        ]);
    }

    /** @test */
    public function it_can_search_employment_by_staff_id()
    {
        Employment::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->getJson("/api/v1/employments/search/staff-id/{$this->employee->staff_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'employee_id',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_get_funding_allocations_for_employment()
    {
        $employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $employment->employeeFundingAllocations()->create([
            'employee_id' => $this->employee->id,
            'grant_item_id' => $this->grantItem->id,
            'grant_id' => null,
            'fte' => 1.00,
            'allocation_type' => 'grant',
            'allocated_amount' => 35000,
            'salary_type' => 'pass_probation_salary',
            'status' => 'active',
            'start_date' => '2025-01-01',
        ]);

        $response = $this->getJson("/api/v1/employments/{$employment->id}/funding-allocations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'grant_item_id',
                        'allocation_type',
                        'fte',
                        'allocated_amount',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_get_probation_history_for_employment()
    {
        $employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->getJson("/api/v1/employments/{$employment->id}/probation-history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/v1/employments');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_checks_permissions_for_create()
    {
        $userWithoutPermission = User::factory()->create();
        Sanctum::actingAs($userWithoutPermission);

        $employmentData = [
            'employee_id' => $this->employee->id,
            'employment_type' => 'Full-time',
            'start_date' => '2025-01-01',
            'pass_probation_salary' => 35000,
        ];

        $response = $this->postJson('/api/v1/employments', $employmentData);

        $response->assertStatus(403);
    }
}
