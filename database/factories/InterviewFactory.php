<?php

namespace Database\Factories;

use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    public function definition(): array
    {
        $startTime = $this->faker->time('H:i:s', '18:00:00');
        $startDateTime = \DateTime::createFromFormat('H:i:s', $startTime);
        $endDateTime = clone $startDateTime;
        $endDateTime->add(new \DateInterval('PT' . $this->faker->numberBetween(30, 120) . 'M'));

        return [
            'candidate_name' => $this->faker->name(),
            'phone' => $this->faker->optional(0.8)->phoneNumber(),
            'job_position' => $this->faker->randomElement([
                'Software Engineer', 'Project Manager', 'Data Scientist', 'HR Specialist',
                'Marketing Manager', 'Sales Representative', 'Accountant', 'UI/UX Designer'
            ]),
            'interviewer_name' => $this->faker->optional(0.9)->name(),
            'interview_date' => $this->faker->dateTimeBetween('-3 months', '+1 month')->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endDateTime->format('H:i:s'),
            'interview_mode' => $this->faker->randomElement(['Online', 'In-Person', 'Phone', 'Video Call']),
            'interview_status' => $this->faker->randomElement(['Scheduled', 'Completed', 'Cancelled']),
            'hired_status' => $this->faker->randomElement(['Hired', 'Not Hired', 'Pending']),
            'score' => $this->faker->optional(0.7)->randomFloat(2, 0, 100),
            'feedback' => $this->faker->optional(0.6)->sentence(),
            'reference_info' => $this->faker->optional(0.3)->sentence(),
            'created_by' => $this->faker->optional(0.8)->name(),
            'updated_by' => $this->faker->optional(0.4)->name(),
        ];
    }
} 