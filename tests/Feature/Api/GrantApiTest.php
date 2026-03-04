<?php

use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create and authenticate user
    $this->user = User::factory()->create();

    // Create permissions
    $permissions = [
        'grants_list.read',
        'grants_list.edit',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $this->user->givePermissionTo($permissions);
    Sanctum::actingAs($this->user);
});

describe('Grant Bulk Delete', function () {
    it('can delete multiple grants', function () {
        // Create test grants
        $grants = Grant::factory()->count(3)->create();
        $grantIds = $grants->pluck('id')->toArray();

        // Delete the grants
        $response = $this->deleteJson('/api/v1/grants/batch', [
            'ids' => $grantIds,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['succeeded', 'failed']);

        // Verify grants are deleted
        foreach ($grantIds as $id) {
            expect(Grant::find($id))->toBeNull();
        }
    });

    it('soft deletes grants and preserves grant items when deleting grants in batch', function () {
        // Create test grant with items
        $grant = Grant::factory()->create();
        $grantItems = GrantItem::factory()
            ->count(3)
            ->for($grant)
            ->create();

        $grantItemIds = $grantItems->pluck('id')->toArray();

        // Delete the grant (soft delete)
        $response = $this->deleteJson('/api/v1/grants/batch', [
            'ids' => [$grant->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['succeeded', 'failed']);

        // Verify grant is soft-deleted (not visible via normal find)
        expect(Grant::find($grant->id))->toBeNull();

        // Verify grant still exists in database (soft-deleted)
        expect(Grant::withTrashed()->find($grant->id))->not->toBeNull();

        // Grant items are preserved after soft delete (they remain for data integrity)
        foreach ($grantItemIds as $id) {
            expect(GrantItem::find($id))->not->toBeNull();
        }
    });

    it('returns validation error when ids array is empty', function () {
        $response = $this->deleteJson('/api/v1/grants/batch', [
            'ids' => [],
        ]);

        $response->assertStatus(422);
    });

    it('returns validation error when ids are not provided', function () {
        $response = $this->deleteJson('/api/v1/grants/batch', []);

        $response->assertStatus(422);
    });

    it('returns partial failure when non-existent ids are provided', function () {
        $response = $this->deleteJson('/api/v1/grants/batch', [
            'ids' => [99999, 99998],
        ]);

        $response->assertStatus(207)
            ->assertJson([
                'success' => false,
            ]);
    });

    it('requires grants_list.edit permission', function () {
        // Create a user without edit permission
        $readOnlyUser = User::factory()->create();
        Permission::firstOrCreate(['name' => 'grants_list.read']);
        $readOnlyUser->givePermissionTo('grants_list.read');
        Sanctum::actingAs($readOnlyUser);

        $grant = Grant::factory()->create();

        $response = $this->deleteJson('/api/v1/grants/batch', [
            'ids' => [$grant->id],
        ]);

        $response->assertStatus(403);
    });
});

describe('Grant List', function () {
    it('can list grants with pagination', function () {
        Grant::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/grants?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    });

});

describe('Single Grant Delete', function () {
    it('can delete a single grant', function () {
        $grant = Grant::factory()->create();

        $response = $this->deleteJson("/api/v1/grants/{$grant->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        expect(Grant::find($grant->id))->toBeNull();
    });
});
