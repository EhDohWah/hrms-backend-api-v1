<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Attendance API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Module::create([
            'name' => 'attendance_admin',
            'display_name' => 'Attendance',
            'category' => 'HRM',
            'icon' => 'clock-circle',
            'route' => '/hrm/attendance',
            'read_permission' => 'attendance_admin.read',
            'edit_permissions' => ['attendance_admin.edit'],
            'order' => 25,
            'is_active' => true,
        ]);

        Permission::firstOrCreate(['name' => 'attendance_admin.read']);
        Permission::firstOrCreate(['name' => 'attendance_admin.create']);
        Permission::firstOrCreate(['name' => 'attendance_admin.update']);
        Permission::firstOrCreate(['name' => 'attendance_admin.delete']);

        $this->user->givePermissionTo(['attendance_admin.read', 'attendance_admin.create', 'attendance_admin.update', 'attendance_admin.delete']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/attendances', function () {
        it('returns paginated attendance list', function () {
            Attendance::factory()->count(15)->create();

            $response = $this->getJson('/api/v1/attendances');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'employee_id',
                            'date',
                            'status',
                        ],
                    ],
                ])
                ->assertJson(['success' => true]);
        });

        it('filters attendance by status', function () {
            Attendance::factory()->present()->create();
            Attendance::factory()->absent()->create();
            Attendance::factory()->late()->create();

            $response = $this->getJson('/api/v1/attendances?filter_status=Present');

            $response->assertStatus(200);
            $data = $response->json('data');
            foreach ($data as $record) {
                expect($record['status'])->toBe('Present');
            }
        });

        it('filters attendance by date range', function () {
            Attendance::factory()->onDate('2026-01-15')->create();
            Attendance::factory()->onDate('2026-02-15')->create();
            Attendance::factory()->onDate('2026-03-15')->create();

            $response = $this->getJson('/api/v1/attendances?filter_date_from=2026-02-01&filter_date_to=2026-02-28');

            $response->assertStatus(200);
            $data = $response->json('data');
            expect(count($data))->toBe(1);
        });

        it('searches attendance records', function () {
            $employee = Employee::factory()->create(['first_name_en' => 'John', 'last_name_en' => 'Doe']);
            Attendance::factory()->create(['employee_id' => $employee->id]);

            $response = $this->getJson('/api/v1/attendances?search=John');

            $response->assertStatus(200);
        });

        it('handles pagination parameters', function () {
            Attendance::factory()->count(20)->create();

            $response = $this->getJson('/api/v1/attendances?page=2&per_page=5');

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(5);
        });
    });

    describe('GET /api/v1/attendances/options', function () {
        it('returns attendance options', function () {
            $response = $this->getJson('/api/v1/attendances/options');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/attendances/{id}', function () {
        it('returns specific attendance record', function () {
            $attendance = Attendance::factory()->present()->create();

            $response = $this->getJson("/api/v1/attendances/{$attendance->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $attendance->id,
                    ],
                ]);
        });

        it('returns 404 for non-existent attendance', function () {
            $response = $this->getJson('/api/v1/attendances/99999');

            $response->assertStatus(404);
        });
    });

    describe('POST /api/v1/attendances', function () {
        it('creates a new attendance record', function () {
            $employee = Employee::factory()->create();

            $data = [
                'employee_id' => $employee->id,
                'date' => '2026-02-16',
                'clock_in' => '08:30',
                'clock_out' => '17:30',
                'status' => 'Present',
            ];

            $response = $this->postJson('/api/v1/attendances', $data);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('attendances', [
                'employee_id' => $employee->id,
                'date' => '2026-02-16',
                'status' => 'Present',
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/attendances', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id', 'date', 'status']);
        });

        it('validates employee exists', function () {
            $response = $this->postJson('/api/v1/attendances', [
                'employee_id' => 99999,
                'date' => '2026-02-16',
                'status' => 'Present',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id']);
        });

        it('validates status is valid enum value', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/attendances', [
                'employee_id' => $employee->id,
                'date' => '2026-02-16',
                'status' => 'InvalidStatus',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
        });

        it('validates clock_in time format', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/attendances', [
                'employee_id' => $employee->id,
                'date' => '2026-02-16',
                'status' => 'Present',
                'clock_in' => 'not-a-time',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['clock_in']);
        });

        it('validates notes max length', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/attendances', [
                'employee_id' => $employee->id,
                'date' => '2026-02-16',
                'status' => 'Present',
                'notes' => str_repeat('a', 1001),
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['notes']);
        });
    });

    describe('PUT /api/v1/attendances/{id}', function () {
        it('updates existing attendance record', function () {
            $attendance = Attendance::factory()->present()->create();

            $response = $this->putJson("/api/v1/attendances/{$attendance->id}", [
                'employee_id' => $attendance->employee_id,
                'date' => $attendance->date->format('Y-m-d'),
                'status' => 'Late',
                'notes' => 'Arrived 30 minutes late',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('attendances', [
                'id' => $attendance->id,
                'status' => 'Late',
            ]);
        });

        it('returns 404 for non-existent attendance', function () {
            $employee = Employee::factory()->create();

            $response = $this->putJson('/api/v1/attendances/99999', [
                'employee_id' => $employee->id,
                'date' => '2026-02-16',
                'status' => 'Present',
            ]);

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/v1/attendances/{id}', function () {
        it('deletes existing attendance record', function () {
            $attendance = Attendance::factory()->create();

            $response = $this->deleteJson("/api/v1/attendances/{$attendance->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        });

        it('returns 404 for non-existent attendance', function () {
            $response = $this->deleteJson('/api/v1/attendances/99999');

            $response->assertStatus(404);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/attendances');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/attendances');

            $response->assertStatus(403);
        });
    });
});
