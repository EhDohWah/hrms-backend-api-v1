<?php

use App\Models\Module;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create test module using new simplified Read/Edit permission model
    $this->module = Module::create([
        'name' => 'test_module',
        'display_name' => 'Test Module',
        'category' => 'Testing',
        'icon' => 'test',
        'route' => '/test',
        'read_permission' => 'test_module.read',
        'edit_permissions' => ['test_module.edit'], // Simplified: single edit permission
        'order' => 1,
        'is_active' => true,
    ]);

    // Create user_management module for testing admin access
    Module::create([
        'name' => 'user_management',
        'display_name' => 'User Management',
        'category' => 'Administration',
        'icon' => 'users',
        'route' => '/user-management',
        'read_permission' => 'user_management.read',
        'edit_permissions' => ['user_management.edit'],
        'order' => 0,
        'is_active' => true,
    ]);

    // Create permissions (simplified Read/Edit model)
    Permission::create(['name' => 'test_module.read']);
    Permission::create(['name' => 'test_module.edit']);
    Permission::create(['name' => 'user_management.read']);
    Permission::create(['name' => 'user_management.edit']);

    // Create test user
    $this->user = User::factory()->create();
});

describe('DynamicModulePermission Middleware', function () {
    test('unauthenticated user receives 401', function () {
        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->get('/api/v1/test-unauth', fn () => response()->json(['success' => true]));

        $response = $this->getJson('/api/v1/test-unauth');

        $response->assertUnauthorized();
    });

    test('user with read permission can access GET endpoints', function () {
        $this->user->givePermissionTo('test_module.read');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->get('/api/v1/test-get', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->getJson('/api/v1/test-get');

        $response->assertSuccessful();
    });

    test('user without read permission cannot access GET endpoints', function () {
        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->get('/api/v1/test-get', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->getJson('/api/v1/test-get');

        $response->assertForbidden();
        $response->assertJson([
            'success' => false,
            'message' => 'You do not have permission to view Test Module records',
        ]);
    });

    test('user with only read permission cannot access POST endpoints', function () {
        $this->user->givePermissionTo('test_module.read');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->post('/api/v1/test-post', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->postJson('/api/v1/test-post', []);

        $response->assertForbidden();
        $response->assertJson([
            'success' => false,
            'message' => 'You do not have permission to create Test Module records',
        ]);
    });

    test('user with edit permission can access POST endpoints', function () {
        $this->user->givePermissionTo('test_module.edit');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->post('/api/v1/test-post', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->postJson('/api/v1/test-post', []);

        $response->assertSuccessful();
    });

    test('user with any edit permission can access PUT endpoints', function () {
        $this->user->givePermissionTo('test_module.edit');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->put('/api/v1/test-put', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->putJson('/api/v1/test-put', []);

        $response->assertSuccessful();
    });

    test('user with any edit permission can access DELETE endpoints', function () {
        $this->user->givePermissionTo('test_module.edit');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->delete('/api/v1/test-delete', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->deleteJson('/api/v1/test-delete');

        $response->assertSuccessful();
    });

    test('returns 404 when module does not exist', function () {
        $this->user->givePermissionTo('test_module.read');

        Route::middleware(['auth:sanctum', 'module.permission:nonexistent_module'])
            ->get('/api/v1/test-nonexistent', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->getJson('/api/v1/test-nonexistent');

        $response->assertNotFound();
        $response->assertJson([
            'success' => false,
            'message' => "Module 'nonexistent_module' not found or inactive",
        ]);
    });

    test('returns 404 when module is inactive', function () {
        $this->module->update(['is_active' => false]);
        $this->user->givePermissionTo('test_module.read');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->get('/api/v1/test-inactive', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->getJson('/api/v1/test-inactive');

        $response->assertNotFound();
    });
});

describe('User Model Helper Methods', function () {
    test('canReadModule returns true when user has read permission', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->canReadModule('test_module'))->toBeTrue();
    });

    test('canReadModule returns false when user lacks read permission', function () {
        expect($this->user->canReadModule('test_module'))->toBeFalse();
    });

    test('canEditModule returns true when user has any edit permission', function () {
        $this->user->givePermissionTo('test_module.edit');

        expect($this->user->canEditModule('test_module'))->toBeTrue();
    });

    test('canEditModule returns false when user lacks edit permissions', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->canEditModule('test_module'))->toBeFalse();
    });

    test('hasReadOnlyAccess returns true when user has read but not edit', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->hasReadOnlyAccess('test_module'))->toBeTrue();
    });

    test('hasReadOnlyAccess returns false when user has both read and edit', function () {
        $this->user->givePermissionTo(['test_module.read', 'test_module.edit']);

        expect($this->user->hasReadOnlyAccess('test_module'))->toBeFalse();
    });

    test('hasFullAccess returns true when user has both read and edit', function () {
        $this->user->givePermissionTo(['test_module.read', 'test_module.edit']);

        expect($this->user->hasFullAccess('test_module'))->toBeTrue();
    });

    test('hasFullAccess returns false when user has only read', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->hasFullAccess('test_module'))->toBeFalse();
    });

    test('getModuleAccess returns correct access levels', function () {
        $this->user->givePermissionTo('test_module.read');

        $access = $this->user->getModuleAccess('test_module');

        expect($access)->toEqual([
            'read' => true,
            'edit' => false,
        ]);
    });

    test('getAccessibleModules returns only modules user has access to', function () {
        $this->user->givePermissionTo('test_module.read');

        $accessibleModules = $this->user->getAccessibleModules();

        expect($accessibleModules)->toHaveKey('test_module');
        expect($accessibleModules['test_module'])->toHaveKeys(['read', 'edit', 'display_name']);
    });
});

