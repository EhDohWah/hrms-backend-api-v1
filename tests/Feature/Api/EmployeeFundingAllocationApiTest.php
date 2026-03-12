<?php

namespace Tests\Feature\Api;

use App\Enums\FundingAllocationStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
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

class EmployeeFundingAllocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Employee $employee;

    protected Employment $employment;

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

        // Create permissions matching route middleware
        $permissions = [
            'employee_funding_allocations.read',
            'employee_funding_allocations.create',
            'employee_funding_allocations.update',
            'employee_funding_allocations.delete',
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
            'staff_id' => 'EMP001',
            'first_name_en' => 'John',
            'last_name_en' => 'Doe',
            'gender' => 'M',
            'date_of_birth' => '1990-01-01',
            'status' => 'Local ID Staff',
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

        $this->employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'site_id' => $this->site->id,
            'start_date' => '2025-01-01',
            'pass_probation_date' => '2025-04-01',
            'pass_probation_salary' => 35000,
            'probation_salary' => 25000,
        ]);
    }

    /** @test */
    public function it_can_list_employee_funding_allocations()
    {
        EmployeeFundingAllocation::factory()->count(5)->create([
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->getJson('/api/v1/employee-funding-allocations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'employee_id',
                        'employment_id',
                        'fte',
                        'allocated_amount',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_filter_allocations_by_employee_id()
    {
        $otherEmployee = Employee::factory()->create();

        EmployeeFundingAllocation::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        EmployeeFundingAllocation::factory()->create([
            'employee_id' => $otherEmployee->id,
        ]);

        $response = $this->getJson("/api/v1/employee-funding-allocations?employee_id={$this->employee->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    /** @test */
    public function it_can_create_allocation()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'allocations' => [
                [
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'allocations',
                    'total_created',
                ],
            ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $this->grantItem->id,
        ]);
    }

    /** @test */
    public function it_can_create_multiple_allocations()
    {
        // Create a second grant item for split allocations
        $grantItem2 = GrantItem::create([
            'grant_id' => $this->grant->id,
            'grant_position' => 'Analyst',
            'grant_salary' => 40000,
            'grant_benefit' => 4000,
            'grant_level_of_effort' => 100,
            'grant_position_number' => 2,
            'budgetline_code' => 'BL002',
        ]);

        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'allocations' => [
                [
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 60,
                ],
                [
                    'grant_item_id' => $grantItem2->id,
                    'fte' => 40,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(201);
        $this->assertEquals(2, $response->json('data.total_created'));

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $this->grantItem->id,
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $grantItem2->id,
        ]);
    }

    /** @test */
    public function it_validates_grant_capacity()
    {
        // Set grant position number to 1
        $grantItem = GrantItem::create([
            'grant_id' => $this->grant->id,
            'grant_position' => 'Limited Position',
            'grant_salary' => 50000,
            'grant_benefit' => 5000,
            'grant_level_of_effort' => 100,
            'grant_position_number' => 1, // Only 1 slot
            'budgetline_code' => 'BL002',
        ]);

        // Create first allocation (fills the slot)
        $otherEmployee = Employee::factory()->create();
        $otherEmployment = Employment::factory()->create([
            'employee_id' => $otherEmployee->id,
        ]);

        EmployeeFundingAllocation::create([
            'employee_id' => $otherEmployee->id,
            'employment_id' => $otherEmployment->id,
            'grant_item_id' => $grantItem->id,
            'fte' => 1.00,
            'status' => FundingAllocationStatus::Active,
        ]);

        // Try to create second allocation (should fail)
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'allocations' => [
                [
                    'grant_item_id' => $grantItem->id,
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_can_show_allocation_details()
    {
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 1.00,
            'allocated_amount' => 35000,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        $response = $this->getJson("/api/v1/employee-funding-allocations/{$allocation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'employee_id',
                    'employment_id',
                    'fte',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $allocation->id,
                ],
            ]);
    }

    /** @test */
    public function it_can_update_allocation()
    {
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 1.00,
            'allocated_amount' => 35000,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        $updateData = [
            'grant_item_id' => $this->grantItem->id,
            'fte' => 100,
            'allocated_amount' => 35000,
        ];

        $response = $this->putJson("/api/v1/employee-funding-allocations/{$allocation->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'id' => $allocation->id,
            'allocated_amount' => 35000,
        ]);
    }

    /** @test */
    public function it_can_delete_allocation()
    {
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 1.00,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        $response = $this->deleteJson("/api/v1/employee-funding-allocations/{$allocation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employee funding allocation deactivated successfully',
            ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'id' => $allocation->id,
            'status' => FundingAllocationStatus::Inactive->value,
        ]);
    }

    /** @test */
    public function it_can_get_allocations_by_employee()
    {
        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 0.60,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        // Create a second grant item for the second allocation
        $grantItem2 = GrantItem::create([
            'grant_id' => $this->grant->id,
            'grant_position' => 'Analyst',
            'grant_salary' => 40000,
            'grant_benefit' => 4000,
            'grant_level_of_effort' => 100,
            'grant_position_number' => 2,
            'budgetline_code' => 'BL002',
        ]);

        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $grantItem2->id,
            'fte' => 0.40,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        $response = $this->getJson("/api/v1/employee-funding-allocations/employee/{$this->employee->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'employee' => [
                        'id',
                        'staff_id',
                        'first_name_en',
                        'last_name_en',
                    ],
                    'total_allocations',
                    'total_effort',
                    'allocations',
                ],
            ])
            ->assertJson([
                'data' => [
                    'total_allocations' => 2,
                    'total_effort' => 100.0, // 60% + 40% = 100%
                ],
            ]);
    }

    /** @test */
    public function it_can_get_allocations_by_grant_item()
    {
        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 1.00,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        $response = $this->getJson("/api/v1/employee-funding-allocations/by-grant-item/{$this->grantItem->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'allocations',
                    'total_allocations',
                    'active_allocations',
                ],
            ])
            ->assertJson([
                'data' => [
                    'total_allocations' => 1,
                    'active_allocations' => 1,
                ],
            ]);
    }

    /** @test */
    public function it_can_get_grant_structure()
    {
        $response = $this->getJson('/api/v1/employee-funding-allocations/grant-structure');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'grants' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                            'grant_items' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'grant_salary',
                                    'grant_benefit',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_bulk_deactivate_allocations()
    {
        $allocation1 = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 0.60,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        // Create a second grant item for the second allocation
        $grantItem2 = GrantItem::create([
            'grant_id' => $this->grant->id,
            'grant_position' => 'Analyst',
            'grant_salary' => 40000,
            'grant_benefit' => 4000,
            'grant_level_of_effort' => 100,
            'grant_position_number' => 2,
            'budgetline_code' => 'BL003',
        ]);

        $allocation2 = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $grantItem2->id,
            'fte' => 0.40,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/employee-funding-allocations/bulk-deactivate', [
            'allocation_ids' => [$allocation1->id, $allocation2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'deactivated_count' => 2,
                ],
            ]);

        // Check that status is set to Closed
        $allocation1->refresh();
        $allocation2->refresh();

        $this->assertEquals(FundingAllocationStatus::Closed, $allocation1->status);
        $this->assertEquals(FundingAllocationStatus::Closed, $allocation2->status);
    }

    /** @test */
    public function it_can_update_all_employee_allocations()
    {
        // Create existing allocation
        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 1.00,
            'salary_type' => 'pass_probation_salary',
            'status' => FundingAllocationStatus::Active,
        ]);

        // Create a second grant item for the split
        $grantItem2 = GrantItem::create([
            'grant_id' => $this->grant->id,
            'grant_position' => 'Analyst',
            'grant_salary' => 40000,
            'grant_benefit' => 4000,
            'grant_level_of_effort' => 100,
            'grant_position_number' => 2,
            'budgetline_code' => 'BL004',
        ]);

        // Update with new allocation split
        $updateData = [
            'employment_id' => $this->employment->id,
            'allocations' => [
                [
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 70,
                ],
                [
                    'grant_item_id' => $grantItem2->id,
                    'fte' => 30,
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/employee-funding-allocations/employee/{$this->employee->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_created' => 2,
                ],
            ]);

        // New allocations should exist with Active status
        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $this->grantItem->id,
            'status' => FundingAllocationStatus::Active,
            'fte' => 0.70,
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $grantItem2->id,
            'status' => FundingAllocationStatus::Active,
            'fte' => 0.30,
        ]);
    }

    /** @test */
    public function it_requires_grant_item_id_for_allocations()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'allocations' => [
                [
                    // Missing grant_item_id
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations.0.grant_item_id']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/employee-funding-allocations');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_checks_permissions_for_create()
    {
        // Create user WITHOUT edit permission (no permissions at all)
        $userWithoutPermission = User::factory()->create();
        Sanctum::actingAs($userWithoutPermission);

        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'allocations' => [
                [
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_404_for_non_existent_allocation()
    {
        $response = $this->getJson('/api/v1/employee-funding-allocations/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found',
            ]);
    }
}
