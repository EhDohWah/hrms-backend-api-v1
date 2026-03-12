<?php

use App\Models\Module;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Create test module using CRUD permission model
    $this->module = Module::create([
        'name' => 'test_module',
        'display_name' => 'Test Module',
        'category' => 'Testing',
        'icon' => 'test',
        'route' => '/test',
        'read_permission' => 'test_module.read',
        'edit_permissions' => ['test_module.create', 'test_module.update', 'test_module.delete'],
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
        'edit_permissions' => ['user_management.create', 'user_management.update', 'user_management.delete'],
        'order' => 0,
        'is_active' => true,
    ]);

    // Create 'users' module — required by admin routes that use module.permission:users middleware
    Module::create([
        'name' => 'users',
        'display_name' => 'Users',
        'category' => 'User Management',
        'icon' => 'users',
        'route' => '/user-management/users',
        'read_permission' => 'users.read',
        'edit_permissions' => ['users.create', 'users.update', 'users.delete'],
        'order' => 130,
        'is_active' => true,
    ]);

    // Create CRUD permissions for each module
    foreach (['test_module', 'user_management', 'users'] as $module) {
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}"]);
        }
    }

    // Reset cached permissions so Spatie picks up newly created permissions
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
            'message' => 'You do not have permission to create new Test Module records',
        ]);
    });

    test('user with create permission can access POST endpoints', function () {
        $this->user->givePermissionTo('test_module.create');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->post('/api/v1/test-post', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->postJson('/api/v1/test-post', []);

        $response->assertSuccessful();
    });

    test('user with only create permission cannot access PUT endpoints', function () {
        $this->user->givePermissionTo('test_module.create');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->put('/api/v1/test-put', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->putJson('/api/v1/test-put', []);

        $response->assertForbidden();
    });

    test('user with update permission can access PUT endpoints', function () {
        $this->user->givePermissionTo('test_module.update');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->put('/api/v1/test-put', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->putJson('/api/v1/test-put', []);

        $response->assertSuccessful();
    });

    test('user with update permission can access PATCH endpoints', function () {
        $this->user->givePermissionTo('test_module.update');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->patch('/api/v1/test-patch', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->patchJson('/api/v1/test-patch', []);

        $response->assertSuccessful();
    });

    test('user with only update permission cannot access DELETE endpoints', function () {
        $this->user->givePermissionTo('test_module.update');

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->delete('/api/v1/test-delete', fn () => response()->json(['success' => true]));

        $response = $this->actingAs($this->user)->deleteJson('/api/v1/test-delete');

        $response->assertForbidden();
    });

    test('user with delete permission can access DELETE endpoints', function () {
        $this->user->givePermissionTo('test_module.delete');

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

    test('each CRUD permission is enforced independently', function () {
        // Give only create and delete — not read or update
        $this->user->givePermissionTo(['test_module.create', 'test_module.delete']);

        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->get('/api/v1/test-crud-get', fn () => response()->json(['success' => true]));
        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->post('/api/v1/test-crud-post', fn () => response()->json(['success' => true]));
        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->put('/api/v1/test-crud-put', fn () => response()->json(['success' => true]));
        Route::middleware(['auth:sanctum', 'module.permission:test_module'])
            ->delete('/api/v1/test-crud-delete', fn () => response()->json(['success' => true]));

        // GET should be forbidden (no read)
        $this->actingAs($this->user)->getJson('/api/v1/test-crud-get')->assertForbidden();
        // POST should succeed (has create)
        $this->actingAs($this->user)->postJson('/api/v1/test-crud-post', [])->assertSuccessful();
        // PUT should be forbidden (no update)
        $this->actingAs($this->user)->putJson('/api/v1/test-crud-put', [])->assertForbidden();
        // DELETE should succeed (has delete)
        $this->actingAs($this->user)->deleteJson('/api/v1/test-crud-delete')->assertSuccessful();
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

    test('canCreateModule returns true when user has create permission', function () {
        $this->user->givePermissionTo('test_module.create');

        expect($this->user->canCreateModule('test_module'))->toBeTrue();
    });

    test('canCreateModule returns false when user lacks create permission', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->canCreateModule('test_module'))->toBeFalse();
    });

    test('canUpdateModule returns true when user has update permission', function () {
        $this->user->givePermissionTo('test_module.update');

        expect($this->user->canUpdateModule('test_module'))->toBeTrue();
    });

    test('canUpdateModule returns false when user lacks update permission', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->canUpdateModule('test_module'))->toBeFalse();
    });

    test('canDeleteModule returns true when user has delete permission', function () {
        $this->user->givePermissionTo('test_module.delete');

        expect($this->user->canDeleteModule('test_module'))->toBeTrue();
    });

    test('canDeleteModule returns false when user lacks delete permission', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->canDeleteModule('test_module'))->toBeFalse();
    });

    test('hasReadOnlyAccess returns true when user has read but no write permissions', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->user->hasReadOnlyAccess('test_module'))->toBeTrue();
    });

    test('hasReadOnlyAccess returns false when user has any write permission', function () {
        $this->user->givePermissionTo(['test_module.read', 'test_module.create']);

        expect($this->user->hasReadOnlyAccess('test_module'))->toBeFalse();
    });

    test('hasFullAccess returns true when user has all CRUD permissions', function () {
        $this->user->givePermissionTo([
            'test_module.read',
            'test_module.create',
            'test_module.update',
            'test_module.delete',
        ]);

        expect($this->user->hasFullAccess('test_module'))->toBeTrue();
    });

    test('hasFullAccess returns false when user is missing any permission', function () {
        $this->user->givePermissionTo([
            'test_module.read',
            'test_module.create',
            'test_module.update',
        ]);

        expect($this->user->hasFullAccess('test_module'))->toBeFalse();
    });

    test('getModuleAccess returns correct CRUD access levels', function () {
        $this->user->givePermissionTo(['test_module.read', 'test_module.update']);

        $access = $this->user->getModuleAccess('test_module');

        expect($access)->toEqual([
            'read' => true,
            'create' => false,
            'update' => true,
            'delete' => false,
        ]);
    });

    test('getAccessibleModules returns only modules user has access to', function () {
        $this->user->givePermissionTo('test_module.read');

        $accessibleModules = $this->user->getAccessibleModules();

        expect($accessibleModules)->toHaveKey('test_module');
        expect($accessibleModules['test_module'])->toHaveKeys(['read', 'create', 'update', 'delete', 'display_name']);
    });

    test('getAccessibleModules excludes modules user has no access to', function () {
        $this->user->givePermissionTo('test_module.read');

        $accessibleModules = $this->user->getAccessibleModules();

        expect($accessibleModules)->toHaveKey('test_module');
        expect($accessibleModules)->not->toHaveKey('user_management');
    });
});

