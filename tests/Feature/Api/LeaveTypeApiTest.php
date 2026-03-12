<?php

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Leave Type API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'leave_types.read']);
        Permission::firstOrCreate(['name' => 'leave_types.create']);
        Permission::firstOrCreate(['name' => 'leave_types.update']);
        Permission::firstOrCreate(['name' => 'leave_types.delete']);

        $this->user->givePermissionTo(['leave_types.read', 'leave_types.create', 'leave_types.update', 'leave_types.delete']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/leave-types', function () {
        it('returns leave types list', function () {
            LeaveType::factory()->count(5)->create();

            $response = $this->getJson('/api/v1/leave-types');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                ])
                ->assertJson(['success' => true]);
        });

        it('searches leave types by name', function () {
            LeaveType::factory()->create(['name' => 'Annual Leave']);
            LeaveType::factory()->create(['name' => 'Sick Leave']);

            $response = $this->getJson('/api/v1/leave-types?search=Annual');

            $response->assertStatus(200);
            $data = $response->json('data');
            expect(count($data))->toBeGreaterThanOrEqual(1);
        });
    });

    describe('GET /api/v1/leave-types/options', function () {
        it('returns leave type options for dropdowns', function () {
            LeaveType::factory()->count(3)->create();

            $response = $this->getJson('/api/v1/leave-types/options');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('POST /api/v1/leave-types', function () {
        it('creates a new leave type', function () {
            $data = [
                'name' => 'Bereavement Leave',
                'default_duration' => 5,
                'description' => 'Leave for family bereavement',
                'requires_attachment' => false,
            ];

            $response = $this->postJson('/api/v1/leave-types', $data);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'Bereavement Leave',
                    ],
                ]);

            $this->assertDatabaseHas('leave_types', ['name' => 'Bereavement Leave']);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/leave-types', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('validates unique name', function () {
            LeaveType::factory()->create(['name' => 'Annual Leave']);

            $response = $this->postJson('/api/v1/leave-types', [
                'name' => 'Annual Leave',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('validates name max length', function () {
            $response = $this->postJson('/api/v1/leave-types', [
                'name' => str_repeat('a', 101),
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });
    });

    describe('PUT /api/v1/leave-types/{id}', function () {
        it('updates existing leave type', function () {
            $leaveType = LeaveType::factory()->create(['name' => 'Old Name']);

            $response = $this->putJson("/api/v1/leave-types/{$leaveType->id}", [
                'name' => 'Updated Name',
                'default_duration' => 10,
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('leave_types', [
                'id' => $leaveType->id,
                'name' => 'Updated Name',
            ]);
        });

        it('returns 404 for non-existent leave type', function () {
            $response = $this->putJson('/api/v1/leave-types/99999', [
                'name' => 'Updated',
            ]);

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/v1/leave-types/{id}', function () {
        it('deletes existing leave type', function () {
            $leaveType = LeaveType::factory()->create();

            $response = $this->deleteJson("/api/v1/leave-types/{$leaveType->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('returns 404 for non-existent leave type', function () {
            $response = $this->deleteJson('/api/v1/leave-types/99999');

            $response->assertStatus(404);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/leave-types');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/leave-types');

            $response->assertStatus(403);
        });
    });
});
