<?php

use App\Models\Interview;

describe('Interview Model', function () {
    it('can create an interview with factory', function () {
        $interview = Interview::factory()->create();

        expect($interview)->toBeInstanceOf(Interview::class)
            ->and($interview->candidate_name)->toBeString()
            ->and($interview->job_position)->toBeString()
            ->and($interview->exists)->toBeTrue();
    });

    it('has correct fillable attributes', function () {
        $fillable = [
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
        ];

        $interview = new Interview;

        expect($interview->getFillable())->toEqual($fillable);
    });

    describe('Date Mutators', function () {
        it('formats interview_date correctly', function () {
            $interview = new Interview;
            $interview->interview_date = '2024-12-25T10:30:00Z';

            expect($interview->interview_date)->toBe('2024-12-25');
        });

        it('handles null interview_date', function () {
            $interview = new Interview;
            $interview->interview_date = null;

            expect($interview->interview_date)->toBeNull();
        });

        it('handles empty interview_date', function () {
            $interview = new Interview;
            $interview->interview_date = '';

            expect($interview->interview_date)->toBeNull();
        });
    });

    describe('Time Mutators', function () {
        it('formats start_time correctly', function () {
            $interview = new Interview;
            $interview->start_time = '10:30:45';

            expect($interview->start_time)->toBe('10:30:45');
        });

        it('formats start_time from datetime', function () {
            $interview = new Interview;
            $interview->start_time = '2024-12-25 10:30:45';

            expect($interview->start_time)->toBe('10:30:45');
        });

        it('handles null start_time', function () {
            $interview = new Interview;
            $interview->start_time = null;

            expect($interview->start_time)->toBeNull();
        });

        it('handles empty start_time', function () {
            $interview = new Interview;
            $interview->start_time = '';

            expect($interview->start_time)->toBeNull();
        });

        it('formats end_time correctly', function () {
            $interview = new Interview;
            $interview->end_time = '12:30:45';

            expect($interview->end_time)->toBe('12:30:45');
        });

        it('formats end_time from datetime', function () {
            $interview = new Interview;
            $interview->end_time = '2024-12-25 12:30:45';

            expect($interview->end_time)->toBe('12:30:45');
        });

        it('handles null end_time', function () {
            $interview = new Interview;
            $interview->end_time = null;

            expect($interview->end_time)->toBeNull();
        });

        it('handles empty end_time', function () {
            $interview = new Interview;
            $interview->end_time = '';

            expect($interview->end_time)->toBeNull();
        });
    });

    describe('Factory States', function () {
        it('creates interview with valid data structure', function () {
            $interview = Interview::factory()->create();

            expect($interview->candidate_name)->toBeString()
                ->and($interview->job_position)->toBeString()
                ->and($interview->interview_mode)->toBeIn(['Online', 'In-Person', 'Phone', 'Video Call'])
                ->and($interview->interview_status)->toBeIn(['Scheduled', 'Completed', 'Cancelled'])
                ->and($interview->hired_status)->toBeIn(['Hired', 'Not Hired', 'Pending']);
        });

        it('creates interview with optional fields', function () {
            $interview = Interview::factory()->create([
                'phone' => '1234567890',
                'score' => 85.5,
                'feedback' => 'Great candidate',
            ]);

            expect($interview->phone)->toBe('1234567890')
                ->and($interview->score)->toBe(85.5)
                ->and($interview->feedback)->toBe('Great candidate');
        });
    });

    describe('Database Operations', function () {
        it('can create interview with minimum required fields', function () {
            $interview = Interview::create([
                'candidate_name' => 'John Doe',
                'job_position' => 'Software Engineer',
            ]);

            expect($interview->exists)->toBeTrue()
                ->and($interview->candidate_name)->toBe('John Doe')
                ->and($interview->job_position)->toBe('Software Engineer');
        });

        it('can update interview fields', function () {
            $interview = Interview::factory()->create();

            $interview->update([
                'interview_status' => 'Completed',
                'hired_status' => 'Hired',
                'score' => 95.0,
            ]);

            expect($interview->interview_status)->toBe('Completed')
                ->and($interview->hired_status)->toBe('Hired')
                ->and($interview->score)->toBe(95.0);
        });

        it('can delete interview', function () {
            $interview = Interview::factory()->create();
            $interviewId = $interview->id;

            $interview->delete();

            expect(Interview::find($interviewId))->toBeNull();
        });
    });

    describe('Traits', function () {
        it('uses HasFactory trait', function () {
            expect(Interview::factory())->toBeInstanceOf(\Database\Factories\InterviewFactory::class);
        });

        it('uses KeepsDeletedModels trait', function () {
            $traits = class_uses_recursive(Interview::class);

            expect($traits)->toHaveKey('Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels');
        });
    });
});
