<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Site;
use Carbon\Carbon;
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
        $startDate = fake()->dateTimeBetween('-2 years', '-1 month');
        $passProbationDate = Carbon::parse($startDate)->addMonths(3);
        $passProbationSalary = fake()->numberBetween(25000, 80000);

        return [
            'employee_id' => Employee::factory(),
            'pay_method' => fake()->randomElement(['Transferred to bank', 'Cash cheque']),
            'pass_probation_date' => $passProbationDate->format('Y-m-d'),
            'start_date' => $startDate,
            'end_probation_date' => null,
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'site_id' => Site::factory(),
            'pass_probation_salary' => $passProbationSalary,
            'probation_salary' => $passProbationSalary * 0.8, // 80% of pass_probation_salary
            'probation_required' => true,
            'health_welfare' => fake()->boolean(70),
            'pvd' => fake()->boolean(60),
            'saving_fund' => fake()->boolean(40),
        ];
    }

    /**
     * Configure the model factory.
     *
     * Ensures pass_probation_date is always within 6 months of start_date,
     * even when start_date is overridden by the caller.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (\App\Models\Employment $employment) {
            $startDate = Carbon::parse($employment->start_date);
            $passProbationDate = Carbon::parse($employment->pass_probation_date);

            // If pass_probation_date is not within 6 months of start_date,
            // recalculate it to be 3 months after start_date
            if ($passProbationDate->lte($startDate) || $passProbationDate->gt($startDate->copy()->addMonths(6))) {
                $employment->pass_probation_date = $startDate->copy()->addMonths(3)->format('Y-m-d');
            }
        });
    }
}
