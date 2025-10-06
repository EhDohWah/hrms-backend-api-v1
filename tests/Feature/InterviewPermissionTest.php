<?php

use App\Models\Interview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Interview Permission Middleware', function () {
    beforeEach(function () {
        // Create permissions (use firstOrCreate to avoid conflicts)
        Permission::firstOrCreate(['name' => 'interview.read']);
        Permission::firstOrCreate(['name' => 'interview.create']);
        Permission::firstOrCreate(['name' => 'interview.update']);
        Permission::firstOrCreate(['name' => 'interview.delete']);

        // Create roles (use firstOrCreate to avoid conflicts)
        $this->adminRole = Role::firstOrCreate(['name' => 'admin']);
        $this->hrRole = Role::firstOrCreate(['name' => 'hr']);
        $this->userRole = Role::firstOrCreate(['name' => 'user']);

        // Assign permissions to roles
        $this->adminRole->givePermissionTo(['interview.read', 'interview.create', 'interview.update', 'interview.delete']);
        $this->hrRole->givePermissionTo(['interview.read', 'interview.create', 'interview.update']);
        $this->userRole->givePermissionTo(['interview.read']);

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

    describe('GET /api/interviews (interview.read permission)', function () {
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

    describe('GET /api/interviews/{id} (interview.read permission)', function () {
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

    describe('GET /api/interviews/by-candidate/{candidateName} (interview.read permission)', function () {
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

    describe('POST /api/interviews (interview.create permission)', function () {
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

    describe('PUT /api/interviews/{id} (interview.update permission)', function () {
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

    describe('DELETE /api/interviews/{id} (interview.delete permission)', function () {
        it('allows admin user to delete interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->adminUser)->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('denies hr user access to delete interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->actingAs($this->hrUser)->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(403);
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
            expect($this->adminUser->can('interview.read'))->toBeTrue()
                ->and($this->adminUser->can('interview.create'))->toBeTrue()
                ->and($this->adminUser->can('interview.update'))->toBeTrue()
                ->and($this->adminUser->can('interview.delete'))->toBeTrue();
        });

        it('verifies hr has limited interview permissions', function () {
            expect($this->hrUser->can('interview.read'))->toBeTrue()
                ->and($this->hrUser->can('interview.create'))->toBeTrue()
                ->and($this->hrUser->can('interview.update'))->toBeTrue()
                ->and($this->hrUser->can('interview.delete'))->toBeFalse();
        });

        it('verifies regular user has read-only interview permissions', function () {
            expect($this->regularUser->can('interview.read'))->toBeTrue()
                ->and($this->regularUser->can('interview.create'))->toBeFalse()
                ->and($this->regularUser->can('interview.update'))->toBeFalse()
                ->and($this->regularUser->can('interview.delete'))->toBeFalse();
        });

        it('verifies unauthorized user has no interview permissions', function () {
            expect($this->unauthorizedUser->can('interview.read'))->toBeFalse()
                ->and($this->unauthorizedUser->can('interview.create'))->toBeFalse()
                ->and($this->unauthorizedUser->can('interview.update'))->toBeFalse()
                ->and($this->unauthorizedUser->can('interview.delete'))->toBeFalse();
        });
    });

    describe('Role and Permission Management', function () {
        it('can dynamically assign interview permissions', function () {
            $newUser = User::factory()->create();

            // Initially no permissions
            expect($newUser->can('interview.read'))->toBeFalse();

            // Give direct permission
            $newUser->givePermissionTo('interview.read');

            expect($newUser->can('interview.read'))->toBeTrue();
        });

        it('can revoke interview permissions', function () {
            // Create a new user with direct permission (not via role)
            $tempUser = User::factory()->create();
            $tempUser->givePermissionTo('interview.read');

            expect($tempUser->can('interview.read'))->toBeTrue();

            // Now revoke the permission
            $tempUser->revokePermissionTo('interview.read');
            $tempUser->refresh(); // Refresh to clear cached permissions

            expect($tempUser->can('interview.read'))->toBeFalse();

            $response = $this->actingAs($tempUser)->getJson('/api/v1/interviews');
            $response->assertStatus(403);
        });

        it('can create custom role with specific interview permissions', function () {
            $customRole = Role::firstOrCreate(['name' => 'interviewer']);
            $customRole->givePermissionTo(['interview.read', 'interview.update']);

            $customUser = User::factory()->create();
            $customUser->assignRole('interviewer');

            expect($customUser->can('interview.read'))->toBeTrue()
                ->and($customUser->can('interview.create'))->toBeFalse()
                ->and($customUser->can('interview.update'))->toBeTrue()
                ->and($customUser->can('interview.delete'))->toBeFalse();
        });
    });

    describe('Multiple Permissions Scenarios', function () {
        it('handles user with multiple roles', function () {
            $multiRoleUser = User::factory()->create();
            $multiRoleUser->assignRole(['user', 'hr']);

            // Should have the highest permissions from any role
            expect($multiRoleUser->can('interview.read'))->toBeTrue()
                ->and($multiRoleUser->can('interview.create'))->toBeTrue()
                ->and($multiRoleUser->can('interview.update'))->toBeTrue()
                ->and($multiRoleUser->can('interview.delete'))->toBeFalse();
        });

        it('handles direct permissions overriding role permissions', function () {
            $specialUser = User::factory()->create();
            $specialUser->assignRole('user'); // Only has read permission
            $specialUser->givePermissionTo('interview.delete'); // Direct delete permission

            expect($specialUser->can('interview.read'))->toBeTrue()
                ->and($specialUser->can('interview.create'))->toBeFalse()
                ->and($specialUser->can('interview.update'))->toBeFalse()
                ->and($specialUser->can('interview.delete'))->toBeTrue();
        });
    });
});
