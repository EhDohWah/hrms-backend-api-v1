<?php

use App\Models\Interview;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Interview Permission Middleware', function () {
    beforeEach(function () {
        // Create Module record for dynamic module permission middleware
        Module::create([
            'name' => 'interviews',
            'display_name' => 'Interviews',
            'category' => 'Recruitment',
            'icon' => 'calendar',
            'route' => '/recruitment/interviews-list',
            'read_permission' => 'interviews.read',
            'edit_permissions' => ['interviews.edit'],
            'order' => 20,
            'is_active' => true,
        ]);

        // Create permissions (use firstOrCreate to avoid conflicts)
        Permission::firstOrCreate(['name' => 'interviews.read']);
        Permission::firstOrCreate(['name' => 'interviews.edit']);

        // Create roles (use firstOrCreate to avoid conflicts)
        $this->adminRole = Role::firstOrCreate(['name' => 'admin']);
        $this->hrRole = Role::firstOrCreate(['name' => 'hr']);
        $this->userRole = Role::firstOrCreate(['name' => 'user']);

        // Assign permissions to roles
        // admin: read + edit (full access)
        $this->adminRole->givePermissionTo(['interviews.read', 'interviews.edit']);
        // hr: read + edit (full access including delete via edit permission)
        $this->hrRole->givePermissionTo(['interviews.read', 'interviews.edit']);
        // user: read only
        $this->userRole->givePermissionTo(['interviews.read']);

        // Create users
        $this->adminUser = User::factory()->create();
        $this->hrUser = User::factory()->create();
        $this->regularUser = User::factory()->create();
        $this->unauthorizedUser = User::factory()->create();

        // Assign roles
        $this->adminUser->assignRole('admin');
        $this->hrUser->assignRole('hr');
        $this->regularUser->assignRole('user');
        // unauthorizedUser has no role/permissions
    });

    describe('GET /api/interviews (interviews.read permission)', function () {
        it('allows admin user to list interviews', function () {
            Interview::factory()->count(3)->create();

            $response = $this->actingAs($this->adminUser)->getJson('/api/v1/interviews');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows hr user to list interviews', function () {
            Interview::factory()->count(3)->create();

            $response = $this->actingAs($this->hrUser)->getJson('/api/v1/interviews');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows regular user to list interviews', function () {
            Interview::factory()->count(3)->create();

            $response = $this->actingAs($this->regularUser)->getJson('/api/v1/interviews');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('denies unauthorized user access to list interviews', function () {
            Interview::factory()->count(3)->create();

            $response = $this->actingAs($this->unauthorizedUser)->getJson('/api/v1/interviews');

            $response->assertStatus(403);
        });

        it('denies unauthenticated access to list interviews', function () {
            Interview::factory()->count(3)->create();

            $response = $this->getJson('/api/v1/interviews');

            $response->assertStatus(401);
        });
    });

    describe('GET /api/interviews/{id} (interviews.read permission)', function () {
        it('allows admin user to view specific interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->adminUser)->getJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows hr user to view specific interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->hrUser)->getJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows regular user to view specific interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->regularUser)->getJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('denies unauthorized user access to view specific interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->unauthorizedUser)->getJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(403);
        });
    });

    describe('GET /api/interviews/by-candidate/{candidateName} (interviews.read permission)', function () {
        it('allows admin user to search by candidate name', function () {
            $interview = Interview::factory()->create(['candidate_name' => 'John Doe']);

            $response = $this->actingAs($this->adminUser)->getJson('/api/v1/interviews/by-candidate/John Doe');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows hr user to search by candidate name', function () {
            $interview = Interview::factory()->create(['candidate_name' => 'John Doe']);

            $response = $this->actingAs($this->hrUser)->getJson('/api/v1/interviews/by-candidate/John Doe');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows regular user to search by candidate name', function () {
            $interview = Interview::factory()->create(['candidate_name' => 'John Doe']);

            $response = $this->actingAs($this->regularUser)->getJson('/api/v1/interviews/by-candidate/John Doe');

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('denies unauthorized user access to search by candidate name', function () {
            $interview = Interview::factory()->create(['candidate_name' => 'John Doe']);

            $response = $this->actingAs($this->unauthorizedUser)->getJson('/api/v1/interviews/by-candidate/John Doe');

            $response->assertStatus(403);
        });
    });

    describe('POST /api/interviews (interviews.edit permission)', function () {
        it('allows admin user to create interview', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ];

            $response = $this->actingAs($this->adminUser)->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);
        });

        it('allows hr user to create interview', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ];

            $response = $this->actingAs($this->hrUser)->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);
        });

        it('denies regular user access to create interview', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ];

            $response = $this->actingAs($this->regularUser)->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(403);
        });

        it('denies unauthorized user access to create interview', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ];

            $response = $this->actingAs($this->unauthorizedUser)->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(403);
        });

        it('denies unauthenticated access to create interview', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ];

            $response = $this->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(401);
        });
    });

    describe('PUT /api/interviews/{id} (interviews.edit permission)', function () {
        it('allows admin user to update interview', function () {
            $interview = Interview::factory()->create();
            $updateData = [
                'candidate_name' => 'Updated Name',
                'job_position' => 'Senior Software Engineer',
            ];

            $response = $this->actingAs($this->adminUser)->putJson("/api/v1/interviews/{$interview->id}", $updateData);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows hr user to update interview', function () {
            $interview = Interview::factory()->create();
            $updateData = [
                'candidate_name' => 'Updated Name',
                'job_position' => 'Senior Software Engineer',
            ];

            $response = $this->actingAs($this->hrUser)->putJson("/api/v1/interviews/{$interview->id}", $updateData);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('denies regular user access to update interview', function () {
            $interview = Interview::factory()->create();
            $updateData = [
                'candidate_name' => 'Updated Name',
                'job_position' => 'Senior Software Engineer',
            ];

            $response = $this->actingAs($this->regularUser)->putJson("/api/v1/interviews/{$interview->id}", $updateData);

            $response->assertStatus(403);
        });

        it('denies unauthorized user access to update interview', function () {
            $interview = Interview::factory()->create();
            $updateData = [
                'candidate_name' => 'Updated Name',
                'job_position' => 'Senior Software Engineer',
            ];

            $response = $this->actingAs($this->unauthorizedUser)->putJson("/api/v1/interviews/{$interview->id}", $updateData);

            $response->assertStatus(403);
        });
    });

    describe('DELETE /api/interviews/{id} (interviews.edit permission)', function () {
        it('allows admin user to delete interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->adminUser)->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('allows hr user to delete interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->hrUser)->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('denies regular user access to delete interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->regularUser)->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(403);
        });

        it('denies unauthorized user access to delete interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->unauthorizedUser)->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(403);
        });

        it('denies unauthenticated access to delete interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(401);
        });
    });

    describe('Direct Permission Testing', function () {
        it('verifies admin has all interview permissions', function () {
            expect($this->adminUser->can('interviews.read'))->toBeTrue()
                ->and($this->adminUser->can('interviews.edit'))->toBeTrue();
        });

        it('verifies hr has all interview permissions', function () {
            expect($this->hrUser->can('interviews.read'))->toBeTrue()
                ->and($this->hrUser->can('interviews.edit'))->toBeTrue();
        });

        it('verifies regular user has read-only interview permissions', function () {
            expect($this->regularUser->can('interviews.read'))->toBeTrue()
                ->and($this->regularUser->can('interviews.edit'))->toBeFalse();
        });

        it('verifies unauthorized user has no interview permissions', function () {
            expect($this->unauthorizedUser->can('interviews.read'))->toBeFalse()
                ->and($this->unauthorizedUser->can('interviews.edit'))->toBeFalse();
        });
    });

    describe('Role and Permission Management', function () {
        it('can dynamically assign interview permissions', function () {
            $newUser = User::factory()->create();

            // Initially no permissions
            expect($newUser->can('interviews.read'))->toBeFalse();

            // Give direct permission
            $newUser->givePermissionTo('interviews.read');

            expect($newUser->can('interviews.read'))->toBeTrue();
        });

        it('can revoke interview permissions', function () {
            // Create a new user with direct permission (not via role)
            $tempUser = User::factory()->create();
            $tempUser->givePermissionTo('interviews.read');

            expect($tempUser->can('interviews.read'))->toBeTrue();

            // Now revoke the permission
            $tempUser->revokePermissionTo('interviews.read');
            $tempUser->refresh(); // Refresh to clear cached permissions

            expect($tempUser->can('interviews.read'))->toBeFalse();

            $response = $this->actingAs($tempUser)->getJson('/api/v1/interviews');
            $response->assertStatus(403);
        });

        it('can create custom role with specific interview permissions', function () {
            $customRole = Role::firstOrCreate(['name' => 'interviewer']);
            $customRole->givePermissionTo(['interviews.read', 'interviews.edit']);

            $customUser = User::factory()->create();
            $customUser->assignRole('interviewer');

            expect($customUser->can('interviews.read'))->toBeTrue()
                ->and($customUser->can('interviews.edit'))->toBeTrue();
        });
    });

    describe('Multiple Permissions Scenarios', function () {
        it('handles user with multiple roles', function () {
            $multiRoleUser = User::factory()->create();
            $multiRoleUser->assignRole(['user', 'hr']);

            // Should have the highest permissions from any role
            expect($multiRoleUser->can('interviews.read'))->toBeTrue()
                ->and($multiRoleUser->can('interviews.edit'))->toBeTrue();
        });

        it('handles direct permissions overriding role permissions', function () {
            $specialUser = User::factory()->create();
            $specialUser->assignRole('user'); // Only has read permission
            $specialUser->givePermissionTo('interviews.edit'); // Direct edit permission

            expect($specialUser->can('interviews.read'))->toBeTrue()
                ->and($specialUser->can('interviews.edit'))->toBeTrue();
        });
    });
});
