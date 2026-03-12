<?php

use App\Models\Module;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Seed modules and permissions for testing
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ModuleSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionRoleSeeder']);

    // Reset cached permissions so Spatie picks up newly seeded permissions
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // Create a test user with admin permissions
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('users.read');
});

it('returns all active modules', function () {
    $totalActive = Module::active()->count();
    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules?per_page='.$totalActive);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'display_name',
                    'description',
                    'icon',
                    'category',
                    'route',
                    'read_permission',
                    'edit_permissions',
                    'order',
                    'is_active',
                ],
            ],
            'meta',
        ])
        ->assertJson([
            'success' => true,
        ]);

    expect($response->json('data'))->toHaveCount($totalActive);
});

it('returns modules in hierarchical tree structure', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules/hierarchical');

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);
});

it('returns modules grouped by category', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules/by-category');

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);

    $categories = $response->json('data');
    expect($categories)->toBeArray();
    expect($categories)->toHaveKeys(['Grants', 'Recruitment', 'Employee', 'User Management']);
});

it('returns a single module by ID', function () {
    $module = Module::first();

    $response = $this->actingAs($this->user)->getJson("/api/v1/admin/modules/{$module->id}");

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $module->id,
                'name' => $module->name,
                'display_name' => $module->display_name,
            ],
        ]);
});

it('returns 404 when module not found', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules/99999');

    $response->assertNotFound();
});

it('returns all unique permissions from modules', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules/permissions');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'data',
        ])
        ->assertJson([
            'success' => true,
        ]);

    $permissions = $response->json('data');
    expect($permissions)->toBeArray();
    expect($permissions)->not->toBeEmpty();
    expect($permissions)->toContain('users.read', 'users.create', 'employees.read', 'employees.create');
});

it('requires authentication to access modules', function () {
    $response = $this->getJson('/api/v1/admin/modules');

    $response->assertUnauthorized();
});

it('returns modules ordered correctly', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules?per_page=100');

    $response->assertSuccessful();

    $modules = $response->json('data');
    $orders = array_column($modules, 'order');

    // Verify modules are sorted by order
    $sortedOrders = $orders;
    sort($sortedOrders);

    expect($orders)->toBe($sortedOrders);
});

it('only returns active modules', function () {
    // Create an inactive module
    Module::factory()->create([
        'name' => 'inactive_test_module',
        'display_name' => 'Inactive Test Module',
        'is_active' => false,
        'read_permission' => 'test.read',
        'edit_permissions' => ['test.create', 'test.update'],
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules?per_page=100');

    $response->assertSuccessful();

    $modules = collect($response->json('data'));
    expect($modules->where('name', 'inactive_test_module'))->toBeEmpty();
});

it('includes edit permissions as array', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/admin/modules?per_page=100');

    $response->assertSuccessful();

    $module = collect($response->json('data'))->first();
    expect($module['edit_permissions'])->toBeArray();
});
