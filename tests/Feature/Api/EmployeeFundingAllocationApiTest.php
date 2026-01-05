<?php

namespace Tests\Feature\Api;

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

        // Create permissions
        $permissions = [
            'employee.read',
            'employee.create',
            'employee.update',
            'employee.delete',
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

        $this->employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'site_id' => $this->site->id,
            'start_date' => '2025-01-01',
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
                        'allocation_type',
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
    public function it_can_create_grant_allocation()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 100,
                    'allocated_amount' => 35000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'grant_item_id',
                        'allocation_type',
                        'fte',
                    ],
                ],
                'total_created',
            ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $this->grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 1.00, // Stored as decimal
        ]);
    }

    /** @test */
    public function it_can_create_org_funded_allocation()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
            'allocations' => [
                [
                    'allocation_type' => 'org_funded',
                    'grant_id' => $this->grant->id,
                    'fte' => 100,
                    'allocated_amount' => 35000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_id' => $this->grant->id,
            'grant_item_id' => null,
            'allocation_type' => 'org_funded',
            'fte' => 1.00,
        ]);
    }

    /** @test */
    public function it_can_create_mixed_allocations()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
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

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(201);
        $this->assertEquals(2, $response->json('total_created'));

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 0.60,
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_id' => $this->grant->id,
            'fte' => 0.40,
        ]);
    }

    /** @test */
    public function it_validates_total_fte_must_equal_100_percent()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 80, // Only 80%, not 100%
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Total effort of all allocations must equal exactly 100%',
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_active_allocations()
    {
        // Create existing active allocation
        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'allocated_amount' => 35000,
            'start_date' => '2025-01-01',
        ]);

        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-02-01',
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Employee already has active funding allocations for this employment. Please use the update endpoint to modify existing allocations or end them first.',
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
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'start_date' => '2025-01-01',
        ]);

        // Try to create second allocation (should fail)
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
            'allocations' => [
                [
                    'allocation_type' => 'grant',
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
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'allocated_amount' => 35000,
            'start_date' => '2025-01-01',
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
                    'allocation_type',
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
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'allocated_amount' => 35000,
            'start_date' => '2025-01-01',
        ]);

        $updateData = [
            'fte' => 80,
            'allocated_amount' => 28000,
        ];

        $response = $this->putJson("/api/v1/employee-funding-allocations/{$allocation->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'id' => $allocation->id,
            'fte' => 0.80,
            'allocated_amount' => 28000,
        ]);
    }

    /** @test */
    public function it_can_change_allocation_type_on_update()
    {
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'grant_id' => null,
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'start_date' => '2025-01-01',
        ]);

        $updateData = [
            'allocation_type' => 'org_funded',
            'grant_id' => $this->grant->id,
        ];

        $response = $this->putJson("/api/v1/employee-funding-allocations/{$allocation->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'id' => $allocation->id,
            'allocation_type' => 'org_funded',
            'grant_id' => $this->grant->id,
            'grant_item_id' => null, // Should be cleared
        ]);
    }

    /** @test */
    public function it_can_delete_allocation()
    {
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'start_date' => '2025-01-01',
        ]);

        $response = $this->deleteJson("/api/v1/employee-funding-allocations/{$allocation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employee funding allocation deleted successfully',
            ]);

        $this->assertDatabaseMissing('employee_funding_allocations', [
            'id' => $allocation->id,
        ]);
    }

    /** @test */
    public function it_can_get_allocations_by_employee()
    {
        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 0.60,
            'start_date' => '2025-01-01',
        ]);

        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_id' => $this->grant->id,
            'allocation_type' => 'org_funded',
            'fte' => 0.40,
            'start_date' => '2025-01-01',
        ]);

        $response = $this->getJson("/api/v1/employee-funding-allocations/employee/{$this->employee->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'employee' => [
                    'id',
                    'staff_id',
                    'first_name_en',
                    'last_name_en',
                ],
                'total_allocations',
                'total_effort',
                'data',
            ])
            ->assertJson([
                'total_allocations' => 2,
                'total_effort' => 100.0, // 60% + 40% = 100%
            ]);
    }

    /** @test */
    public function it_can_get_allocations_by_grant_item()
    {
        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'start_date' => '2025-01-01',
        ]);

        $response = $this->getJson("/api/v1/employee-funding-allocations/by-grant-item/{$this->grantItem->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'total_allocations',
                'active_allocations',
            ])
            ->assertJson([
                'total_allocations' => 1,
                'active_allocations' => 1,
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
            'allocation_type' => 'grant',
            'fte' => 0.60,
            'start_date' => '2025-01-01',
            'end_date' => null,
        ]);

        $allocation2 = EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_id' => $this->grant->id,
            'allocation_type' => 'org_funded',
            'fte' => 0.40,
            'start_date' => '2025-01-01',
            'end_date' => null,
        ]);

        $response = $this->postJson('/api/v1/employee-funding-allocations/bulk-deactivate', [
            'allocation_ids' => [$allocation1->id, $allocation2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'deactivated_count' => 2,
            ]);

        // Check that end_date is set to today
        $allocation1->refresh();
        $allocation2->refresh();

        $this->assertNotNull($allocation1->end_date);
        $this->assertNotNull($allocation2->end_date);
    }

    /** @test */
    public function it_can_update_all_employee_allocations()
    {
        // Create existing allocations
        EmployeeFundingAllocation::create([
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'grant_item_id' => $this->grantItem->id,
            'allocation_type' => 'grant',
            'fte' => 1.00,
            'start_date' => '2025-01-01',
        ]);

        // Update with new allocation split
        $updateData = [
            'employment_id' => $this->employment->id,
            'start_date' => '2025-02-01',
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 70,
                ],
                [
                    'allocation_type' => 'org_funded',
                    'grant_id' => $this->grant->id,
                    'fte' => 30,
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/employee-funding-allocations/employee/{$this->employee->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_created' => 2,
            ]);

        // New allocations should exist
        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_item_id' => $this->grantItem->id,
            'fte' => 0.70,
            'start_date' => '2025-02-01',
        ]);

        $this->assertDatabaseHas('employee_funding_allocations', [
            'employee_id' => $this->employee->id,
            'grant_id' => $this->grant->id,
            'fte' => 0.30,
            'start_date' => '2025-02-01',
        ]);
    }

    /** @test */
    public function it_requires_grant_item_id_for_grant_allocations()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
            'allocations' => [
                [
                    'allocation_type' => 'grant',
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
    public function it_requires_grant_id_for_org_funded_allocations()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
            'allocations' => [
                [
                    'allocation_type' => 'org_funded',
                    // Missing grant_id
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations.0.grant_id']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/v1/employee-funding-allocations');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_checks_permissions_for_create()
    {
        $userWithoutPermission = User::factory()->create();
        Sanctum::actingAs($userWithoutPermission);

        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-01-01',
            'allocations' => [
                [
                    'allocation_type' => 'grant',
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
                'message' => 'Employee funding allocation not found',
            ]);
    }

    /** @test */
    public function it_validates_end_date_after_start_date()
    {
        $allocationData = [
            'employee_id' => $this->employee->id,
            'employment_id' => $this->employment->id,
            'start_date' => '2025-12-01',
            'end_date' => '2025-01-01', // Before start_date
            'allocations' => [
                [
                    'allocation_type' => 'grant',
                    'grant_item_id' => $this->grantItem->id,
                    'fte' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/employee-funding-allocations', $allocationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