describe('Module Model Helper Methods', function () {
    test('userCanRead returns true when user has read permission', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->module->userCanRead($this->user))->toBeTrue();
    });

    test('userCanWrite returns true when user has any write permission', function () {
        $this->user->givePermissionTo('test_module.create');

        expect($this->module->userCanWrite($this->user))->toBeTrue();
    });

    test('userCanWrite returns false when user has only read permission', function () {
        $this->user->givePermissionTo('test_module.read');

        expect($this->module->userCanWrite($this->user))->toBeFalse();
    });

    test('getUserAccess returns correct CRUD access levels', function () {
        $this->user->givePermissionTo(['test_module.read', 'test_module.create', 'test_module.delete']);

        $access = $this->module->getUserAccess($this->user);

        expect($access)->toEqual([
            'read' => true,
            'create' => true,
            'update' => false,
            'delete' => true,
        ]);
    });

    test('getPermissionForAction returns correct permission for read actions', function () {
        expect($this->module->getPermissionForAction('read'))->toBe('test_module.read');
        expect($this->module->getPermissionForAction('view'))->toBe('test_module.read');
        expect($this->module->getPermissionForAction('index'))->toBe('test_module.read');
    });

    test('getPermissionForAction returns correct permission for CRUD actions', function () {
        expect($this->module->getPermissionForAction('create'))->toBe('test_module.create');
        expect($this->module->getPermissionForAction('update'))->toBe('test_module.update');
        expect($this->module->getPermissionForAction('delete'))->toBe('test_module.delete');
    });

    test('getEditActions returns all write action names', function () {
        $actions = $this->module->getEditActions();

        expect($actions)->toContain('create', 'update', 'delete');
        expect($actions)->toHaveCount(3);
    });

    test('getAllPermissions returns all CRUD permissions', function () {
        $permissions = $this->module->getAllPermissions();

        expect($permissions)->toContain(
            'test_module.read',
            'test_module.create',
            'test_module.update',
            'test_module.delete'
        );
        expect($permissions)->toHaveCount(4);
    });

    test('accessibleBy scope returns modules user can access', function () {
        $this->user->givePermissionTo('test_module.read');

        $accessible = Module::accessibleBy($this->user)->get();

        expect($accessible->pluck('name')->toArray())->toContain('test_module');
    });
});