describe('Module Model Helper Methods', function () {
    test('userCanRead returns true when user has read permission', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->module->userCanRead($this->user))->toBeTrue();
    });

    test('userCanEdit returns true when user has edit permission', function () {
        $this->user->givePermissionTo('test_module.edit');

        expect($this->module->userCanEdit($this->user))->toBeTrue();
    });

    test('getUserAccess returns correct access levels', function () {
        $this->user->givePermissionTo(['test_module.read', 'test_module.edit']);

        $access = $this->module->getUserAccess($this->user);

        expect($access)->toEqual([
            'read' => true,
            'edit' => true,
        ]);
    });

    test('getPermissionForAction returns correct permission for read actions', function () {
        expect($this->module->getPermissionForAction('read'))->toBe('test_module.read');
        expect($this->module->getPermissionForAction('view'))->toBe('test_module.read');
        expect($this->module->getPermissionForAction('index'))->toBe('test_module.read');
    });

    test('getPermissionForAction returns correct permission for edit actions', function () {
        // With simplified model, the edit_permissions contains test_module.edit
        // The getPermissionForAction looks for exact match in edit_permissions array
        expect($this->module->getPermissionForAction('edit'))->toBe('test_module.edit');
    });

    test('getEditActions returns all edit action names', function () {
        $actions = $this->module->getEditActions();

        // With simplified model, should return the single edit permission
        expect($actions)->toContain('edit');
    });

    test('getAllPermissions returns read and edit permissions', function () {
        $permissions = $this->module->getAllPermissions();

        expect($permissions)->toContain(
            'test_module.read',
            'test_module.edit'
        );
    });

    test('accessibleBy scope returns modules user can access', function () {
        $this->user->givePermissionTo('test_module.read');

        $accessible = Module::accessibleBy($this->user)->get();

        expect($accessible->pluck('name')->toArray())->toContain('test_module');
    });
});

describe('UserPermissionController', function () {
    test('getUserPermissions returns correct permission structure', function () {
        $this->user->givePermissionTo(['test_module.read', 'test_module.edit']);
        $admin = User::factory()->create();
        // Need both read (for GET) and edit permissions
        $admin->givePermissionTo(['user_management.read', 'user_management.edit']);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/user-permissions/{$this->user->id}");

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => ['id', 'name', 'email', 'roles'],
                'modules' => [
                    'test_module' => ['read', 'edit', 'display_name', 'category', 'icon', 'order'],
                ],
            ],
        ]);
    });

    test('updateUserPermissions correctly assigns permissions based on checkboxes', function () {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['user_management.read', 'user_management.edit']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/user-permissions/{$this->user->id}", [
            'modules' => [
                'test_module' => [
                    'read' => true,
                    'edit' => false,
                ],
            ],
        ]);

        $response->assertSuccessful();

        $this->user->refresh();
        expect($this->user->can('test_module.read'))->toBeTrue();
        expect($this->user->can('test_module.edit'))->toBeFalse();
    });

    test('updateUserPermissions grants full permissions when both checkboxes checked', function () {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['user_management.read', 'user_management.edit']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/user-permissions/{$this->user->id}", [
            'modules' => [
                'test_module' => [
                    'read' => true,
                    'edit' => true,
                ],
            ],
        ]);

        $response->assertSuccessful();

        $this->user->refresh();
        expect($this->user->can('test_module.read'))->toBeTrue();
        expect($this->user->can('test_module.edit'))->toBeTrue();
    });

    test('summary endpoint returns correct statistics', function () {
        $this->user->givePermissionTo('test_module.read');
        $admin = User::factory()->create();
        // Need read permission for GET request
        $admin->givePermissionTo(['user_management.read', 'user_management.edit']);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/user-permissions/{$this->user->id}/summary");

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'summary' => ['total_modules', 'full_access', 'read_only', 'no_access', 'total_permissions'],
            ],
        ]);
    });
});
