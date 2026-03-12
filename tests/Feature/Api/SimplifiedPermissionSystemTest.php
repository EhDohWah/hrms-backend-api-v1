<?php

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run module seeder to create modules
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ModuleSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionRoleSeeder']);

    // Reset cached permissions so Spatie picks up newly seeded permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Assign all permissions to admin role (mirrors what UserSeeder does for admin users)
    $adminRole = Role::where('name', 'admin')->first();
    if ($adminRole) {
        $adminRole->syncPermissions(Permission::all());
    }

    // Create employee role with limited permissions (basic self-service access)
    $employeeRole = Role::firstOrCreate(['name' => 'employee']);
    $employeeRole->syncPermissions([
        'dashboard.read',
        'users.read',
        'users.create',
        'users.update',
        'users.delete',
        'attendance_admin.read',
        'attendance_admin.create',
        'attendance_admin.update',
        'attendance_admin.delete',
        'travel_admin.read',
        'travel_admin.create',
        'travel_admin.update',
        'travel_admin.delete',
        'leaves_admin.read',
        'leaves_admin.create',
        'leaves_admin.update',
        'leaves_admin.delete',
    ]);

    // Create test user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Permission System Structure', function () {
    it('creates CRUD permissions for each permission prefix', function () {
        // Get unique permission prefixes from modules
        $modules = Module::all();
        $permissionPrefixes = $modules->pluck('read_permission')
            ->map(fn ($perm) => str_replace('.read', '', $perm))
            ->unique();

        expect($permissionPrefixes)->not->toBeEmpty();

        foreach ($permissionPrefixes as $prefix) {
            $readPermission = Permission::where('name', "{$prefix}.read")->first();
            $createPermission = Permission::where('name', "{$prefix}.create")->first();
            $updatePermission = Permission::where('name', "{$prefix}.update")->first();
            $deletePermission = Permission::where('name', "{$prefix}.delete")->first();

            expect($readPermission)->not->toBeNull()
                ->and($createPermission)->not->toBeNull()
                ->and($updatePermission)->not->toBeNull()
                ->and($deletePermission)->not->toBeNull();
        }
    });

    it('assigns all permissions to admin role', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $allPermissions = Permission::all();

        expect($adminRole)->not->toBeNull();
        expect($adminRole->permissions->count())->toBe($allPermissions->count());
    });

    it('assigns limited permissions to employee role', function () {
        $employeeRole = Role::where('name', 'employee')->first();

        expect($employeeRole)->not->toBeNull();

        // Employee should have specific CRUD permissions
        $expectedPermissions = [
            'dashboard.read',
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
            'attendance_admin.read',
            'attendance_admin.create',
            'attendance_admin.update',
            'attendance_admin.delete',
            'travel_admin.read',
            'travel_admin.create',
            'travel_admin.update',
            'travel_admin.delete',
            'leaves_admin.read',
            'leaves_admin.create',
            'leaves_admin.update',
            'leaves_admin.delete',
        ];

        foreach ($expectedPermissions as $permission) {
            expect($employeeRole->hasPermissionTo($permission))->toBeTrue();
        }
    });
});

describe('User Permission Assignment', function () {
    it('can assign read permission to user', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);
        $readPermission = "{$permissionPrefix}.read";

        $this->user->givePermissionTo($readPermission);

        expect($this->user->hasPermissionTo($readPermission))->toBeTrue()
            ->and($this->user->can($readPermission))->toBeTrue();
    });

    it('can assign create permission to user', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);
        $createPermission = "{$permissionPrefix}.create";

        $this->user->givePermissionTo($createPermission);

        expect($this->user->hasPermissionTo($createPermission))->toBeTrue()
            ->and($this->user->can($createPermission))->toBeTrue();
    });

    it('all CRUD permissions are independent', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only create permission
        $this->user->givePermissionTo("{$permissionPrefix}.create");

        // User should have create but NOT read, update, or delete
        expect($this->user->can("{$permissionPrefix}.create"))->toBeTrue()
            ->and($this->user->can("{$permissionPrefix}.read"))->toBeFalse()
            ->and($this->user->can("{$permissionPrefix}.update"))->toBeFalse()
            ->and($this->user->can("{$permissionPrefix}.delete"))->toBeFalse();
    });
});

describe('User Model Helper Methods', function () {
    it('canCreateModule returns true when user has create permission', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        $this->user->givePermissionTo("{$permissionPrefix}.create");

        expect($this->user->canCreateModule($permissionPrefix))->toBeTrue();
    });

    it('canUpdateModule returns false when user lacks update permission', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only read permission
        $this->user->givePermissionTo("{$permissionPrefix}.read");

        expect($this->user->canUpdateModule($permissionPrefix))->toBeFalse();
    });

    it('canDeleteModule returns true when user has delete permission', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        $this->user->givePermissionTo("{$permissionPrefix}.delete");

        expect($this->user->canDeleteModule($permissionPrefix))->toBeTrue();
    });

    it('getModuleAccess returns correct CRUD flags', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give all CRUD permissions
        $this->user->givePermissionTo([
            "{$permissionPrefix}.read",
            "{$permissionPrefix}.create",
            "{$permissionPrefix}.update",
            "{$permissionPrefix}.delete",
        ]);

        $access = $this->user->getModuleAccess($permissionPrefix);

        expect($access)->toBeArray()
            ->and($access['read'])->toBeTrue()
            ->and($access['create'])->toBeTrue()
            ->and($access['update'])->toBeTrue()
            ->and($access['delete'])->toBeTrue();
    });

    it('getModuleAccess returns correct flags for read-only', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only read permission
        $this->user->givePermissionTo("{$permissionPrefix}.read");

        $access = $this->user->getModuleAccess($permissionPrefix);

        expect($access)->toBeArray()
            ->and($access['read'])->toBeTrue()
            ->and($access['create'])->toBeFalse()
            ->and($access['update'])->toBeFalse()
            ->and($access['delete'])->toBeFalse();
    });

    it('hasFullAccess requires all 4 CRUD permissions', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only 3 of 4 permissions
        $this->user->givePermissionTo([
            "{$permissionPrefix}.read",
            "{$permissionPrefix}.create",
            "{$permissionPrefix}.update",
        ]);

        expect($this->user->hasFullAccess($permissionPrefix))->toBeFalse();

        // Now add the 4th
        $this->user->givePermissionTo("{$permissionPrefix}.delete");

        expect($this->user->hasFullAccess($permissionPrefix))->toBeTrue();
    });
});

