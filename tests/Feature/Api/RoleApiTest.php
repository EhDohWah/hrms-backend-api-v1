<?php

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Role API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Module::create([
            'name' => 'roles',
            'display_name' => 'Roles',
            'category' => 'User Management',
            'icon' => 'lock',
            'route' => '/admin/roles',
            'read_permission' => 'roles.read',
            'edit_permissions' => ['roles.edit'],
            'order' => 40,
            'is_active' => true,
        ]);

        Permission::firstOrCreate(['name' => 'roles.read']);
        Permission::firstOrCreate(['name' => 'roles.edit']);

        $this->user->givePermissionTo(['roles.read', 'roles.edit']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/admin/roles', function () {
        it('returns roles list', function () {
            Role::create(['name' => 'test-role-1']);
            Role::create(['name' => 'test-role-2']);

            $response = $this->getJson('/api/v1/admin/roles');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/admin/roles/options', function () {
        it('returns role options for dropdowns', function () {
            Role::create(['name' => 'test-role-option']);

            $response = $this->getJson('/api/v1/admin/roles/options');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });
    });

    describe('GET /api/v1/admin/roles/{id}', function () {
        it('returns specific role', function () {
            $role = Role::create(['name' => 'test-viewer']);

            $response = $this->getJson("/api/v1/admin/roles/{$role->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $role->id,
                        'name' => 'test-viewer',
                    ],
                ]);
        });

        it('returns 404 for non-existent role', function () {
            $response = $this->getJson('/api/v1/admin/roles/99999');

            $response->assertStatus(404);
        });
    });

    describe('POST /api/v1/admin/roles', function () {
        it('creates a new role', function () {
            $data = [
                'name' => 'payroll-specialist',
            ];

            $response = $this->postJson('/api/v1/admin/roles', $data);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'payroll-specialist',
                    ],
                ]);

            $this->assertDatabaseHas('roles', ['name' => 'payroll-specialist']);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/admin/roles', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('validates unique name', function () {
            Role::create(['name' => 'existing-role']);

            $response = $this->postJson('/api/v1/admin/roles', [
                'name' => 'existing-role',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('validates name format (lowercase with hyphens)', function () {
            $response = $this->postJson('/api/v1/admin/roles', [
                'name' => 'Invalid Name With Spaces',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('prevents creating protected role names', function () {
            $response = $this->postJson('/api/v1/admin/roles', [
                'name' => 'admin',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });
    });

    describe('PUT /api/v1/admin/roles/{id}', function () {
        it('updates existing role', function () {
            $role = Role::create(['name' => 'old-role-name']);

            $response = $this->putJson("/api/v1/admin/roles/{$role->id}", [
                'name' => 'updated-role-name',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('roles', [
                'id' => $role->id,
                'name' => 'updated-role-name',
            ]);
        });

        it('returns 404 for non-existent role', function () {
            $response = $this->putJson('/api/v1/admin/roles/99999', [
                'name' => 'updated',
            ]);

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/v1/admin/roles/{id}', function () {
        it('deletes existing role', function () {
            $role = Role::create(['name' => 'deletable-role']);

            $response = $this->deleteJson("/api/v1/admin/roles/{$role->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('returns 404 for non-existent role', function () {
            $response = $this->deleteJson('/api/v1/admin/roles/99999');

            $response->assertStatus(404);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/admin/roles');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $response = $this->getJson('/api/v1/admin/roles');

            $response->assertStatus(403);
        });
    });
});
