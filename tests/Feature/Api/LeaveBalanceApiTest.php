<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Leave Balance API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'leave_balances.read']);
        Permission::firstOrCreate(['name' => 'leave_balances.create']);
        Permission::firstOrCreate(['name' => 'leave_balances.update']);

        $this->user->givePermissionTo(['leave_balances.read', 'leave_balances.create', 'leave_balances.update']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/leave-balances', function () {
        it('returns leave balances list', function () {
            $employee = Employee::factory()->create();
            $leaveType = LeaveType::factory()->create(['name' => 'LB List Test Type']);
            LeaveBalance::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => 2026,
            ]);

            $response = $this->getJson('/api/v1/leave-balances');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                ])
                ->assertJson(['success' => true]);
        });

        it('filters leave balances by employee_id', function () {
            $employee = Employee::factory()->create();
            $otherEmployee = Employee::factory()->create();
            $leaveType = LeaveType::factory()->create(['name' => 'LB Employee Filter Type']);

            LeaveBalance::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => 2026,
            ]);

            $otherLeaveType = LeaveType::factory()->create(['name' => 'LB Other Filter Type']);
            LeaveBalance::factory()->create([
                'employee_id' => $otherEmployee->id,
                'leave_type_id' => $otherLeaveType->id,
                'year' => 2026,
            ]);

            $response = $this->getJson("/api/v1/leave-balances?employee_id={$employee->id}");

            $response->assertStatus(200);
        });

        it('filters leave balances by year', function () {
            $employee = Employee::factory()->create();
            $lt1 = LeaveType::factory()->create(['name' => 'LB Year 2025 Type']);
            $lt2 = LeaveType::factory()->create(['name' => 'LB Year 2026 Type']);

            LeaveBalance::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $lt1->id,
                'year' => 2025,
            ]);
            LeaveBalance::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $lt2->id,
                'year' => 2026,
            ]);

            $response = $this->getJson('/api/v1/leave-balances?year=2026');

            $response->assertStatus(200);
            $data = $response->json('data');
            foreach ($data as $balance) {
                expect($balance['year'])->toBe(2026);
            }
        });
    });

    describe('GET /api/v1/leave-balances/{employeeId}/{leaveTypeId}', function () {
        it('returns specific leave balance', function () {
            $employee = Employee::factory()->create();
            $leaveType = LeaveType::factory()->create(['name' => 'LB Show Type']);
            LeaveBalance::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'total_days' => 15,
                'used_days' => 5,
                'remaining_days' => 10,
                'year' => 2026,
            ]);

            $response = $this->getJson("/api/v1/leave-balances/{$employee->id}/{$leaveType->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('returns 404 when balance not found', function () {
            $response = $this->getJson('/api/v1/leave-balances/99999/99999');

            $response->assertStatus(404);
        });
    });

    describe('POST /api/v1/leave-balances', function () {
        it('creates a new leave balance', function () {
            $employee = Employee::factory()->create();
            $leaveType = LeaveType::factory()->create(['name' => 'LB Create Type']);

            $data = [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'total_days' => 15,
                'year' => 2026,
            ];

            $response = $this->postJson('/api/v1/leave-balances', $data);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('leave_balances', [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => 2026,
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/leave-balances', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id', 'leave_type_id', 'total_days', 'year']);
        });

        it('validates employee exists', function () {
            $leaveType = LeaveType::factory()->create(['name' => 'LB Validate Emp Type']);

            $response = $this->postJson('/api/v1/leave-balances', [
                'employee_id' => 99999,
                'leave_type_id' => $leaveType->id,
                'total_days' => 15,
                'year' => 2026,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id']);
        });

        it('validates leave type exists', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/leave-balances', [
                'employee_id' => $employee->id,
                'leave_type_id' => 99999,
                'total_days' => 15,
                'year' => 2026,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['leave_type_id']);
        });

        it('validates total_days is non-negative', function () {
            $employee = Employee::factory()->create();
            $leaveType = LeaveType::factory()->create(['name' => 'LB Validate Days Type']);

            $response = $this->postJson('/api/v1/leave-balances', [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'total_days' => -5,
                'year' => 2026,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['total_days']);
        });

        it('validates year range', function () {
            $employee = Employee::factory()->create();
            $leaveType = LeaveType::factory()->create(['name' => 'LB Validate Year Type']);

            $response = $this->postJson('/api/v1/leave-balances', [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'total_days' => 15,
                'year' => 2019,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['year']);
        });
    });

    describe('PUT /api/v1/leave-balances/{id}', function () {
        it('updates existing leave balance', function () {
            $employee = Employee::factory()->create();
            $leaveType = LeaveType::factory()->create(['name' => 'LB Update Type']);
            $balance = LeaveBalance::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'total_days' => 15,
                'year' => 2026,
            ]);

            $response = $this->putJson("/api/v1/leave-balances/{$balance->id}", [
                'total_days' => 20,
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('returns 404 for non-existent leave balance', function () {
            $response = $this->putJson('/api/v1/leave-balances/99999', [
                'total_days' => 20,
            ]);

            $response->assertStatus(404);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/leave-balances');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/leave-balances');

            $response->assertStatus(403);
        });
    });
});
