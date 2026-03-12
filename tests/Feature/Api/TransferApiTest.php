<?php

use App\Models\Employee;
use App\Models\Employment;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Transfer API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'transfer.read']);
        Permission::firstOrCreate(['name' => 'transfer.create']);
        Permission::firstOrCreate(['name' => 'transfer.delete']);

        $this->user->givePermissionTo(['transfer.read', 'transfer.create', 'transfer.delete']);

        $this->actingAs($this->user);
    });

    describe('POST /api/v1/transfers', function () {
        it('creates a transfer and updates employment organization', function () {
            $employee = Employee::factory()->create();
            $employment = Employment::factory()->smru()->create([
                'employee_id' => $employee->id,
                'end_date' => null,
            ]);

            $response = $this->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'to_organization' => 'BHF',
                'to_start_date' => '2026-04-01',
                'reason' => 'Cross-organization assignment',
            ]);

            $response->assertStatus(201)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'employee_id',
                        'from_organization',
                        'to_organization',
                        'from_start_date',
                        'to_start_date',
                        'reason',
                    ],
                ]);

            // Verify transfer record created correctly
            $this->assertDatabaseHas('transfers', [
                'employee_id' => $employee->id,
                'from_organization' => 'SMRU',
                'to_organization' => 'BHF',
            ]);

            // Verify employment organization updated
            $this->assertDatabaseHas('employments', [
                'id' => $employment->id,
                'organization' => 'BHF',
            ]);
        });

        it('does NOT modify employment start_date', function () {
            $employee = Employee::factory()->create();
            $employment = Employment::factory()->smru()->create([
                'employee_id' => $employee->id,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ]);

            $this->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'to_organization' => 'BHF',
                'to_start_date' => '2026-04-01',
            ]);

            // start_date must remain unchanged — Golden Rule #1
            $this->assertDatabaseHas('employments', [
                'id' => $employment->id,
                'start_date' => '2025-01-01',
            ]);
        });

        it('does NOT modify employment position_id or department_id', function () {
            $employee = Employee::factory()->create();
            $employment = Employment::factory()->smru()->create([
                'employee_id' => $employee->id,
                'end_date' => null,
            ]);

            $originalPositionId = $employment->position_id;
            $originalDepartmentId = $employment->department_id;

            $this->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'to_organization' => 'BHF',
                'to_start_date' => '2026-04-01',
            ]);

            $this->assertDatabaseHas('employments', [
                'id' => $employment->id,
                'position_id' => $originalPositionId,
                'department_id' => $originalDepartmentId,
            ]);
        });

        it('validates to_organization differs from current', function () {
            $employee = Employee::factory()->create();
            Employment::factory()->smru()->create([
                'employee_id' => $employee->id,
                'end_date' => null,
            ]);

            $response = $this->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'to_organization' => 'SMRU', // Same as current
                'to_start_date' => '2026-04-01',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['to_organization']);
        });

        it('validates employee has active employment', function () {
            $employee = Employee::factory()->create();
            // No employment record

            $response = $this->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'to_organization' => 'BHF',
                'to_start_date' => '2026-04-01',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id']);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/transfers', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id', 'to_organization', 'to_start_date']);
        });
    });

    describe('GET /api/v1/transfers', function () {
        it('lists transfers with pagination', function () {
            Transfer::factory()->count(5)->create();

            $response = $this->getJson('/api/v1/transfers');

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'employee_id',
                                'from_organization',
                                'to_organization',
                            ],
                        ],
                    ],
                ]);
        });

        it('filters by employee_id', function () {
            $employee = Employee::factory()->create();
            Transfer::factory()->count(2)->create(['employee_id' => $employee->id]);
            Transfer::factory()->count(3)->create(); // other employees

            $response = $this->getJson("/api/v1/transfers?employee_id={$employee->id}");

            $response->assertStatus(200);
            $data = $response->json('data.data');
            expect(count($data))->toBe(2);
        });
    });

    describe('GET /api/v1/transfers/{id}', function () {
        it('shows a specific transfer', function () {
            $transfer = Transfer::factory()->create();

            $response = $this->getJson("/api/v1/transfers/{$transfer->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $transfer->id,
                    ],
                ]);
        });

        it('returns 404 for non-existent transfer', function () {
            $response = $this->getJson('/api/v1/transfers/99999');

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/v1/transfers/{id}', function () {
        it('deletes a transfer (soft delete)', function () {
            $transfer = Transfer::factory()->create();

            $response = $this->deleteJson("/api/v1/transfers/{$transfer->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            // Soft-deleted — exists in DB with deleted_at set
            $this->assertSoftDeleted('transfers', ['id' => $transfer->id]);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/transfers');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/transfers');

            $response->assertStatus(403);
        });
    });
});
