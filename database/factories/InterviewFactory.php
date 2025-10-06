<?php

namespace Database\Factories;

use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 17);
        $startMinute = fake()->numberBetween(0, 59);
        $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);

        $startDateTime = \DateTime::createFromFormat('H:i:s', $startTime);
        $endDateTime = clone $startDateTime;
        $endDateTime->add(new \DateInterval('PT'.fake()->numberBetween(30, 120).'M'));

        return [
            'candidate_name' => fake()->name(),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'job_position' => fake()->randomElement([
                'Software Engineer', 'Project Manager', 'Data Scientist', 'HR Specialist',
                'Marketing Manager', 'Sales Representative', 'Accountant', 'UI/UX Designer',
            ]),
            'interviewer_name' => fake()->optional(0.9)->name(),
            'interview_date' => fake()->dateTimeBetween('-3 months', '+1 month')->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endDateTime->format('H:i:s'),
            'interview_mode' => fake()->randomElement(['Online', 'In-Person', 'Phone', 'Video Call']),
            'interview_status' => fake()->randomElement(['Scheduled', 'Completed', 'Cancelled']),
            'hired_status' => fake()->randomElement(['Hired', 'Not Hired', 'Pending']),
            'score' => fake()->optional(0.7)->randomFloat(2, 0, 100),
            'feedback' => fake()->optional(0.6)->sentence(),
            'reference_info' => fake()->optional(0.3)->sentence(),
            'created_by' => fake()->optional(0.8)->name(),
            'updated_by' => fake()->optional(0.4)->name(),
        ];
    }
}
