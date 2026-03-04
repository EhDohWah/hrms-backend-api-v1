<?php

use App\Models\Interview;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Interview Filtering and Pagination', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

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

        // Create permissions and assign to user
        Permission::firstOrCreate(['name' => 'interviews.read']);
        $this->user->givePermissionTo('interviews.read');

        $this->actingAs($this->user);
    });

    describe('Job Position Filtering', function () {
        beforeEach(function () {
            Interview::factory()->create(['job_position' => 'Software Engineer', 'candidate_name' => 'John Doe']);
            Interview::factory()->create(['job_position' => 'Project Manager', 'candidate_name' => 'Jane Smith']);
            Interview::factory()->create(['job_position' => 'Data Scientist', 'candidate_name' => 'Bob Johnson']);
            Interview::factory()->create(['job_position' => 'Software Engineer', 'candidate_name' => 'Alice Brown']);
            Interview::factory()->create(['job_position' => 'UI/UX Designer', 'candidate_name' => 'Charlie Wilson']);
        });

        it('filters by single job position', function () {
            $response = $this->getJson('/api/v1/interviews?filter_job_position=Software Engineer');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $jobPositions = collect($data)->pluck('job_position')->unique()->toArray();
            expect($jobPositions)->toBe(['Software Engineer']);
        });

        it('filters by multiple job positions', function () {
            $response = $this->getJson('/api/v1/interviews?filter_job_position=Software Engineer,Project Manager');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(3);

            $jobPositions = collect($data)->pluck('job_position')->unique()->sort()->values()->toArray();
            expect($jobPositions)->toBe(['Project Manager', 'Software Engineer']);
        });

        it('returns empty result for non-existent job position', function () {
            $response = $this->getJson('/api/v1/interviews?filter_job_position=Non Existent Position');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(0);
        });
    });

    describe('Hired Status Filtering', function () {
        beforeEach(function () {
            Interview::factory()->create(['hired_status' => 'Hired', 'candidate_name' => 'John Doe']);
            Interview::factory()->create(['hired_status' => 'Not Hired', 'candidate_name' => 'Jane Smith']);
            Interview::factory()->create(['hired_status' => 'Pending', 'candidate_name' => 'Bob Johnson']);
            Interview::factory()->create(['hired_status' => 'Hired', 'candidate_name' => 'Alice Brown']);
            Interview::factory()->create(['hired_status' => 'Pending', 'candidate_name' => 'Charlie Wilson']);
        });

        it('filters by single hired status', function () {
            $response = $this->getJson('/api/v1/interviews?filter_hired_status=Hired');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $hiredStatuses = collect($data)->pluck('hired_status')->unique()->toArray();
            expect($hiredStatuses)->toBe(['Hired']);
        });

        it('filters by multiple hired statuses', function () {
            $response = $this->getJson('/api/v1/interviews?filter_hired_status=Hired,Pending');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(4);

            $hiredStatuses = collect($data)->pluck('hired_status')->unique()->sort()->values()->toArray();
            expect($hiredStatuses)->toBe(['Hired', 'Pending']);
        });
    });

    describe('Combined Filtering', function () {
        beforeEach(function () {
            Interview::factory()->create([
                'job_position' => 'Software Engineer',
                'hired_status' => 'Hired',
                'candidate_name' => 'John Doe',
            ]);
            Interview::factory()->create([
                'job_position' => 'Software Engineer',
                'hired_status' => 'Not Hired',
                'candidate_name' => 'Jane Smith',
            ]);
            Interview::factory()->create([
                'job_position' => 'Project Manager',
                'hired_status' => 'Hired',
                'candidate_name' => 'Bob Johnson',
            ]);
            Interview::factory()->create([
                'job_position' => 'Data Scientist',
                'hired_status' => 'Pending',
                'candidate_name' => 'Alice Brown',
            ]);
        });

        it('applies both job position and hired status filters', function () {
            $response = $this->getJson('/api/v1/interviews?filter_job_position=Software Engineer&filter_hired_status=Hired');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(1);

            $interview = $data[0];
            expect($interview['job_position'])->toBe('Software Engineer')
                ->and($interview['hired_status'])->toBe('Hired')
                ->and($interview['candidate_name'])->toBe('John Doe');
        });

        it('returns empty result when no matches found', function () {
            $response = $this->getJson('/api/v1/interviews?filter_job_position=Data Scientist&filter_hired_status=Hired');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(0);
        });
    });

    describe('Sorting', function () {
        beforeEach(function () {
            Interview::factory()->create([
                'candidate_name' => 'Charlie Brown',
                'job_position' => 'Data Scientist',
                'interview_date' => '2024-03-01',
            ]);
            Interview::factory()->create([
                'candidate_name' => 'Alice Smith',
                'job_position' => 'Software Engineer',
                'interview_date' => '2024-01-01',
            ]);
            Interview::factory()->create([
                'candidate_name' => 'Bob Johnson',
                'job_position' => 'Project Manager',
                'interview_date' => '2024-02-01',
            ]);
        });

        it('sorts by candidate name ascending', function () {
            $response = $this->getJson('/api/v1/interviews?sort_by=candidate_name&sort_order=asc');

            $response->assertStatus(200);

            $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
            expect($names)->toBe(['Alice Smith', 'Bob Johnson', 'Charlie Brown']);
        });

        it('sorts by candidate name descending', function () {
            $response = $this->getJson('/api/v1/interviews?sort_by=candidate_name&sort_order=desc');

            $response->assertStatus(200);

            $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
            expect($names)->toBe(['Charlie Brown', 'Bob Johnson', 'Alice Smith']);
        });

        it('sorts by job position ascending', function () {
            $response = $this->getJson('/api/v1/interviews?sort_by=job_position&sort_order=asc');

            $response->assertStatus(200);

            $positions = collect($response->json('data'))->pluck('job_position')->toArray();
            expect($positions)->toBe(['Data Scientist', 'Project Manager', 'Software Engineer']);
        });

        it('sorts by interview date ascending', function () {
            $response = $this->getJson('/api/v1/interviews?sort_by=interview_date&sort_order=asc');

            $response->assertStatus(200);

            $dates = collect($response->json('data'))->pluck('interview_date')->toArray();
            expect($dates)->toBe(['2024-01-01', '2024-02-01', '2024-03-01']);
        });

        it('sorts by interview date descending', function () {
            $response = $this->getJson('/api/v1/interviews?sort_by=interview_date&sort_order=desc');

            $response->assertStatus(200);

            $dates = collect($response->json('data'))->pluck('interview_date')->toArray();
            expect($dates)->toBe(['2024-03-01', '2024-02-01', '2024-01-01']);
        });

        it('rejects invalid sort_by field', function () {
            $response = $this->getJson('/api/v1/interviews?sort_by=invalid_field');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['sort_by']);
        });

        it('defaults to created_at desc when sort_order is not provided', function () {
            // Note: beforeEach already creates 3 interviews (Charlie Brown, Alice Smith, Bob Johnson)
            // Create additional interviews with different timestamps
            $interview1 = Interview::factory()->create(['candidate_name' => 'Dave Wilson']);
            sleep(1);
            $interview2 = Interview::factory()->create(['candidate_name' => 'Eve Taylor']);
            sleep(1);
            $interview3 = Interview::factory()->create(['candidate_name' => 'Frank Miller']);

            $response = $this->getJson('/api/v1/interviews');

            $response->assertStatus(200);

            $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
            // Most recent first (created_at desc) - the 3 newest should be first
            expect($names[0])->toBe('Frank Miller')
                ->and($names[1])->toBe('Eve Taylor')
                ->and($names[2])->toBe('Dave Wilson');
        });
    });

    describe('Pagination', function () {
        beforeEach(function () {
            Interview::factory()->count(25)->create();
        });

        it('returns default pagination (10 per page)', function () {
            $response = $this->getJson('/api/v1/interviews');

            $response->assertStatus(200)
                ->assertJson([
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 10,
                        'total' => 25,
                        'last_page' => 3,
                        'from' => 1,
                        'to' => 10,
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(10);
        });

        it('handles custom per_page parameter', function () {
            $response = $this->getJson('/api/v1/interviews?per_page=5');

            $response->assertStatus(200)
                ->assertJson([
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 5,
                        'total' => 25,
                        'last_page' => 5,
                        'from' => 1,
                        'to' => 5,
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(5);
        });

        it('handles page navigation', function () {
            $response = $this->getJson('/api/v1/interviews?page=2&per_page=5');

            $response->assertStatus(200)
                ->assertJson([
                    'meta' => [
                        'current_page' => 2,
                        'per_page' => 5,
                        'total' => 25,
                        'last_page' => 5,
                        'from' => 6,
                        'to' => 10,
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(5);
        });

        it('handles last page correctly', function () {
            $response = $this->getJson('/api/v1/interviews?page=3&per_page=10');

            $response->assertStatus(200)
                ->assertJson([
                    'meta' => [
                        'current_page' => 3,
                        'per_page' => 10,
                        'total' => 25,
                        'last_page' => 3,
                        'from' => 21,
                        'to' => 25,
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(5);
        });

        it('rejects per_page exceeding maximum', function () {
            $response = $this->getJson('/api/v1/interviews?per_page=150');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['per_page']);
        });

        it('rejects invalid per_page value', function () {
            $response = $this->getJson('/api/v1/interviews?per_page=0');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['per_page']);
        });

        it('rejects invalid page value', function () {
            $response = $this->getJson('/api/v1/interviews?page=0');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['page']);
        });
    });

    describe('Combined Filtering, Sorting, and Pagination', function () {
        beforeEach(function () {
            // Create interviews with specific data for testing
            Interview::factory()->create([
                'candidate_name' => 'Alice Johnson',
                'job_position' => 'Software Engineer',
                'hired_status' => 'Hired',
                'interview_date' => '2024-01-15',
            ]);
            Interview::factory()->create([
                'candidate_name' => 'Bob Smith',
                'job_position' => 'Software Engineer',
                'hired_status' => 'Not Hired',
                'interview_date' => '2024-01-10',
            ]);
            Interview::factory()->create([
                'candidate_name' => 'Charlie Brown',
                'job_position' => 'Software Engineer',
                'hired_status' => 'Hired',
                'interview_date' => '2024-01-20',
            ]);
            Interview::factory()->create([
                'candidate_name' => 'Diana Wilson',
                'job_position' => 'Project Manager',
                'hired_status' => 'Hired',
                'interview_date' => '2024-01-05',
            ]);
            Interview::factory()->create([
                'candidate_name' => 'Eve Davis',
                'job_position' => 'Software Engineer',
                'hired_status' => 'Pending',
                'interview_date' => '2024-01-25',
            ]);
        });

        it('applies filter, sort, and pagination together', function () {
            $response = $this->getJson('/api/v1/interviews?filter_job_position=Software Engineer&filter_hired_status=Hired&sort_by=interview_date&sort_order=asc&per_page=1&page=2');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(1);

            $interview = $data[0];
            expect($interview['candidate_name'])->toBe('Charlie Brown')
                ->and($interview['job_position'])->toBe('Software Engineer')
                ->and($interview['hired_status'])->toBe('Hired')
                ->and($interview['interview_date'])->toBe('2024-01-20');

            $response->assertJson([
                'meta' => [
                    'current_page' => 2,
                    'per_page' => 1,
                    'total' => 2, // Only 2 Software Engineers with Hired status
                    'last_page' => 2,
                ],
            ]);
        });

        it('maintains filters across pages', function () {
            $response = $this->getJson('/api/v1/interviews?filter_job_position=Software Engineer&per_page=2&page=1');

            $response->assertStatus(200);

            expect($response->json('data'))->toHaveCount(2)
                ->and($response->json('meta.total'))->toBe(4); // 4 Software Engineers total
        });
    });

    describe('Edge Cases', function () {
        it('handles empty filter values gracefully', function () {
            Interview::factory()->count(3)->create();

            $response = $this->getJson('/api/v1/interviews?filter_job_position=&filter_hired_status=');

            $response->assertStatus(200);

            expect($response->json('data'))->toHaveCount(3);
        });

        it('handles whitespace in filter values', function () {
            Interview::factory()->create(['job_position' => 'Software Engineer']);

            // URL encode the space characters to avoid BadRequestException
            $response = $this->getJson('/api/v1/interviews?filter_job_position='.urlencode(' Software Engineer '));

            $response->assertStatus(200);

            // Whitespace trimming depends on controller implementation
            // This test just ensures it doesn't crash
            expect($response->json('data'))->toBeArray();
        });

        it('returns empty data when no interviews exist', function () {
            $response = $this->getJson('/api/v1/interviews');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Interviews retrieved successfully',
                ]);

            expect($response->json('data'))->toHaveCount(0);
        });
    });
});
