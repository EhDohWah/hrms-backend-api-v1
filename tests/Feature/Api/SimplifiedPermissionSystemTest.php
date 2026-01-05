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

    // Create test user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Permission System Structure', function () {
    it('creates only read and edit permissions for each permission prefix', function () {
        // Get unique permission prefixes from modules
        $modules = Module::all();
        $permissionPrefixes = $modules->pluck('read_permission')
            ->map(fn ($perm) => str_replace('.read', '', $perm))
            ->unique();

        expect($permissionPrefixes)->not->toBeEmpty();

        foreach ($permissionPrefixes as $prefix) {
            $readPermission = Permission::where('name', "{$prefix}.read")->first();
            $editPermission = Permission::where('name', "{$prefix}.edit")->first();

            expect($readPermission)->not->toBeNull()
                ->and($editPermission)->not->toBeNull();

            // Verify no granular permissions exist for this prefix
            $granularActions = ['create', 'update', 'delete', 'import', 'export'];
            foreach ($granularActions as $action) {
                $granularPerm = Permission::where('name', "{$prefix}.{$action}")->first();
                expect($granularPerm)->toBeNull();
            }
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

        // Employee should have specific read/edit permissions
        $expectedPermissions = [
            'dashboard.read',
            'user.read',
            'user.edit',
            'attendance.read',
            'attendance.edit',
            'travel_request.read',
            'travel_request.edit',
            'leave_request.read',
            'leave_request.edit',
        ];

        foreach ($expectedPermissions as $permission) {
            expect($employeeRole->hasPermissionTo($permission))->toBeTrue();
        }
    });
});

describe('User Permission Assignment', function () {
    it('can assign read permission to user', function () {
        $module = Module::first();
        // Extract permission prefix from read_permission (e.g., 'admin.read' => 'admin')
        $permissionPrefix = str_replace('.read', '', $module->read_permission);
        $readPermission = "{$permissionPrefix}.read";

        $this->user->givePermissionTo($readPermission);

        expect($this->user->hasPermissionTo($readPermission))->toBeTrue()
            ->and($this->user->can($readPermission))->toBeTrue();
    });

    it('can assign edit permission to user', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);
        $editPermission = "{$permissionPrefix}.edit";

        $this->user->givePermissionTo($editPermission);

        expect($this->user->hasPermissionTo($editPermission))->toBeTrue()
            ->and($this->user->can($editPermission))->toBeTrue();
    });

    it('read and edit permissions are independent', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only edit permission
        $this->user->givePermissionTo("{$permissionPrefix}.edit");

        // User should have edit but NOT read
        expect($this->user->can("{$permissionPrefix}.edit"))->toBeTrue()
            ->and($this->user->can("{$permissionPrefix}.read"))->toBeFalse();
    });
});

describe('User Model Helper Methods', function () {
    it('canEditModule returns true when user has edit permission', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        $this->user->givePermissionTo("{$permissionPrefix}.edit");

        expect($this->user->canEditModule($permissionPrefix))->toBeTrue();
    });

    it('canEditModule returns false when user lacks edit permission', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only read permission
        $this->user->givePermissionTo("{$permissionPrefix}.read");

        expect($this->user->canEditModule($permissionPrefix))->toBeFalse();
    });

    it('getModuleAccess returns correct read/edit flags', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give both read and edit permissions
        $this->user->givePermissionTo([
            "{$permissionPrefix}.read",
            "{$permissionPrefix}.edit",
        ]);

        $access = $this->user->getModuleAccess($permissionPrefix);

        expect($access)->toBeArray()
            ->and($access['read'])->toBeTrue()
            ->and($access['edit'])->toBeTrue();
    });

    it('getModuleAccess returns correct flags for read-only', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give only read permission
        $this->user->givePermissionTo("{$permissionPrefix}.read");

        $access = $this->user->getModuleAccess($permissionPrefix);

        expect($access)->toBeArray()
            ->and($access['read'])->toBeTrue()
            ->and($access['edit'])->toBeFalse();
    });
});

describe('API Endpoint - Get My Permissions', function () {
    it('returns permissions in module format', function () {
        $module = Module::first();
        $permissionPrefix = str_replace('.read', '', $module->read_permission);

        // Give permissions
        $this->user->givePermissionTo([
            "{$permissionPrefix}.read",
            "{$permissionPrefix}.edit",
        ]);

        $response = $this->getJson('/api/v1/me/permissions');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $data = $response->json('data');

        // The API uses module->name as the key, not permission prefix
        expect($data)->toBeArray()
            ->and($data[$module->name])->toBeArray()
            ->and($data[$module->name]['read'])->toBeTrue()
            ->and($data[$module->name]['edit'])->toBeTrue()
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
            ->and($data[$module->name]['edit'])->toBeFalse();
    });
});

describe('DynamicModulePermission Middleware', function () {
    it('allows GET requests with read permission', function () {
        // Create a module with a test route
        $module = Module::where('name', 'user')->first();

        if ($module) {
            $this->user->givePermissionTo("{$module->name}.read");

            // Test endpoint that uses GET (list users)
            $response = $this->getJson('/api/v1/users');

            // Should not get 403 Forbidden
            expect($response->status())->not->toBe(403);
        }
    });

    it('blocks POST requests without edit permission', function () {
        $module = Module::where('name', 'user')->first();

        if ($module) {
            // Give only read permission
            $this->user->givePermissionTo("{$module->name}.read");

            // Test endpoint that uses POST (create user)
            $response = $this->postJson('/api/v1/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

            // Should get 403 Forbidden
            $response->assertForbidden();
        }
    });

    it('allows POST requests with edit permission', function () {
        $module = Module::where('name', 'user')->first();

        if ($module) {
            // Give edit permission (and read for potential checks)
            $this->user->givePermissionTo([
                "{$module->name}.read",
                "{$module->name}.edit",
            ]);

            // Test endpoint that uses POST
            $response = $this->postJson('/api/v1/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'role' => 'employee',
            ]);

            // Should not get 403 Forbidden (might get validation error, but not permission error)
            expect($response->status())->not->toBe(403);
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
    it('stores edit permission as single-item array', function () {
        $modules = Module::all();

        foreach ($modules as $module) {
            // Dashboard is read-only exception
            if ($module->name === 'dashboard') {
                expect($module->edit_permissions)->toBeArray()
                    ->and($module->edit_permissions)->toBeEmpty();
            } else {
                $permissionPrefix = str_replace('.read', '', $module->read_permission);

                expect($module->edit_permissions)->toBeArray()
                    ->and($module->edit_permissions)->toHaveCount(1)
                    ->and($module->edit_permissions[0])->toBe("{$permissionPrefix}.edit");
            }
        }
    });
});
