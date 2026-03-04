<?php

use App\Enums\ResignationAcknowledgementStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Resignation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Resignation API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'resignation.read']);
        Permission::firstOrCreate(['name' => 'resignation.edit']);

        $this->user->givePermissionTo(['resignation.read', 'resignation.edit']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/resignations', function () {
        it('returns paginated resignations list', function () {
            Resignation::factory()->count(15)->create();

            $response = $this->getJson('/api/v1/resignations');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'employee_id',
                            'resignation_date',
                            'last_working_date',
                            'reason',
                            'acknowledgement_status',
                        ],
                    ],
                ])
                ->assertJson(['success' => true]);
        });

        it('filters by acknowledgement status', function () {
            Resignation::factory()->create(['acknowledgement_status' => ResignationAcknowledgementStatus::Pending->value]);
            Resignation::factory()->acknowledged()->create();
            Resignation::factory()->rejected()->create();

            $response = $this->getJson('/api/v1/resignations?acknowledgement_status=Pending');

            $response->assertStatus(200);
            $data = $response->json('data');
            foreach ($data as $resignation) {
                expect($resignation['acknowledgement_status'])->toBe('Pending');
            }
        });

        it('filters by department_id', function () {
            $department = Department::factory()->create();
            Resignation::factory()->count(2)->create(['department_id' => $department->id]);
            Resignation::factory()->create();

            $response = $this->getJson("/api/v1/resignations?department_id={$department->id}");

            $response->assertStatus(200);
        });

        it('filters by reason', function () {
            Resignation::factory()->create(['reason' => 'Career Advancement']);
            Resignation::factory()->create(['reason' => 'Personal Reasons']);

            $response = $this->getJson('/api/v1/resignations?reason=Career Advancement');

            $response->assertStatus(200);
            $data = $response->json('data');
            foreach ($data as $resignation) {
                expect($resignation['reason'])->toBe('Career Advancement');
            }
        });

        it('searches resignations', function () {
            $employee = Employee::factory()->create(['first_name_en' => 'John', 'last_name_en' => 'Doe']);
            Resignation::factory()->create(['employee_id' => $employee->id]);

            $response = $this->getJson('/api/v1/resignations?search=John');

            $response->assertStatus(200);
        });

        it('handles pagination parameters', function () {
            Resignation::factory()->count(20)->create();

            $response = $this->getJson('/api/v1/resignations?page=2&per_page=5');

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(5);
        });
    });

    describe('GET /api/v1/resignations/{id}', function () {
        it('returns specific resignation', function () {
            $resignation = Resignation::factory()->create(['reason' => 'Career Advancement']);

            $response = $this->getJson("/api/v1/resignations/{$resignation->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $resignation->id,
                        'reason' => 'Career Advancement',
                    ],
                ]);
        });

        it('returns 404 for non-existent resignation', function () {
            $response = $this->getJson('/api/v1/resignations/99999');

            $response->assertStatus(404);
        });
    });

    describe('POST /api/v1/resignations', function () {
        it('creates a new resignation', function () {
            $employee = Employee::factory()->create();
            $department = Department::factory()->create();
            $position = Position::factory()->create(['department_id' => $department->id]);

            $data = [
                'employee_id' => $employee->id,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'resignation_date' => now()->format('Y-m-d'),
                'last_working_date' => now()->addDays(30)->format('Y-m-d'),
                'reason' => 'Career Advancement',
                'reason_details' => 'Accepted a new position elsewhere',
            ];

            $response = $this->postJson('/api/v1/resignations', $data);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('resignations', [
                'employee_id' => $employee->id,
                'reason' => 'Career Advancement',
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/resignations', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id', 'resignation_date', 'last_working_date', 'reason']);
        });

        it('validates employee exists', function () {
            $response = $this->postJson('/api/v1/resignations', [
                'employee_id' => 99999,
                'resignation_date' => now()->format('Y-m-d'),
                'last_working_date' => now()->addDays(30)->format('Y-m-d'),
                'reason' => 'Test',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id']);
        });

        it('validates last_working_date is after resignation_date', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/resignations', [
                'employee_id' => $employee->id,
                'resignation_date' => '2026-02-15',
                'last_working_date' => '2026-02-10',
                'reason' => 'Test',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['last_working_date']);
        });

        it('validates reason max length', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/resignations', [
                'employee_id' => $employee->id,
                'resignation_date' => now()->format('Y-m-d'),
                'last_working_date' => now()->addDays(30)->format('Y-m-d'),
                'reason' => str_repeat('a', 51),
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['reason']);
        });
    });

    describe('PUT /api/v1/resignations/{id}', function () {
        it('updates existing resignation', function () {
            $resignation = Resignation::factory()->create(['reason' => 'Personal Reasons']);

            $response = $this->putJson("/api/v1/resignations/{$resignation->id}", [
                'reason' => 'Career Advancement',
                'reason_details' => 'Updated details',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('resignations', [
                'id' => $resignation->id,
                'reason' => 'Career Advancement',
            ]);
        });

        it('returns 404 for non-existent resignation', function () {
            $response = $this->putJson('/api/v1/resignations/99999', [
                'reason' => 'Updated',
            ]);

            $response->assertStatus(404);
        });
    });

    describe('PUT /api/v1/resignations/{id}/acknowledge', function () {
        it('acknowledges a pending resignation', function () {
            $resignation = Resignation::factory()->create([
                'acknowledgement_status' => ResignationAcknowledgementStatus::Pending->value,
            ]);

            $response = $this->putJson("/api/v1/resignations/{$resignation->id}/acknowledge", [
                'action' => 'acknowledge',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('resignations', [
                'id' => $resignation->id,
                'acknowledgement_status' => 'Acknowledged',
            ]);
        });

        it('rejects a pending resignation', function () {
            $resignation = Resignation::factory()->create([
                'acknowledgement_status' => ResignationAcknowledgementStatus::Pending->value,
            ]);

            $response = $this->putJson("/api/v1/resignations/{$resignation->id}/acknowledge", [
                'action' => 'reject',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('resignations', [
                'id' => $resignation->id,
                'acknowledgement_status' => 'Rejected',
            ]);
        });
    });

    describe('DELETE /api/v1/resignations/{id}', function () {
        it('deletes existing resignation', function () {
            $resignation = Resignation::factory()->create();

            $response = $this->deleteJson("/api/v1/resignations/{$resignation->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('returns 404 for non-existent resignation', function () {
            $response = $this->deleteJson('/api/v1/resignations/99999');

            $response->assertStatus(404);
        });
    });

    describe('GET /api/v1/resignations/search-employees', function () {
        it('searches employees for resignation assignment', function () {
            Employee::factory()->create(['first_name_en' => 'John', 'last_name_en' => 'Doe']);

            $response = $this->getJson('/api/v1/resignations/search-employees?search=John');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/resignations');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/resignations');

            $response->assertStatus(403);
        });
    });
});
