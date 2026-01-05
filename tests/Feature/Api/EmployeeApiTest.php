<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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
    }

    /** @test */
    public function it_can_list_employees()
    {
        Employee::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/employees');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'staff_id',
                        'first_name_en',
                        'last_name_en',
                        'organization',
                        'status',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_paginate_employees()
    {
        Employee::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/employees?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $this->assertLessThanOrEqual(10, count($response->json('data')));
    }

    /** @test */
    public function it_can_create_employee()
    {
        $employeeData = [
            'organization' => 'SMRU',
            'staff_id' => 'EMP999',
            'first_name_en' => 'Jane',
            'last_name_en' => 'Smith',
            'gender' => 'Female',
            'date_of_birth' => '1995-05-15',
            'status' => 'Active',
            'email' => 'jane.smith@example.com',
            'phone_number' => '1234567890',
        ];

        $response = $this->postJson('/api/v1/employees', $employeeData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'staff_id',
                    'first_name_en',
                    'last_name_en',
                ],
            ]);

        $this->assertDatabaseHas('employees', [
            'staff_id' => 'EMP999',
            'first_name_en' => 'Jane',
            'last_name_en' => 'Smith',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        $response = $this->postJson('/api/v1/employees', [
            // Missing required fields
            'first_name_en' => 'John',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['staff_id', 'last_name_en', 'gender', 'date_of_birth']);
    }

    /** @test */
    public function it_can_show_employee_by_id()
    {
        $employee = Employee::factory()->create();

        $response = $this->getJson("/api/v1/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'staff_id',
                    'first_name_en',
                    'last_name_en',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $employee->id,
                    'staff_id' => $employee->staff_id,
                ],
            ]);
    }

    /** @test */
    public function it_can_show_employee_by_staff_id()
    {
        $employee = Employee::factory()->create([
            'staff_id' => '1234',
        ]);

        $response = $this->getJson("/api/v1/employees/staff-id/{$employee->staff_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'staff_id' => '1234',
                ],
            ]);
    }

    /** @test */
    public function it_can_update_employee()
    {
        $employee = Employee::factory()->create([
            'first_name_en' => 'Original',
            'last_name_en' => 'Name',
        ]);

        $updateData = [
            'first_name_en' => 'Updated',
            'last_name_en' => 'Name',
            'gender' => $employee->gender,
            'date_of_birth' => $employee->date_of_birth,
        ];

        $response = $this->putJson("/api/v1/employees/{$employee->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name_en' => 'Updated',
        ]);
    }

    /** @test */
    public function it_can_update_employee_basic_information()
    {
        $employee = Employee::factory()->create();

        $updateData = [
            'first_name_en' => 'Updated First',
            'last_name_en' => 'Updated Last',
            'first_name_th' => 'ชื่อแรก',
            'last_name_th' => 'นามสกุล',
            'nickname_en' => 'Nick',
            'nickname_th' => 'นิค',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-15',
        ];

        $response = $this->putJson("/api/v1/employees/{$employee->id}/basic-information", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name_en' => 'Updated First',
            'nickname_en' => 'Nick',
        ]);
    }

    /** @test */
    public function it_can_update_employee_personal_information()
    {
        $employee = Employee::factory()->create();

        $updateData = [
            'email' => 'newemail@example.com',
            'phone_number' => '9876543210',
            'address' => '123 New Street',
            'nationality' => 'Thai',
        ];

        $response = $this->putJson("/api/v1/employees/{$employee->id}/personal-information", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'email' => 'newemail@example.com',
        ]);
    }

    /** @test */
    public function it_can_update_employee_family_information()
    {
        $employee = Employee::factory()->create();

        $updateData = [
            'marital_status' => 'Married',
            'spouse_name' => 'Jane Doe',
            'number_of_children' => 2,
        ];

        $response = $this->putJson("/api/v1/employees/{$employee->id}/family-information", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'marital_status' => 'Married',
        ]);
    }

    /** @test */
    public function it_can_update_employee_bank_information()
    {
        $employee = Employee::factory()->create();

        $updateData = [
            'bank_name' => 'Bangkok Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'John Doe',
        ];

        $response = $this->putJson("/api/v1/employees/{$employee->id}/bank-information", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'bank_name' => 'Bangkok Bank',
            'bank_account_number' => '1234567890',
        ]);
    }

    /** @test */
    public function it_can_delete_employee()
    {
        $employee = Employee::factory()->create();

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employee deleted successfully',
            ]);

        $this->assertSoftDeleted('employees', [
            'id' => $employee->id,
        ]);
    }

    /** @test */
    public function it_can_filter_employees_by_status()
    {
        Employee::factory()->create(['status' => 'Active']);
        Employee::factory()->create(['status' => 'Inactive']);

        $response = $this->getJson('/api/v1/employees?status=Active');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        foreach ($data as $employee) {
            $this->assertEquals('Active', $employee['status']);
        }
    }

    /** @test */
    public function it_can_filter_employees_by_organization()
    {
        Employee::factory()->create(['organization' => 'SMRU']);
        Employee::factory()->create(['organization' => 'MORU']);

        $response = $this->getJson('/api/v1/employees?organization=SMRU');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        foreach ($data as $employee) {
            $this->assertEquals('SMRU', $employee['organization']);
        }
    }

    /** @test */
    public function it_can_search_employees_by_name()
    {
        Employee::factory()->create([
            'first_name_en' => 'John',
            'last_name_en' => 'Smith',
        ]);

        Employee::factory()->create([
            'first_name_en' => 'Jane',
            'last_name_en' => 'Doe',
        ]);

        $response = $this->getJson('/api/v1/employees?search=John');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertStringContainsString('John', $data[0]['first_name_en']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/v1/employees');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_checks_permissions_for_create()
    {
        $userWithoutPermission = User::factory()->create();
        Sanctum::actingAs($userWithoutPermission);

        $employeeData = [
            'organization' => 'SMRU',
            'staff_id' => 'EMP999',
            'first_name_en' => 'Jane',
            'last_name_en' => 'Smith',
            'gender' => 'Female',
            'date_of_birth' => '1995-05-15',
            'status' => 'Active',
        ];

        $response = $this->postJson('/api/v1/employees', $employeeData);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_checks_permissions_for_update()
    {
        $employee = Employee::factory()->create();

        $userWithoutPermission = User::factory()->create();
        Sanctum::actingAs($userWithoutPermission);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'first_name_en' => 'Updated',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_checks_permissions_for_delete()
    {
        $employee = Employee::factory()->create();

        $userWithoutPermission = User::factory()->create();
        Sanctum::actingAs($userWithoutPermission);

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_404_for_non_existent_employee()
    {
        $response = $this->getJson('/api/v1/employees/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_prevents_duplicate_staff_id()
    {
        Employee::factory()->create([
            'staff_id' => 'DUP001',
        ]);

        $employeeData = [
            'organization' => 'SMRU',
            'staff_id' => 'DUP001', // Duplicate
            'first_name_en' => 'Jane',
            'last_name_en' => 'Smith',
            'gender' => 'Female',
            'date_of_birth' => '1995-05-15',
            'status' => 'Active',
        ];

        $response = $this->postJson('/api/v1/employees', $employeeData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['staff_id']);
    }

    /** @test */
    public function it_can_get_employees_for_tree_search()
    {
        Employee::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/employees/tree-search');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'staff_id',
                        'first_name_en',
                        'last_name_en',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_validates_date_of_birth_format()
    {
        $employeeData = [
            'organization' => 'SMRU',
            'staff_id' => 'EMP998',
            'first_name_en' => 'Jane',
            'last_name_en' => 'Smith',
            'gender' => 'Female',
            'date_of_birth' => 'invalid-date',
            'status' => 'Active',
        ];

        $response = $this->postJson('/api/v1/employees', $employeeData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $employee = Employee::factory()->create();

        $updateData = [
            'email' => 'invalid-email',
        ];

        $response = $this->putJson("/api/v1/employees/{$employee->id}/personal-information", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
