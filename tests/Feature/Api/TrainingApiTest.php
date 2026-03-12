<?php

use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Training API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'training_list.read']);
        Permission::firstOrCreate(['name' => 'training_list.create']);
        Permission::firstOrCreate(['name' => 'training_list.update']);
        Permission::firstOrCreate(['name' => 'training_list.delete']);

        $this->user->givePermissionTo(['training_list.read', 'training_list.create', 'training_list.update', 'training_list.delete']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/trainings', function () {
        it('returns paginated trainings list', function () {
            Training::factory()->count(15)->create();

            $response = $this->getJson('/api/v1/trainings');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'organizer',
                            'start_date',
                            'end_date',
                        ],
                    ],
                ])
                ->assertJson(['success' => true]);
        });

        it('filters trainings by organizer', function () {
            Training::factory()->create(['organizer' => 'Red Cross']);
            Training::factory()->create(['organizer' => 'Internal HR']);

            $response = $this->getJson('/api/v1/trainings?filter_organizer=Red Cross');

            $response->assertStatus(200);
        });

        it('filters trainings by title', function () {
            Training::factory()->create(['title' => 'First Aid Training 1']);
            Training::factory()->create(['title' => 'Leadership Program 2']);

            $response = $this->getJson('/api/v1/trainings?filter_title=First Aid');

            $response->assertStatus(200);
        });

        it('searches trainings', function () {
            Training::factory()->create(['title' => 'CPR Certification 10']);

            $response = $this->getJson('/api/v1/trainings?search=CPR');

            $response->assertStatus(200);
            $data = $response->json('data');
            expect(count($data))->toBeGreaterThanOrEqual(1);
        });

        it('handles pagination parameters', function () {
            Training::factory()->count(20)->create();

            $response = $this->getJson('/api/v1/trainings?page=2&per_page=5');

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(5);
        });
    });

    describe('GET /api/v1/trainings/{id}', function () {
        it('returns specific training', function () {
            $training = Training::factory()->create();

            $response = $this->getJson("/api/v1/trainings/{$training->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $training->id,
                    ],
                ]);
        });

        it('returns 404 for non-existent training', function () {
            $response = $this->getJson('/api/v1/trainings/99999');

            $response->assertStatus(404);
        });
    });

    describe('POST /api/v1/trainings', function () {
        it('creates a new training', function () {
            $data = [
                'title' => 'New Training Course',
                'organizer' => 'Training Corp',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-05',
            ];

            $response = $this->postJson('/api/v1/trainings', $data);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'title' => 'New Training Course',
                        'organizer' => 'Training Corp',
                    ],
                ]);

            $this->assertDatabaseHas('trainings', ['title' => 'New Training Course']);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/trainings', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'organizer', 'start_date', 'end_date']);
        });

        it('validates end_date is after start_date', function () {
            $response = $this->postJson('/api/v1/trainings', [
                'title' => 'Test Training',
                'organizer' => 'Test Org',
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-05',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['end_date']);
        });

        it('validates title max length', function () {
            $response = $this->postJson('/api/v1/trainings', [
                'title' => str_repeat('a', 201),
                'organizer' => 'Test Org',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-05',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title']);
        });

        it('validates organizer max length', function () {
            $response = $this->postJson('/api/v1/trainings', [
                'title' => 'Test Training',
                'organizer' => str_repeat('a', 101),
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-05',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['organizer']);
        });

        it('validates date format', function () {
            $response = $this->postJson('/api/v1/trainings', [
                'title' => 'Test Training',
                'organizer' => 'Test Org',
                'start_date' => 'not-a-date',
                'end_date' => 'also-not-a-date',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['start_date', 'end_date']);
        });
    });

    describe('PUT /api/v1/trainings/{id}', function () {
        it('updates existing training', function () {
            $training = Training::factory()->create();

            $response = $this->putJson("/api/v1/trainings/{$training->id}", [
                'title' => 'Updated Training Title',
                'organizer' => 'Updated Org',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-05',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('trainings', [
                'id' => $training->id,
                'title' => 'Updated Training Title',
            ]);
        });

        it('returns 404 for non-existent training', function () {
            $response = $this->putJson('/api/v1/trainings/99999', [
                'title' => 'Updated',
                'organizer' => 'Org',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-05',
            ]);

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/v1/trainings/{id}', function () {
        it('deletes existing training', function () {
            $training = Training::factory()->create();

            $response = $this->deleteJson("/api/v1/trainings/{$training->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseMissing('trainings', ['id' => $training->id]);
        });

        it('returns 404 for non-existent training', function () {
            $response = $this->deleteJson('/api/v1/trainings/99999');

            $response->assertStatus(404);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/trainings');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/trainings');

            $response->assertStatus(403);
        });
    });
});