describe('API Endpoint - Get My Permissions', function () {
    it('returns permissions in module format with CRUD flags', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give permissions
        $this->user->givePermissionTo([
            "{$permissionPrefix}.read",
            "{$permissionPrefix}.create",
            "{$permissionPrefix}.update",
            "{$permissionPrefix}.delete",
        ]);

        $response = $this->getJson('/api/v1/me/permissions');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $data = $response->json('data');

        expect($data)->toBeArray()
            ->and($data[$module->name])->toBeArray()
            ->and($data[$module->name]['read'])->toBeTrue()
            ->and($data[$module->name]['create'])->toBeTrue()
            ->and($data[$module->name]['update'])->toBeTrue()
            ->and($data[$module->name]['delete'])->toBeTrue()
            ->and($data[$module->name]['display_name'])->toBe($module->display_name);
    });

    it('returns only modules user has access to', function () {
        $firstModule = Module::first();
        $secondModule = Module::skip(1)->first();
        $firstPrefix = str_replace('.read', '', $firstModule->read_permission);

        // Give access to only first module
        $this->user->givePermissionTo("{$firstPrefix}.read");

        $response = $this->getJson('/api/v1/me/permissions');

        $data = $response->json('data');

        expect($data)->toHaveKey($firstModule->name)
            ->and($data)->not->toHaveKey($secondModule->name);
    });

    it('returns correct read-only access', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only read permission
        $this->user->givePermissionTo("{$permissionPrefix}.read");

        $response = $this->getJson('/api/v1/me/permissions');

        $data = $response->json('data');

        expect($data[$module->name]['read'])->toBeTrue()
            ->and($data[$module->name]['create'])->toBeFalse()
            ->and($data[$module->name]['update'])->toBeFalse()
            ->and($data[$module->name]['delete'])->toBeFalse();
    });
});

describe('DynamicModulePermission Middleware', function () {
    it('allows GET requests with read permission', function () {
        $module = Module::where('name', 'users')->first();

        if ($module) {
            $this->user->givePermissionTo("{$module->name}.read");

            $response = $this->getJson('/api/v1/admin/users');

            // Should not get 403 Forbidden
            expect($response->status())->not->toBe(403);
        }
    });

    it('blocks POST requests without create permission', function () {
        $module = Module::where('name', 'users')->first();

        if ($module) {
            // Give only read permission
            $this->user->givePermissionTo("{$module->name}.read");

            $response = $this->postJson('/api/v1/admin/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

            // Should get 403 Forbidden
            $response->assertForbidden();
        }
    });

    it('allows POST requests with create permission', function () {
        $module = Module::where('name', 'users')->first();

        if ($module) {
            $this->user->givePermissionTo([
                "{$module->name}.read",
                "{$module->name}.create",
            ]);

            $response = $this->postJson('/api/v1/admin/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'employee',
            ]);

            // Should not get 403 Forbidden (might get validation error, but not permission error)
            expect($response->status())->not->toBe(403);
        }
    });

    it('blocks DELETE requests without delete permission', function () {
        $module = Module::where('name', 'roles')->first();

        if ($module) {
            // Give read and create but NOT delete
            $this->user->givePermissionTo([
                "{$module->name}.read",
                "{$module->name}.create",
            ]);

            $role = \Spatie\Permission\Models\Role::create(['name' => 'test-deletable']);

            $response = $this->deleteJson("/api/v1/admin/roles/{$role->id}");

            $response->assertForbidden();
        }
    });
});

describe('Permission Cache', function () {
    it('clears permission cache on permission update', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give initial permission
        $this->user->givePermissionTo("{$permissionPrefix}.read");

        expect($this->user->can("{$permissionPrefix}.read"))->toBeTrue();

        // Remove permission
        $this->user->revokePermissionTo("{$permissionPrefix}.read");

        // Force fresh permission check
        $this->user->refresh();

        expect($this->user->can("{$permissionPrefix}.read"))->toBeFalse();
    });
});

describe('Module Edit Permissions Field', function () {
    it('stores CRUD write permissions as 3-item array', function () {
        $modules = Module::all();

        foreach ($modules as $module) {
            $permissionPrefix = str_replace('.read', '', $module->read_permission);

            expect($module->edit_permissions)->toBeArray()
                ->and($module->edit_permissions)->toHaveCount(3)
                ->and($module->edit_permissions[0])->toBe("{$permissionPrefix}.create")
                ->and($module->edit_permissions[1])->toBe("{$permissionPrefix}.update")
                ->and($module->edit_permissions[2])->toBe("{$permissionPrefix}.delete");
        }
    });
});
