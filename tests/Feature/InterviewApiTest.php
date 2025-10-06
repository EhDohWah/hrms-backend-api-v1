<?php

use App\Models\Interview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Interview API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        // Create permissions and assign to user
        Permission::firstOrCreate(['name' => 'interview.read']);
        Permission::firstOrCreate(['name' => 'interview.create']);
        Permission::firstOrCreate(['name' => 'interview.update']);
        Permission::firstOrCreate(['name' => 'interview.delete']);

        $this->user->givePermissionTo(['interview.read', 'interview.create', 'interview.update', 'interview.delete']);

        $this->actingAs($this->user);
    });

    describe('GET /api/interviews', function () {
        it('returns paginated interviews list', function () {
            Interview::factory()->count(15)->create();

            $response = $this->getJson('/api/v1/interviews');

            if ($response->status() !== 200) {
                dump($response->json());
            }

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'candidate_name',
                            'phone',
                            'job_position',
                            'interviewer_name',
                            'interview_date',
                            'start_time',
                            'end_time',
                            'interview_mode',
                            'interview_status',
                            'hired_status',
                            'score',
                            'feedback',
                            'reference_info',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'from',
                        'to',
                        'has_more_pages',
                    ],
                    'filters' => [
                        'applied_filters',
                    ],
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Interviews retrieved successfully',
                ]);

            expect($response->json('data'))->toHaveCount(10) // Default per_page
                ->and($response->json('pagination.total'))->toBe(15);
        });

        it('filters interviews by job position', function () {
            Interview::factory()->create(['job_position' => 'Software Engineer']);
            Interview::factory()->create(['job_position' => 'Project Manager']);
            Interview::factory()->create(['job_position' => 'Data Scientist']);

            $response = $this->getJson('/api/v1/interviews?filter_job_position=Software Engineer,Project Manager');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $jobPositions = collect($data)->pluck('job_position')->toArray();
            expect($jobPositions)->toContain('Software Engineer')
                ->and($jobPositions)->toContain('Project Manager')
                ->and($jobPositions)->not->toContain('Data Scientist');
        });

        it('filters interviews by hired status', function () {
            Interview::factory()->create(['hired_status' => 'Hired']);
            Interview::factory()->create(['hired_status' => 'Not Hired']);
            Interview::factory()->create(['hired_status' => 'Pending']);

            $response = $this->getJson('/api/v1/interviews?filter_hired_status=Hired,Pending');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $hiredStatuses = collect($data)->pluck('hired_status')->toArray();
            expect($hiredStatuses)->toContain('Hired')
                ->and($hiredStatuses)->toContain('Pending')
                ->and($hiredStatuses)->not->toContain('Not Hired');
        });

        it('sorts interviews by candidate name', function () {
            Interview::factory()->create(['candidate_name' => 'Charlie Brown']);
            Interview::factory()->create(['candidate_name' => 'Alice Smith']);
            Interview::factory()->create(['candidate_name' => 'Bob Johnson']);

            $response = $this->getJson('/api/v1/interviews?sort_by=candidate_name&sort_order=asc');

            $response->assertStatus(200);

            $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
            expect($names)->toBe(['Alice Smith', 'Bob Johnson', 'Charlie Brown']);
        });

        it('sorts interviews by interview date descending', function () {
            Interview::factory()->create(['interview_date' => '2024-01-01']);
            Interview::factory()->create(['interview_date' => '2024-03-01']);
            Interview::factory()->create(['interview_date' => '2024-02-01']);

            $response = $this->getJson('/api/v1/interviews?sort_by=interview_date&sort_order=desc');

            $response->assertStatus(200);

            $dates = collect($response->json('data'))->pluck('interview_date')->toArray();
            expect($dates)->toBe(['2024-03-01', '2024-02-01', '2024-01-01']);
        });

        it('handles custom pagination parameters', function () {
            Interview::factory()->count(25)->create();

            $response = $this->getJson('/api/v1/interviews?page=2&per_page=5');

            $response->assertStatus(200)
                ->assertJson([
                    'pagination' => [
                        'current_page' => 2,
                        'per_page' => 5,
                        'total' => 25,
                        'last_page' => 5,
                        'from' => 6,
                        'to' => 10,
                        'has_more_pages' => true,
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(5);
        });

        it('validates pagination parameters', function () {
            $response = $this->getJson('/api/v1/interviews?per_page=150');

            $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Failed to retrieve interviews',
                    'error' => 'The per page field must not be greater than 100.',
                ]);
        });

        it('validates sort parameters', function () {
            $response = $this->getJson('/api/v1/interviews?sort_by=invalid_field');

            $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Failed to retrieve interviews',
                ]);
        });
    });

    describe('POST /api/interviews', function () {
        it('creates a new interview with valid data', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'phone' => '1234567890',
                'job_position' => 'Software Engineer',
                'interviewer_name' => 'Jane Smith',
                'interview_date' => '2024-12-25',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'interview_mode' => 'Online',
                'interview_status' => 'Scheduled',
                'hired_status' => 'Pending',
                'score' => 85.5,
                'feedback' => 'Great candidate',
                'reference_info' => 'Referred by colleague',
                'created_by' => 'HR Manager',
            ];

            $response = $this->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Interview created successfully',
                    'data' => [
                        'candidate_name' => 'John Doe',
                        'phone' => '1234567890',
                        'job_position' => 'Software Engineer',
                        'interviewer_name' => 'Jane Smith',
                        'interview_date' => '2024-12-25',
                        'start_time' => '10:00:00',
                        'end_time' => '11:00:00',
                        'interview_mode' => 'Online',
                        'interview_status' => 'Scheduled',
                        'hired_status' => 'Pending',
                        'score' => 85.5,
                        'feedback' => 'Great candidate',
                        'reference_info' => 'Referred by colleague',
                        'created_by' => 'HR Manager',
                    ],
                ]);

            $this->assertDatabaseHas('interviews', [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ]);
        });

        it('creates interview with minimum required fields', function () {
            $interviewData = [
                'candidate_name' => 'Jane Doe',
                'job_position' => 'Project Manager',
            ];

            $response = $this->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Interview created successfully',
                    'data' => [
                        'candidate_name' => 'Jane Doe',
                        'job_position' => 'Project Manager',
                    ],
                ]);

            $this->assertDatabaseHas('interviews', $interviewData);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/interviews', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['candidate_name', 'job_position']);
        });

        it('validates field lengths', function () {
            $interviewData = [
                'candidate_name' => str_repeat('a', 256), // Too long
                'job_position' => 'Software Engineer',
                'phone' => '12345678901', // Too long (max 10)
            ];

            $response = $this->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['candidate_name', 'phone']);
        });

        it('validates time format and logic', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'start_time' => '10:00:00',
                'end_time' => '09:00:00', // Before start time
            ];

            $response = $this->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['end_time']);
        });

        it('validates score range', function () {
            $interviewData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'score' => 150, // Out of range (0-100)
            ];

            $response = $this->postJson('/api/v1/interviews', $interviewData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['score']);
        });
    });

    describe('GET /api/interviews/{id}', function () {
        it('returns specific interview', function () {
            $interview = Interview::factory()->create([
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ]);

            $response = $this->getJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Interview retrieved successfully',
                    'data' => [
                        'id' => $interview->id,
                        'candidate_name' => 'John Doe',
                        'job_position' => 'Software Engineer',
                    ],
                ]);
        });

        it('returns 404 for non-existent interview', function () {
            $response = $this->getJson('/api/v1/interviews/99999');

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Interview not found',
                ]);
        });
    });

    describe('PUT /api/interviews/{id}', function () {
        it('updates existing interview', function () {
            $interview = Interview::factory()->create([
                'candidate_name' => 'John Doe',
                'interview_status' => 'Scheduled',
            ]);

            $updateData = [
                'candidate_name' => 'John Smith',
                'job_position' => 'Senior Software Engineer',
                'interview_status' => 'Completed',
                'hired_status' => 'Hired',
                'score' => 95.0,
            ];

            $response = $this->putJson("/api/v1/interviews/{$interview->id}", $updateData);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Interview updated successfully',
                    'data' => [
                        'id' => $interview->id,
                        'candidate_name' => 'John Smith',
                        'job_position' => 'Senior Software Engineer',
                        'interview_status' => 'Completed',
                        'hired_status' => 'Hired',
                        'score' => 95.0,
                    ],
                ]);

            $this->assertDatabaseHas('interviews', [
                'id' => $interview->id,
                'candidate_name' => 'John Smith',
                'interview_status' => 'Completed',
            ]);
        });

        it('returns 404 for non-existent interview', function () {
            $updateData = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ];

            $response = $this->putJson('/api/v1/interviews/99999', $updateData);

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Interview not found',
                ]);
        });

        it('validates update data', function () {
            $interview = Interview::factory()->create();

            $updateData = [
                'candidate_name' => '', // Required field empty
                'score' => 150, // Out of range
            ];

            $response = $this->putJson("/api/v1/interviews/{$interview->id}", $updateData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['candidate_name', 'score']);
        });
    });

    describe('DELETE /api/interviews/{id}', function () {
        it('deletes existing interview', function () {
            $interview = Interview::factory()->create();

            $response = $this->deleteJson("/api/v1/interviews/{$interview->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Interview deleted successfully',
                ]);

            $this->assertDatabaseMissing('interviews', [
                'id' => $interview->id,
            ]);
        });

        it('returns 404 for non-existent interview', function () {
            $response = $this->deleteJson('/api/v1/interviews/99999');

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Interview not found',
                ]);
        });
    });

    describe('GET /api/interviews/by-candidate/{candidateName}', function () {
        it('finds interview by candidate name', function () {
            $interview = Interview::factory()->create([
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ]);

            $response = $this->getJson('/api/v1/interviews/by-candidate/John Doe');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Interview retrieved successfully',
                    'data' => [
                        'id' => $interview->id,
                        'candidate_name' => 'John Doe',
                        'job_position' => 'Software Engineer',
                    ],
                ]);
        });

        it('finds interview by candidate name case insensitive', function () {
            $interview = Interview::factory()->create([
                'candidate_name' => 'John Doe',
            ]);

            $response = $this->getJson('/api/v1/interviews/by-candidate/john doe');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $interview->id,
                        'candidate_name' => 'John Doe',
                    ],
                ]);
        });

        it('returns 404 for non-existent candidate', function () {
            $response = $this->getJson('/api/v1/interviews/by-candidate/Non Existent');

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Interview not found',
                ]);
        });

        it('handles URL encoded candidate names', function () {
            $interview = Interview::factory()->create([
                'candidate_name' => 'John O\'Connor',
            ]);

            $response = $this->getJson('/api/v1/interviews/by-candidate/'.urlencode('John O\'Connor'));

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'candidate_name' => 'John O\'Connor',
                    ],
                ]);
        });
    });

});
