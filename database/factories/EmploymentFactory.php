<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employment>
 */
class EmploymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-2 years', 'now');
        $passProbationSalary = fake()->numberBetween(25000, 80000);

        return [
            'employee_id' => Employee::factory(),
            'pay_method' => fake()->randomElement(['Transferred to bank', 'Cash cheque']),
            'pass_probation_date' => fake()->dateTimeBetween($startDate, '+6 months'),
            'start_date' => $startDate,
            'end_date' => null,
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'site_id' => Site::factory(),
            'pass_probation_salary' => $passProbationSalary,
            'probation_salary' => $passProbationSalary * 0.8, // 80% of pass_probation_salary
            'health_welfare' => fake()->boolean(70),
            'pvd' => fake()->boolean(60),
            'saving_fund' => fake()->boolean(40),
            'status' => true, // Active by default
        ];
    }
}