describe('UserPermissionController', function () {
    test('getUserPermissions returns correct CRUD permission structure', function () {
        $this->user->givePermissionTo([
            'test_module.read',
            'test_module.create',
            'test_module.update',
            'test_module.delete',
        ]);
        $admin = User::factory()->create();
        $admin->givePermissionTo(['users.read', 'users.create', 'users.update', 'users.delete']);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/user-permissions/{$this->user->id}");

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => ['id', 'name', 'email', 'roles'],
                'modules' => [
                    'test_module' => ['read', 'create', 'update', 'delete', 'display_name', 'category', 'icon', 'order'],
                ],
            ],
        ]);
    });

    test('updateUserPermissions correctly assigns granular CRUD permissions', function () {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['users.read', 'users.create', 'users.update', 'users.delete']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/user-permissions/{$this->user->id}", [
            'modules' => [
                'test_module' => [
                    'read' => true,
                    'create' => true,
                    'update' => false,
                    'delete' => false,
                ],
            ],
        ]);

        $response->assertSuccessful();

        $this->user->refresh();
        expect($this->user->can('test_module.read'))->toBeTrue();
        expect($this->user->can('test_module.create'))->toBeTrue();
        expect($this->user->can('test_module.update'))->toBeFalse();
        expect($this->user->can('test_module.delete'))->toBeFalse();
    });

    test('updateUserPermissions grants full CRUD permissions when all checkboxes checked', function () {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['users.read', 'users.create', 'users.update', 'users.delete']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/user-permissions/{$this->user->id}", [
            'modules' => [
                'test_module' => [
                    'read' => true,
                    'create' => true,
                    'update' => true,
                    'delete' => true,
                ],
            ],
        ]);

        $response->assertSuccessful();

        $this->user->refresh();
        expect($this->user->can('test_module.read'))->toBeTrue();
        expect($this->user->can('test_module.create'))->toBeTrue();
        expect($this->user->can('test_module.update'))->toBeTrue();
        expect($this->user->can('test_module.delete'))->toBeTrue();
    });

    test('summary endpoint returns correct statistics', function () {
        $this->user->givePermissionTo('test_module.read');
        $admin = User::factory()->create();
        $admin->givePermissionTo(['users.read', 'users.create', 'users.update', 'users.delete']);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/user-permissions/{$this->user->id}/summary");

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'summary' => ['total_modules', 'full_access', 'partial_access', 'read_only', 'no_access', 'total_permissions'],
            ],
        ]);
    });
});
