<?php

use App\Models\JobOffer;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Job Offer API', function () {
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
        Permission::firstOrCreate(['name' => 'job_offers.create']);
        Permission::firstOrCreate(['name' => 'job_offers.update']);
        Permission::firstOrCreate(['name' => 'job_offers.delete']);

        $this->user->givePermissionTo(['job_offers.read', 'job_offers.create', 'job_offers.update', 'job_offers.delete']);

        $this->actingAs($this->user);
    });

    describe('GET /api/v1/job-offers', function () {
        it('returns paginated job offers list', function () {
            JobOffer::factory()->count(15)->create();

            $response = $this->getJson('/api/v1/job-offers');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'custom_offer_id',
                            'date',
                            'candidate_name',
                            'position_name',
                            'probation_salary',
                            'pass_probation_salary',
                            'acceptance_deadline',
                            'acceptance_status',
                            'note',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'meta' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'from',
                        'to',
                    ],
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Job offers retrieved successfully',
                ]);

            expect($response->json('data'))->toHaveCount(10) // Default per_page
                ->and($response->json('meta.total'))->toBe(15);
        });

        it('filters job offers by position', function () {
            JobOffer::factory()->create(['position_name' => 'Software Developer']);
            JobOffer::factory()->create(['position_name' => 'Project Manager']);
            JobOffer::factory()->create(['position_name' => 'Data Analyst']);

            $response = $this->getJson('/api/v1/job-offers?filter_position=Software Developer,Project Manager');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $positions = collect($data)->pluck('position_name')->toArray();
            expect($positions)->toContain('Software Developer')
                ->and($positions)->toContain('Project Manager')
                ->and($positions)->not->toContain('Data Analyst');
        });

        it('filters job offers by acceptance status', function () {
            JobOffer::factory()->create(['acceptance_status' => 'Accepted']);
            JobOffer::factory()->create(['acceptance_status' => 'Declined']);
            JobOffer::factory()->create(['acceptance_status' => 'Pending']);

            $response = $this->getJson('/api/v1/job-offers?filter_status=Accepted,Pending');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->toHaveCount(2);

            $statuses = collect($data)->pluck('acceptance_status')->toArray();
            expect($statuses)->toContain('Accepted')
                ->and($statuses)->toContain('Pending')
                ->and($statuses)->not->toContain('Declined');
        });

        it('sorts job offers by candidate name', function () {
            JobOffer::factory()->create(['candidate_name' => 'Charlie Brown']);
            JobOffer::factory()->create(['candidate_name' => 'Alice Smith']);
            JobOffer::factory()->create(['candidate_name' => 'Bob Johnson']);

            $response = $this->getJson('/api/v1/job-offers?sort_by=candidate_name&sort_order=asc');

            $response->assertStatus(200);

            $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
            expect($names)->toBe(['Alice Smith', 'Bob Johnson', 'Charlie Brown']);
        });

        it('sorts job offers by date descending', function () {
            JobOffer::factory()->create(['date' => '2024-01-01']);
            JobOffer::factory()->create(['date' => '2024-03-01']);
            JobOffer::factory()->create(['date' => '2024-02-01']);

            $response = $this->getJson('/api/v1/job-offers?sort_by=date&sort_order=desc');

            $response->assertStatus(200);

            $dates = collect($response->json('data'))->pluck('date')->toArray();
            expect($dates)->toBe(['2024-03-01', '2024-02-01', '2024-01-01']);
        });

        it('handles custom pagination parameters', function () {
            JobOffer::factory()->count(25)->create();

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

        it('validates pagination parameters', function () {
            $response = $this->getJson('/api/v1/job-offers?per_page=150');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['per_page']);
        });

        it('validates sort parameters', function () {
            $response = $this->getJson('/api/v1/job-offers?sort_by=invalid_field');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['sort_by']);
        });

        it('searches by candidate name and position', function () {
            JobOffer::factory()->create(['candidate_name' => 'John Doe', 'position_name' => 'Developer']);
            JobOffer::factory()->create(['candidate_name' => 'Jane Smith', 'position_name' => 'Manager']);
            JobOffer::factory()->create(['candidate_name' => 'Bob Wilson', 'position_name' => 'Developer']);

            $response = $this->getJson('/api/v1/job-offers?search=John');

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(1);
            expect($response->json('data.0.candidate_name'))->toBe('John Doe');
        });
    });

    describe('POST /api/v1/job-offers', function () {
        it('creates a new job offer with valid data', function () {
            $offerData = [
                'date' => '2024-12-25',
                'candidate_name' => 'John Doe',
                'position_name' => 'Software Developer',
                'probation_salary' => 35000.00,
                'pass_probation_salary' => 40000.00,
                'acceptance_deadline' => '2025-01-15',
                'acceptance_status' => 'Pending',
                'note' => 'Standard job offer with benefits',
            ];

            $response = $this->postJson('/api/v1/job-offers', $offerData);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Job offer created successfully',
                    'data' => [
                        'candidate_name' => 'John Doe',
                        'position_name' => 'Software Developer',
                        'acceptance_status' => 'Pending',
                        'note' => 'Standard job offer with benefits',
                    ],
                ]);

            // created_by is auto-set by the service
            expect($response->json('data.created_by'))->toBe($this->user->name);

            $this->assertDatabaseHas('job_offers', [
                'candidate_name' => 'John Doe',
                'position_name' => 'Software Developer',
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/job-offers', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'date',
                    'candidate_name',
                    'position_name',
                    'probation_salary',
                    'pass_probation_salary',
                    'acceptance_deadline',
                    'acceptance_status',
                    'note',
                ]);
        });

        it('validates acceptance_status enum', function () {
            $offerData = [
                'date' => '2024-12-25',
                'candidate_name' => 'John Doe',
                'position_name' => 'Developer',
                'probation_salary' => 35000,
                'pass_probation_salary' => 40000,
                'acceptance_deadline' => '2025-01-15',
                'acceptance_status' => 'InvalidStatus',
                'note' => 'Test',
            ];

            $response = $this->postJson('/api/v1/job-offers', $offerData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['acceptance_status']);
        });

        it('validates salary is numeric and non-negative', function () {
            $offerData = [
                'date' => '2024-12-25',
                'candidate_name' => 'John Doe',
                'position_name' => 'Developer',
                'probation_salary' => -5000,
                'pass_probation_salary' => 40000,
                'acceptance_deadline' => '2025-01-15',
                'acceptance_status' => 'Pending',
                'note' => 'Test',
            ];

            $response = $this->postJson('/api/v1/job-offers', $offerData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['probation_salary']);
        });
    });

    describe('GET /api/v1/job-offers/{id}', function () {
        it('returns specific job offer', function () {
            $jobOffer = JobOffer::factory()->create([
                'candidate_name' => 'John Doe',
                'position_name' => 'Software Developer',
            ]);

            $response = $this->getJson("/api/v1/job-offers/{$jobOffer->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Job offer retrieved successfully',
                    'data' => [
                        'id' => $jobOffer->id,
                        'candidate_name' => 'John Doe',
                        'position_name' => 'Software Developer',
                    ],
                ]);
        });

        it('returns 404 for non-existent job offer', function () {
            $response = $this->getJson('/api/v1/job-offers/99999');

            $response->assertStatus(404);
        });
    });

    describe('PUT /api/v1/job-offers/{id}', function () {
        it('updates existing job offer', function () {
            $jobOffer = JobOffer::factory()->create([
                'candidate_name' => 'John Doe',
                'acceptance_status' => 'Pending',
            ]);

            $updateData = [
                'candidate_name' => 'John Smith',
                'position_name' => 'Senior Software Developer',
                'acceptance_status' => 'Accepted',
            ];

            $response = $this->putJson("/api/v1/job-offers/{$jobOffer->id}", $updateData);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Job offer updated successfully',
                    'data' => [
                        'id' => $jobOffer->id,
                        'candidate_name' => 'John Smith',
                        'position_name' => 'Senior Software Developer',
                        'acceptance_status' => 'Accepted',
                    ],
                ]);

            $this->assertDatabaseHas('job_offers', [
                'id' => $jobOffer->id,
                'candidate_name' => 'John Smith',
                'acceptance_status' => 'Accepted',
            ]);
        });

        it('returns 404 for non-existent job offer', function () {
            $updateData = [
                'candidate_name' => 'John Doe',
                'position_name' => 'Developer',
            ];

            $response = $this->putJson('/api/v1/job-offers/99999', $updateData);

            $response->assertStatus(404);
        });

        it('validates update data', function () {
            $jobOffer = JobOffer::factory()->create();

            $updateData = [
                'acceptance_status' => 'InvalidStatus',
                'probation_salary' => -100,
            ];

            $response = $this->putJson("/api/v1/job-offers/{$jobOffer->id}", $updateData);

            $response->assertStatus(422);
        });

        it('auto-sets updated_by field', function () {
            $jobOffer = JobOffer::factory()->create();

            $response = $this->putJson("/api/v1/job-offers/{$jobOffer->id}", [
                'candidate_name' => 'Updated Name',
            ]);

            $response->assertStatus(200);
            expect($response->json('data.updated_by'))->toBe($this->user->name);
        });
    });

    describe('DELETE /api/v1/job-offers/{id}', function () {
        it('deletes existing job offer', function () {
            $jobOffer = JobOffer::factory()->create();

            $response = $this->deleteJson("/api/v1/job-offers/{$jobOffer->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Job offer deleted successfully',
                ]);

            $this->assertDatabaseMissing('job_offers', [
                'id' => $jobOffer->id,
            ]);
        });

        it('returns 404 for non-existent job offer', function () {
            $response = $this->deleteJson('/api/v1/job-offers/99999');

            $response->assertStatus(404);
        });
    });

    describe('GET /api/v1/job-offers/by-candidate/{candidateName}', function () {
        it('finds job offer by candidate name', function () {
            $jobOffer = JobOffer::factory()->create([
                'candidate_name' => 'John Doe',
                'position_name' => 'Software Developer',
            ]);

            $response = $this->getJson('/api/v1/job-offers/by-candidate/John Doe');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Job offer retrieved successfully',
                    'data' => [
                        'id' => $jobOffer->id,
                        'candidate_name' => 'John Doe',
                        'position_name' => 'Software Developer',
                    ],
                ]);
        });

        it('finds job offer by partial candidate name', function () {
            $jobOffer = JobOffer::factory()->create([
                'candidate_name' => 'John Doe',
            ]);

            $response = $this->getJson('/api/v1/job-offers/by-candidate/John');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $jobOffer->id,
                        'candidate_name' => 'John Doe',
                    ],
                ]);
        });

        it('returns 404 for non-existent candidate', function () {
            $response = $this->getJson('/api/v1/job-offers/by-candidate/Non Existent');

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Job offer not found',
                ]);
        });
    });
});
