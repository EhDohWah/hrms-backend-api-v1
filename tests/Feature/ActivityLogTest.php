<?php

use App\Models\ActivityLog;
use App\Models\Grant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('ActivityLog Model', function () {
    it('can create an activity log entry', function () {
        $log = ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'created',
            'subject_type' => 'App\Models\Grant',
            'subject_id' => 1,
            'subject_name' => 'Test Grant',
            'description' => 'Test description',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        expect($log)->toBeInstanceOf(ActivityLog::class)
            ->and($log->action)->toBe('created')
            ->and($log->subject_name)->toBe('Test Grant');
    });

    it('can store properties as JSON', function () {
        $properties = [
            'old' => ['name' => 'Old Name'],
            'new' => ['name' => 'New Name'],
            'changes' => ['name'],
        ];

        $log = ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'updated',
            'subject_type' => 'App\Models\Grant',
            'subject_id' => 1,
            'subject_name' => 'Test Grant',
            'properties' => $properties,
            'created_at' => now(),
        ]);

        expect($log->properties)->toBeArray()
            ->and($log->properties['changes'])->toContain('name');
    });

    it('belongs to a user', function () {
        $log = ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'created',
            'subject_type' => 'App\Models\Grant',
            'subject_id' => 1,
            'created_at' => now(),
        ]);

        expect($log->user)->toBeInstanceOf(User::class)
            ->and($log->user->id)->toBe($this->user->id);
    });
});

describe('ActivityLog Scopes', function () {
    beforeEach(function () {
        // Create test logs
        ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'created',
            'subject_type' => 'App\Models\Grant',
            'subject_id' => 1,
            'subject_name' => 'Grant 1',
            'created_at' => now()->subHours(2),
        ]);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'updated',
            'subject_type' => 'App\Models\Grant',
            'subject_id' => 1,
            'subject_name' => 'Grant 1',
            'created_at' => now()->subHour(),
        ]);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'created',
            'subject_type' => 'App\Models\Employee',
            'subject_id' => 1,
            'subject_name' => 'Employee 1',
            'created_at' => now(),
        ]);
    });

    it('can filter by subject', function () {
        $logs = ActivityLog::forSubject('grant', 1)->get();

        expect($logs)->toHaveCount(2)
            ->and($logs->first()->subject_type)->toBe('App\Models\Grant');
    });

    it('can filter by user', function () {
        $logs = ActivityLog::byUser($this->user->id)->get();

        expect($logs)->toHaveCount(3);
    });

    it('can filter by action', function () {
        $logs = ActivityLog::byAction('created')->get();

        expect($logs)->toHaveCount(2);
    });

    it('can filter by date range', function () {
        $logs = ActivityLog::dateRange(now()->subHours(3), now()->subMinutes(30))->get();

        expect($logs)->toHaveCount(2);
    });
});

describe('ActivityLog API Endpoints', function () {
    beforeEach(function () {
        // Create test logs
        ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'created',
            'subject_type' => 'App\Models\Grant',
            'subject_id' => 1,
            'subject_name' => 'Grant 1',
            'created_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'updated',
            'subject_type' => 'App\Models\Grant',
            'subject_id' => 1,
            'subject_name' => 'Grant 1',
            'created_at' => now()->subMinute(),
        ]);
    });

    it('can list activity logs', function () {
        $response = $this->getJson('/api/v1/activity-logs');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination',
            ]);
    });

    it('can get activity logs for a subject', function () {
        $response = $this->getJson('/api/v1/activity-logs/subject/grant/1');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        expect($response->json('data'))->toHaveCount(2);
    });

    it('can get recent activity logs', function () {
        $response = $this->getJson('/api/v1/activity-logs/recent?limit=10');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'count',
            ]);
    });

    it('can filter activity logs by action', function () {
        $response = $this->getJson('/api/v1/activity-logs?action=created');

        $response->assertSuccessful();

        $logs = $response->json('data');
        expect(collect($logs)->every(fn ($log) => $log['action'] === 'created'))->toBeTrue();
    });
});

describe('LogsActivity Trait', function () {
    it('automatically logs when a model is created', function () {
        $grant = Grant::create([
            'code' => 'TEST-001',
            'name' => 'Test Grant',
            'organization' => 'SMRU',
        ]);

        $log = ActivityLog::where('subject_type', Grant::class)
            ->where('subject_id', $grant->id)
            ->where('action', 'created')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->subject_name)->toBe('TEST-001');
    });

    it('automatically logs when a model is updated', function () {
        $grant = Grant::create([
            'code' => 'TEST-002',
            'name' => 'Test Grant',
            'organization' => 'SMRU',
        ]);

        // Clear the created log
        ActivityLog::truncate();

        $grant->update(['name' => 'Updated Grant Name']);

        $log = ActivityLog::where('subject_type', Grant::class)
            ->where('subject_id', $grant->id)
            ->where('action', 'updated')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->properties)->not->toBeNull()
            ->and($log->properties['changes'])->toContain('name');
    });

    it('automatically logs when a model is deleted', function () {
        $grant = Grant::create([
            'code' => 'TEST-003',
            'name' => 'Test Grant',
            'organization' => 'SMRU',
        ]);

        $grantId = $grant->id;

        // Clear existing logs
        ActivityLog::truncate();

        $grant->delete();

        $log = ActivityLog::where('subject_type', Grant::class)
            ->where('subject_id', $grantId)
            ->where('action', 'deleted')
            ->first();

        expect($log)->not->toBeNull();
    });

    it('does not log timestamp-only updates', function () {
        $grant = Grant::create([
            'code' => 'TEST-004',
            'name' => 'Test Grant',
            'organization' => 'SMRU',
        ]);

        // Clear the created log
        ActivityLog::truncate();

        // Touch only updates timestamps
        $grant->touch();

        $log = ActivityLog::where('subject_type', Grant::class)
            ->where('subject_id', $grant->id)
            ->where('action', 'updated')
            ->first();

        expect($log)->toBeNull();
    });
});

