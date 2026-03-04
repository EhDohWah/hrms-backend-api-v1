<?php

use App\Models\Lookup;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Lookup API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Module::create([
            'name' => 'lookup_list',
            'display_name' => 'Lookups',
            'category' => 'Administration',
            'icon' => 'unordered-list',
            'route' => '/administration/lookups',
            'read_permission' => 'lookup_list.read',
            'edit_permissions' => ['lookup_list.edit'],
            'order' => 35,
            'is_active' => true,
        ]);

        Permission::firstOrCreate(['name' => 'lookup_list.read']);
        Permission::firstOrCreate(['name' => 'lookup_list.edit']);

        $this->user->givePermissionTo(['lookup_list.read', 'lookup_list.edit']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/lookups', function () {
        it('returns lookups list', function () {
            Lookup::factory()->count(10)->create();

            $response = $this->getJson('/api/v1/lookups');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('returns grouped lookups when grouped parameter is set', function () {
            Lookup::factory()->forType('gender')->count(3)->create();
            Lookup::factory()->forType('organization')->count(2)->create();

            $response = $this->getJson('/api/v1/lookups?grouped=1');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/lookups/lists', function () {
        it('returns all lookups organized by category', function () {
            Lookup::factory()->forType('gender')->count(3)->create();
            Lookup::factory()->forType('religion')->count(2)->create();

            $response = $this->getJson('/api/v1/lookups/lists');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/lookups/types', function () {
        it('returns all available lookup types', function () {
            Lookup::factory()->forType('gender')->create();
            Lookup::factory()->forType('religion')->create();
            Lookup::factory()->forType('nationality')->create();

            $response = $this->getJson('/api/v1/lookups/types');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/lookups/type/{type}', function () {
        it('returns lookups by specific type', function () {
            Lookup::factory()->forType('gender')->count(3)->create();
            Lookup::factory()->forType('religion')->count(2)->create();

            $response = $this->getJson('/api/v1/lookups/type/gender');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('returns 404 for non-existent type', function () {
            $response = $this->getJson('/api/v1/lookups/type/nonexistent_type');

            $response->assertStatus(404);
        });
    });

    describe('GET /api/v1/lookups/search', function () {
        it('searches lookups by query', function () {
            Lookup::factory()->create(['type' => 'gender', 'value' => 'Male Test']);
            Lookup::factory()->create(['type' => 'gender', 'value' => 'Female Test']);

            $response = $this->getJson('/api/v1/lookups/search?search=Male');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/lookups/{id}', function () {
        it('returns specific lookup', function () {
            $lookup = Lookup::factory()->create(['type' => 'gender', 'value' => 'Male']);

            $response = $this->getJson("/api/v1/lookups/{$lookup->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $lookup->id,
                        'type' => 'gender',
                        'value' => 'Male',
                    ],
                ]);
        });

        it('returns 404 for non-existent lookup', function () {
            $response = $this->getJson('/api/v1/lookups/99999');

            $response->assertStatus(404);
        });
    });

    describe('POST /api/v1/lookups', function () {
        it('creates a new lookup', function () {
            $data = [
                'type' => 'test_type',
                'value' => 'Test Value',
            ];

            $response = $this->postJson('/api/v1/lookups', $data);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'type' => 'test_type',
                        'value' => 'Test Value',
                    ],
                ]);

            $this->assertDatabaseHas('lookups', ['type' => 'test_type', 'value' => 'Test Value']);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/lookups', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['type', 'value']);
        });

        it('validates type max length', function () {
            $response = $this->postJson('/api/v1/lookups', [
                'type' => str_repeat('a', 256),
                'value' => 'Test',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['type']);
        });

        it('validates value max length', function () {
            $response = $this->postJson('/api/v1/lookups', [
                'type' => 'test_type',
                'value' => str_repeat('a', 256),
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['value']);
        });
    });

    describe('PUT /api/v1/lookups/{id}', function () {
        it('updates existing lookup', function () {
            $lookup = Lookup::factory()->create(['value' => 'Old Value']);

            $response = $this->putJson("/api/v1/lookups/{$lookup->id}", [
                'type' => $lookup->type,
                'value' => 'Updated Value',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('lookups', [
                'id' => $lookup->id,
                'value' => 'Updated Value',
            ]);
        });

        it('returns 404 for non-existent lookup', function () {
            $response = $this->putJson('/api/v1/lookups/99999', [
                'type' => 'test',
                'value' => 'Updated',
            ]);

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/v1/lookups/{id}', function () {
        it('deletes existing lookup', function () {
            $lookup = Lookup::factory()->create();

            $response = $this->deleteJson("/api/v1/lookups/{$lookup->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseMissing('lookups', ['id' => $lookup->id]);
        });

        it('returns 404 for non-existent lookup', function () {
            $response = $this->deleteJson('/api/v1/lookups/99999');

            $response->assertStatus(404);
        });
    });

    describe('Read access without write permission', function () {
        it('allows reading lookups without edit permission', function () {
            $readOnlyUser = User::factory()->create();
            Permission::firstOrCreate(['name' => 'lookup_list.read']);
            $readOnlyUser->givePermissionTo('lookup_list.read');
            $this->actingAs($readOnlyUser);

            Lookup::factory()->count(3)->create();

            $response = $this->getJson('/api/v1/lookups');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/lookups');

            $response->assertStatus(401);
        });

        it('returns 403 for write operations without edit permission', function () {
            $readOnlyUser = User::factory()->create();
            $readOnlyUser->givePermissionTo('lookup_list.read');
            $this->actingAs($readOnlyUser);

            $response = $this->postJson('/api/v1/lookups', [
                'type' => 'test',
                'value' => 'Test',
            ]);

            $response->assertStatus(403);
        });
    });
});
