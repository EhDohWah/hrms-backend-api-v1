<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\Grant;
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
        $allocationType = fake()->randomElement(['grant', 'org_funded']);
        $startDate = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'employee_id' => Employee::factory(),
            'employment_id' => Employment::factory(),
            'grant_item_id' => $allocationType === 'grant' ? GrantItem::factory() : null,
            'grant_id' => $allocationType === 'org_funded' ? Grant::factory() : null,
            'fte' => fake()->randomElement([0.25, 0.40, 0.50, 0.60, 0.75, 1.00]),
            'allocation_type' => $allocationType,
            'allocated_amount' => fake()->numberBetween(15000, 50000),
            'salary_type' => fake()->randomElement(['probation_salary', 'pass_probation_salary']),
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => null,
        ];
    }

    /**
     * Create a grant-based allocation
     */
    public function grant(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_type' => 'grant',
            'grant_item_id' => GrantItem::factory(),
            'grant_id' => null,
        ]);
    }

    /**
     * Create an org-funded allocation
     */
    public function orgFunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_type' => 'org_funded',
            'grant_item_id' => null,
            'grant_id' => Grant::factory(),
        ]);
    }
}
