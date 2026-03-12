<?php

use App\Models\Employee;
use App\Models\EmployeeIdentification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Employee Identification API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'employees.read']);
        Permission::firstOrCreate(['name' => 'employees.create']);
        Permission::firstOrCreate(['name' => 'employees.update']);
        Permission::firstOrCreate(['name' => 'employees.delete']);

        $this->user->givePermissionTo(['employees.read', 'employees.create', 'employees.update', 'employees.delete']);
        $this->actingAs($this->user);
    });

    describe('GET /api/v1/employee-identifications', function () {
        it('lists identifications for an employee', function () {
            $employee = Employee::factory()->create();
            EmployeeIdentification::factory()->count(2)->create(['employee_id' => $employee->id]);

            $response = $this->getJson("/api/v1/employee-identifications?employee_id={$employee->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
            expect($response->json('data'))->toHaveCount(2);
        });

        it('requires employee_id parameter', function () {
            $response = $this->getJson('/api/v1/employee-identifications');

            $response->assertStatus(422);
        });
    });

    describe('POST /api/v1/employee-identifications', function () {
        it('creates a new identification', function () {
            $employee = Employee::factory()->create();

            $data = [
                'employee_id' => $employee->id,
                'identification_type' => 'Passport',
                'identification_number' => 'AB1234567',
                'first_name_en' => 'John',
                'last_name_en' => 'Doe',
            ];

            $response = $this->postJson('/api/v1/employee-identifications', $data);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('employee_identifications', [
                'employee_id' => $employee->id,
                'identification_type' => 'Passport',
                'identification_number' => 'AB1234567',
            ]);
        });

        it('auto-sets first identification as primary', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/employee-identifications', [
                'employee_id' => $employee->id,
                'identification_type' => 'ThaiID',
                'identification_number' => '1234567890123',
            ]);

            $response->assertStatus(201);

            $this->assertDatabaseHas('employee_identifications', [
                'employee_id' => $employee->id,
                'is_primary' => true,
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/employee-identifications', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id', 'identification_type', 'identification_number']);
        });

        it('validates identification_type enum', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/employee-identifications', [
                'employee_id' => $employee->id,
                'identification_type' => 'InvalidType',
                'identification_number' => '12345',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['identification_type']);
        });
    });

    describe('PUT /api/v1/employee-identifications/{id}', function () {
        it('updates an identification', function () {
            $identification = EmployeeIdentification::factory()->create([
                'identification_number' => 'OLD123',
            ]);

            $response = $this->putJson("/api/v1/employee-identifications/{$identification->id}", [
                'identification_number' => 'NEW456',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('employee_identifications', [
                'id' => $identification->id,
                'identification_number' => 'NEW456',
            ]);
        });

        it('syncs names to employee when primary record name fields are updated', function () {
            $employee = Employee::factory()->create([
                'first_name_en' => 'OldFirst',
                'last_name_en' => 'OldLast',
            ]);
            $identification = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => true,
                'first_name_en' => 'OldFirst',
                'last_name_en' => 'OldLast',
            ]);

            $response = $this->putJson("/api/v1/employee-identifications/{$identification->id}", [
                'first_name_en' => 'NewFirst',
                'last_name_en' => 'NewLast',
            ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name_en' => 'NewFirst',
                'last_name_en' => 'NewLast',
            ]);
        });
    });

    describe('PATCH /api/v1/employee-identifications/{id}/set-primary', function () {
        it('sets identification as primary and syncs names', function () {
            $employee = Employee::factory()->create([
                'first_name_en' => 'OriginalFirst',
                'last_name_en' => 'OriginalLast',
            ]);

            EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => true,
                'first_name_en' => 'OriginalFirst',
            ]);

            $newPrimary = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => false,
                'first_name_en' => 'PassportFirst',
                'last_name_en' => 'PassportLast',
                'identification_type' => 'Passport',
            ]);

            $response = $this->patchJson("/api/v1/employee-identifications/{$newPrimary->id}/set-primary");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('employee_identifications', [
                'id' => $newPrimary->id,
                'is_primary' => true,
            ]);

            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name_en' => 'PassportFirst',
                'last_name_en' => 'PassportLast',
            ]);
        });

        it('is a no-op when already primary', function () {
            $identification = EmployeeIdentification::factory()->create(['is_primary' => true]);

            $response = $this->patchJson("/api/v1/employee-identifications/{$identification->id}/set-primary");

            $response->assertStatus(200);
        });
    });

    describe('DELETE /api/v1/employee-identifications/{id}', function () {
        it('deletes a non-primary identification', function () {
            $employee = Employee::factory()->create();
            EmployeeIdentification::factory()->create(['employee_id' => $employee->id, 'is_primary' => true]);
            $nonPrimary = EmployeeIdentification::factory()->create(['employee_id' => $employee->id, 'is_primary' => false]);

            $response = $this->deleteJson("/api/v1/employee-identifications/{$nonPrimary->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('prevents deleting the only identification', function () {
            $employee = Employee::factory()->create();
            $only = EmployeeIdentification::factory()->create(['employee_id' => $employee->id, 'is_primary' => true]);

            $response = $this->deleteJson("/api/v1/employee-identifications/{$only->id}");

            $response->assertStatus(422);
        });

        it('promotes another identification when primary is deleted', function () {
            $employee = Employee::factory()->create();
            $primary = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => true,
            ]);
            $other = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => false,
                'first_name_en' => 'PromotedName',
            ]);

            $response = $this->deleteJson("/api/v1/employee-identifications/{$primary->id}");

            $response->assertStatus(200);

            $this->assertDatabaseHas('employee_identifications', [
                'id' => $other->id,
                'is_primary' => true,
            ]);

            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name_en' => 'PromotedName',
            ]);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/employee-identifications?employee_id=1');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $employee = Employee::factory()->create();
            $response = $this->getJson("/api/v1/employee-identifications?employee_id={$employee->id}");

            $response->assertStatus(403);
        });
    });
});
