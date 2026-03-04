<?php

use App\Models\JobOffer;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Job Offer Filtering and Pagination', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        // Create Module record for dynamic module permission middleware
        Module::create([
            'name' => 'job_offers',
            'display_name' => 'Job Offers',
            'category' => 'Recruitment',
            'icon' => 'file-text',
            'route' => '/recruitment/job-offers-list',
            'read_permission' => 'job_offers.read',
            'edit_permissions' => ['job_offers.edit'],
            'order' => 21,
            'is_active' => true,
        ]);

        // Create permissions and assign to user
        Permission::firstOrCreate(['name' => 'job_offers.read']);
        $this->user->givePermissionTo('job_offers.read');

        $this->actingAs($this->user);
    });

    describe('Position Filtering', function () {
        beforeEach(function () {
            JobOffer::factory()->create(['position_name' => 'Software Developer', 'candidate_name' => 'John Doe']);
            JobOffer::factory()->create(['position_name' => 'Project Manager', 'candidate_name' => 'Jane Smith']);
            JobOffer::factory()->create(['position_name' => 'Data Analyst', 'candidate_name' => 'Bob Johnson']);
            JobOffer::factory()->create(['position_name' => 'Software Developer', 'candidate_name' => 'Alice Brown']);
            JobOffer::factory()->create(['position_name' => 'UI/UX Designer', 'candidate_name' => 'Charlie Wilson']);
        });

        it('filters by single position', function () {
            $response = $this->getJson('/api/v1/job-offers?filter_position=Software Developer');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $positions = collect($data)->pluck('position_name')->unique()->toArray();
            expect($positions)->toBe(['Software Developer']);
        });

        it('filters by multiple positions', function () {
            $response = $this->getJson('/api/v1/job-offers?filter_position=Software Developer,Project Manager');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(3);

            $positions = collect($data)->pluck('position_name')->unique()->sort()->values()->toArray();
            expect($positions)->toBe(['Project Manager', 'Software Developer']);
        });

        it('returns empty result for non-existent position', function () {
            $response = $this->getJson('/api/v1/job-offers?filter_position=Non Existent Position');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(0);
        });
    });

    describe('Status Filtering', function () {
        beforeEach(function () {
            JobOffer::factory()->create(['acceptance_status' => 'Accepted', 'candidate_name' => 'John Doe']);
            JobOffer::factory()->create(['acceptance_status' => 'Declined', 'candidate_name' => 'Jane Smith']);
            JobOffer::factory()->create(['acceptance_status' => 'Pending', 'candidate_name' => 'Bob Johnson']);
            JobOffer::factory()->create(['acceptance_status' => 'Accepted', 'candidate_name' => 'Alice Brown']);
            JobOffer::factory()->create(['acceptance_status' => 'Pending', 'candidate_name' => 'Charlie Wilson']);
        });

        it('filters by single acceptance status', function () {
            $response = $this->getJson('/api/v1/job-offers?filter_status=Accepted');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $statuses = collect($data)->pluck('acceptance_status')->unique()->toArray();
            expect($statuses)->toBe(['Accepted']);
        });

        it('filters by multiple acceptance statuses', function () {
            $response = $this->getJson('/api/v1/job-offers?filter_status=Accepted,Pending');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(4);

            $statuses = collect($data)->pluck('acceptance_status')->unique()->sort()->values()->toArray();
            expect($statuses)->toBe(['Accepted', 'Pending']);
        });
    });

    describe('Combined Filtering', function () {
        beforeEach(function () {
            JobOffer::factory()->create([
                'position_name' => 'Software Developer',
                'acceptance_status' => 'Accepted',
                'candidate_name' => 'John Doe',
            ]);
            JobOffer::factory()->create([
                'position_name' => 'Software Developer',
                'acceptance_status' => 'Declined',
                'candidate_name' => 'Jane Smith',
            ]);
            JobOffer::factory()->create([
                'position_name' => 'Project Manager',
                'acceptance_status' => 'Accepted',
                'candidate_name' => 'Bob Johnson',
            ]);
            JobOffer::factory()->create([
                'position_name' => 'Data Analyst',
                'acceptance_status' => 'Pending',
                'candidate_name' => 'Alice Brown',
            ]);
        });

        it('applies both position and status filters', function () {
            $response = $this->getJson('/api/v1/job-offers?filter_position=Software Developer&filter_status=Accepted');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(1);

            $offer = $data[0];
            expect($offer['position_name'])->toBe('Software Developer')
                ->and($offer['acceptance_status'])->toBe('Accepted')
                ->and($offer['candidate_name'])->toBe('John Doe');
        });

        it('returns empty result when no matches found', function () {
            $response = $this->getJson('/api/v1/job-offers?filter_position=Data Analyst&filter_status=Accepted');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(0);
        });
    });

    describe('Sorting', function () {
        beforeEach(function () {
            JobOffer::factory()->create([
                'candidate_name' => 'Charlie Brown',
                'position_name' => 'Data Analyst',
                'date' => '2024-03-01',
            ]);
            JobOffer::factory()->create([
                'candidate_name' => 'Alice Smith',
                'position_name' => 'Software Developer',
                'date' => '2024-01-01',
            ]);
            JobOffer::factory()->create([
                'candidate_name' => 'Bob Johnson',
                'position_name' => 'Project Manager',
                'date' => '2024-02-01',
            ]);
        });

        it('sorts by candidate name ascending', function () {
            $response = $this->getJson('/api/v1/job-offers?sort_by=candidate_name&sort_order=asc');

            $response->assertStatus(200);

            $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
            expect($names)->toBe(['Alice Smith', 'Bob Johnson', 'Charlie Brown']);
        });

        it('sorts by candidate name descending', function () {
            $response = $this->getJson('/api/v1/job-offers?sort_by=candidate_name&sort_order=desc');

            $response->assertStatus(200);

            $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
            expect($names)->toBe(['Charlie Brown', 'Bob Johnson', 'Alice Smith']);
        });

        it('sorts by position name ascending', function () {
            $response = $this->getJson('/api/v1/job-offers?sort_by=position_name&sort_order=asc');

            $response->assertStatus(200);

            $positions = collect($response->json('data'))->pluck('position_name')->toArray();
            expect($positions)->toBe(['Data Analyst', 'Project Manager', 'Software Developer']);
        });

        it('sorts by date ascending', function () {
            $response = $this->getJson('/api/v1/job-offers?sort_by=date&sort_order=asc');

            $response->assertStatus(200);

            $dates = collect($response->json('data'))->pluck('date')->toArray();
            expect($dates)->toBe(['2024-01-01', '2024-02-01', '2024-03-01']);
        });

        it('sorts by date descending', function () {
            $response = $this->getJson('/api/v1/job-offers?sort_by=date&sort_order=desc');

            $response->assertStatus(200);

            $dates = collect($response->json('data'))->pluck('date')->toArray();
            expect($dates)->toBe(['2024-03-01', '2024-02-01', '2024-01-01']);
        });

        it('rejects invalid sort_by field', function () {
            $response = $this->getJson('/api/v1/job-offers?sort_by=invalid_field');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['sort_by']);
        });
    });

    describe('Pagination', function () {
        beforeEach(function () {
            JobOffer::factory()->count(25)->create();
        });

        it('returns default pagination (10 per page)', function () {
            $response = $this->getJson('/api/v1/job-offers');

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
            $response = $this->getJson('/api/v1/job-offers?per_page=5');

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
            $response = $this->getJson('/api/v1/job-offers?page=2&per_page=5');

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
            $response = $this->getJson('/api/v1/job-offers?page=3&per_page=10');

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
            $response = $this->getJson('/api/v1/job-offers?per_page=150');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['per_page']);
        });

        it('rejects invalid per_page value', function () {
            $response = $this->getJson('/api/v1/job-offers?per_page=0');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['per_page']);
        });

        it('rejects invalid page value', function () {
            $response = $this->getJson('/api/v1/job-offers?page=0');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['page']);
        });
    });

    describe('Edge Cases', function () {
        it('handles empty filter values gracefully', function () {
            JobOffer::factory()->count(3)->create();

            $response = $this->getJson('/api/v1/job-offers?filter_position=&filter_status=');

            $response->assertStatus(200);

            expect($response->json('data'))->toHaveCount(3);
        });

        it('returns empty data when no job offers exist', function () {
            $response = $this->getJson('/api/v1/job-offers');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Job offers retrieved successfully',
                ]);

            expect($response->json('data'))->toHaveCount(0);
        });
    });
});
