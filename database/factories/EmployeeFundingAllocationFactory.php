<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\GrantItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeFundingAllocation>
 */
class EmployeeFundingAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'employee_id' => Employee::factory(),
            'employment_id' => Employment::factory(),
            'grant_item_id' => GrantItem::factory(),
            'fte' => fake()->randomElement([0.25, 0.40, 0.50, 0.60, 0.75, 1.00]),
            'allocated_amount' => fake()->numberBetween(15000, 50000),
            'salary_type' => fake()->randomElement(['probation_salary', 'pass_probation_salary']),
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => null,
        ];
    }
}
