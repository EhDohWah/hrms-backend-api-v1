<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeEducation>
 */
class EmployeeEducationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-10 years', '-1 year');
        $endDate = $this->faker->dateTimeBetween($startDate, 'now');

        return [
            'employee_id' => \App\Models\Employee::factory(),
            'school_name' => $this->faker->randomElement([
                'Harvard University',
                'Stanford University',
                'MIT',
                'University of California Berkeley',
                'Yale University',
                'Princeton University',
                'Columbia University',
                'University of Chicago',
                'University of Pennsylvania',
                'Cornell University',
            ]),
            'degree' => $this->faker->randomElement([
                'Bachelor of Science in Computer Science',
                'Bachelor of Arts in Business Administration',
                'Master of Science in Engineering',
                'Bachelor of Science in Information Technology',
                'Master of Business Administration',
                'Bachelor of Science in Accounting',
                'Master of Science in Data Science',
                'Bachelor of Arts in Psychology',
                'Master of Science in Finance',
                'Bachelor of Science in Mathematics',
            ]),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'created_by' => $this->faker->name(),
            'updated_by' => $this->faker->name(),
        ];
    }
}
