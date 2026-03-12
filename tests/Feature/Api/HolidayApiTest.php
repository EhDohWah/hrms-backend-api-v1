<?php

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Holiday API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'holidays.read']);
        Permission::firstOrCreate(['name' => 'holidays.create']);
        Permission::firstOrCreate(['name' => 'holidays.update']);
        Permission::firstOrCreate(['name' => 'holidays.delete']);

        $this->user->givePermissionTo(['holidays.read', 'holidays.create', 'holidays.update', 'holidays.delete']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/holidays', function () {
        it('returns paginated holidays list', function () {
            Holiday::factory()->count(15)->create();

            $response = $this->getJson('/api/v1/holidays');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'date',
                        ],
                    ],
                ])
                ->assertJson(['success' => true]);
        });

        it('filters holidays by year', function () {
            Holiday::factory()->forYear(2025)->create();
            Holiday::factory()->forYear(2026)->create();

            $response = $this->getJson('/api/v1/holidays?year=2026');

            $response->assertStatus(200);
            $data = $response->json('data');
            foreach ($data as $holiday) {
                expect($holiday['year'])->toBe(2026);
            }
        });

        it('searches holidays by name', function () {
            Holiday::factory()->create(['name' => 'New Year Day']);
            Holiday::factory()->create(['name' => 'Songkran Festival']);

            $response = $this->getJson('/api/v1/holidays?search=Songkran');

            $response->assertStatus(200);
            $data = $response->json('data');
            expect(count($data))->toBeGreaterThanOrEqual(1);
        });

        it('handles pagination parameters', function () {
            Holiday::factory()->count(20)->create();

            $response = $this->getJson('/api/v1/holidays?page=2&per_page=5');

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(5);
        });

        it('sorts holidays by date ascending', function () {
            Holiday::factory()->create(['name' => 'B Holiday', 'date' => '2026-06-01']);
            Holiday::factory()->create(['name' => 'A Holiday', 'date' => '2026-01-01']);
            Holiday::factory()->create(['name' => 'C Holiday', 'date' => '2026-12-01']);

            $response = $this->getJson('/api/v1/holidays?sort=date_asc');

            $response->assertStatus(200);
            $dates = collect($response->json('data'))->pluck('date')->toArray();
            expect($dates)->toBe(['2026-01-01', '2026-06-01', '2026-12-01']);
        });
    });

    describe('GET /api/v1/holidays/options', function () {
        it('returns holiday options', function () {
            Holiday::factory()->count(3)->create();

            $response = $this->getJson('/api/v1/holidays/options');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/holidays/in-range', function () {
        it('returns holidays within a date range', function () {
            Holiday::factory()->create(['date' => '2026-03-15']);
            Holiday::factory()->create(['date' => '2026-06-15']);
            Holiday::factory()->create(['date' => '2026-09-15']);

            $response = $this->getJson('/api/v1/holidays/in-range?start_date=2026-03-01&end_date=2026-07-01');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/holidays/{id}', function () {
        it('returns specific holiday', function () {
            $holiday = Holiday::factory()->create(['name' => 'Test Holiday']);

            $response = $this->getJson("/api/v1/holidays/{$holiday->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $holiday->id,
                        'name' => 'Test Holiday',
                    ],
                ]);
        });

        it('returns 404 for non-existent holiday', function () {
            $response = $this->getJson('/api/v1/holidays/99999');

            $response->assertStatus(404);
        });
    });

    describe('POST /api/v1/holidays', function () {
        it('creates a new holiday', function () {
            $data = [
                'name' => 'New Holiday',
                'date' => '2026-07-04',
                'description' => 'A test holiday',
            ];

            $response = $this->postJson('/api/v1/holidays', $data);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'New Holiday',
                        'date' => '2026-07-04',
                    ],
                ]);

            $this->assertDatabaseHas('holidays', ['name' => 'New Holiday']);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/holidays', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'date']);
        });

        it('validates unique date', function () {
            Holiday::factory()->create(['date' => '2026-01-01']);

            $response = $this->postJson('/api/v1/holidays', [
                'name' => 'Duplicate Date Holiday',
                'date' => '2026-01-01',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['date']);
        });

        it('validates date format', function () {
            $response = $this->postJson('/api/v1/holidays', [
                'name' => 'Test Holiday',
                'date' => 'not-a-date',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['date']);
        });
    });

    describe('POST /api/v1/holidays/bulk', function () {
        it('creates multiple holidays at once', function () {
            $data = [
                'holidays' => [
                    ['name' => 'Holiday 1', 'date' => '2026-08-01'],
                    ['name' => 'Holiday 2', 'date' => '2026-08-15'],
                ],
            ];

            $response = $this->postJson('/api/v1/holidays/bulk', $data);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('holidays', ['name' => 'Holiday 1']);
            $this->assertDatabaseHas('holidays', ['name' => 'Holiday 2']);
        });
    });

    describe('PUT /api/v1/holidays/{id}', function () {
        it('updates existing holiday', function () {
            $holiday = Holiday::factory()->create(['name' => 'Old Name']);

            $response = $this->putJson("/api/v1/holidays/{$holiday->id}", [
                'name' => 'Updated Name',
                'date' => $holiday->date->format('Y-m-d'),
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('holidays', [
                'id' => $holiday->id,
                'name' => 'Updated Name',
            ]);
        });

        it('returns 404 for non-existent holiday', function () {
            $response = $this->putJson('/api/v1/holidays/99999', [
                'name' => 'Updated',
                'date' => '2026-01-01',
            ]);

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/v1/holidays/{id}', function () {
        it('deletes existing holiday', function () {
            $holiday = Holiday::factory()->create();

            $response = $this->deleteJson("/api/v1/holidays/{$holiday->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
        });

        it('returns 404 for non-existent holiday', function () {
            $response = $this->deleteJson('/api/v1/holidays/99999');

            $response->assertStatus(404);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/holidays');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/holidays');

            $response->assertStatus(403);
        });
    });
});
