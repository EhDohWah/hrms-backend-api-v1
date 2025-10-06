<?php

use App\Http\Requests\InterviewRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Interview Request Validation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        // Create permissions and assign to user
        Permission::firstOrCreate(['name' => 'interview.create']);
        $this->user->givePermissionTo('interview.create');

        $this->actingAs($this->user);
    });

    describe('Required Fields', function () {
        it('requires candidate_name', function () {
            $data = [
                'job_position' => 'Software Engineer',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['candidate_name']);
        });

        it('requires job_position', function () {
            $data = [
                'candidate_name' => 'John Doe',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['job_position']);
        });

        it('passes with only required fields', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });
    });

    describe('Field Length Validation', function () {
        it('validates candidate_name max length', function () {
            $data = [
                'candidate_name' => str_repeat('a', 256), // Exceeds 255 chars
                'job_position' => 'Software Engineer',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['candidate_name']);
        });

        it('validates job_position max length', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => str_repeat('a', 256), // Exceeds 255 chars
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['job_position']);
        });

        it('validates phone max length', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'phone' => '12345678901', // Exceeds 10 chars
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['phone']);
        });

        it('accepts valid phone length', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'phone' => '1234567890', // Exactly 10 chars
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });
    });

    describe('Date and Time Validation', function () {
        it('validates interview_date format', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'interview_date' => 'invalid-date',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['interview_date']);
        });

        it('accepts valid interview_date', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'interview_date' => '2024-12-25',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });

        it('validates start_time format', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'start_time' => 'invalid-time',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['start_time']);
        });

        it('accepts valid start_time', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'start_time' => '10:30:00',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });

        it('validates end_time format', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'end_time' => 'invalid-time',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['end_time']);
        });

        it('validates end_time is after start_time', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'start_time' => '11:00:00',
                'end_time' => '10:00:00', // Before start time
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['end_time']);
        });

        it('accepts valid time range', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });

        it('allows end_time without start_time', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'end_time' => '11:00:00',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });
    });

    describe('Score Validation', function () {
        it('validates score is numeric', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'score' => 'not-a-number',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['score']);
        });

        it('validates score minimum value', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'score' => -1,
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['score']);
        });

        it('validates score maximum value', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'score' => 101,
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['score']);
        });

        it('accepts valid score at minimum boundary', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'score' => 0,
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });

        it('accepts valid score at maximum boundary', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'score' => 100,
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });

        it('accepts decimal scores', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'score' => 85.5,
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });
    });

    describe('Optional Fields', function () {
        it('accepts null values for optional fields', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'phone' => null,
                'interviewer_name' => null,
                'interview_date' => null,
                'start_time' => null,
                'end_time' => null,
                'interview_mode' => null,
                'interview_status' => null,
                'hired_status' => null,
                'score' => null,
                'feedback' => null,
                'reference_info' => null,
                'created_by' => null,
                'updated_by' => null,
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });

        it('accepts string values for text fields', function () {
            $data = [
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
                'interviewer_name' => 'Jane Smith',
                'interview_mode' => 'Online',
                'interview_status' => 'Scheduled',
                'hired_status' => 'Pending',
                'feedback' => 'Great candidate with excellent technical skills',
                'reference_info' => 'Referred by current employee',
                'created_by' => 'HR Manager',
                'updated_by' => 'Senior HR',
            ];

            $response = $this->postJson('/api/v1/interviews', $data);

            $response->assertStatus(201);
        });
    });

    describe('Request Class Validation', function () {
        it('has correct validation rules', function () {
            $request = new InterviewRequest;
            $rules = $request->rules();

            expect($rules)->toHaveKey('candidate_name')
                ->and($rules['candidate_name'])->toContain('required')
                ->and($rules['candidate_name'])->toContain('string')
                ->and($rules['candidate_name'])->toContain('max:255')
                ->and($rules)->toHaveKey('job_position')
                ->and($rules['job_position'])->toContain('required')
                ->and($rules['job_position'])->toContain('string')
                ->and($rules['job_position'])->toContain('max:255')
                ->and($rules)->toHaveKey('phone')
                ->and($rules['phone'])->toContain('nullable')
                ->and($rules['phone'])->toContain('string')
                ->and($rules['phone'])->toContain('max:10')
                ->and($rules)->toHaveKey('score')
                ->and($rules['score'])->toContain('nullable')
                ->and($rules['score'])->toContain('numeric')
                ->and($rules['score'])->toContain('between:0,100');
        });

        it('authorizes all requests', function () {
            $request = new InterviewRequest;

            expect($request->authorize())->toBeTrue();
        });
    });

    describe('Direct Validator Testing', function () {
        it('validates complete valid data', function () {
            $data = [
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
                'feedback' => 'Excellent candidate',
                'reference_info' => 'Internal referral',
                'created_by' => 'HR Manager',
                'updated_by' => 'Senior HR',
            ];

            $request = new InterviewRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        it('fails validation with invalid data', function () {
            $data = [
                'candidate_name' => '', // Required but empty
                'phone' => '12345678901', // Too long
                'job_position' => str_repeat('a', 256), // Too long
                'interview_date' => 'invalid-date',
                'start_time' => 'invalid-time',
                'end_time' => '09:00:00',
                'score' => 150, // Out of range
            ];

            $request = new InterviewRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue()
                ->and($validator->errors()->has('candidate_name'))->toBeTrue()
                ->and($validator->errors()->has('phone'))->toBeTrue()
                ->and($validator->errors()->has('job_position'))->toBeTrue()
                ->and($validator->errors()->has('interview_date'))->toBeTrue()
                ->and($validator->errors()->has('start_time'))->toBeTrue()
                ->and($validator->errors()->has('score'))->toBeTrue();
        });
    });
});
