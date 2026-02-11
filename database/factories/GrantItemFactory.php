<?php

namespace Database\Factories;

use App\Models\Grant;
use App\Models\GrantItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GrantItem>
 */
class GrantItemFactory extends Factory
{
    protected $model = GrantItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $positions = [
            'Research Assistant',
            'Project Manager',
            'Senior Researcher',
            'Data Analyst',
            'Field Officer',
            'Lab Technician',
            'Nurse',
            'Doctor',
            'Program Coordinator',
            'Administrative Assistant',
            'Finance Officer',
            'IT Specialist',
            'Communications Officer',
            'Monitoring & Evaluation Officer',
        ];

        $salary = $this->faker->numberBetween(30000, 150000);
        $benefit = $this->faker->numberBetween(5000, 25000);
        $levelOfEffort = $this->faker->randomElement([0.25, 0.5, 0.75, 1.0]);
        $positionNumber = $this->faker->numberBetween(1, 5);

        return [
            'grant_id' => Grant::factory(),
            'grant_position' => $this->faker->randomElement($positions),
            'grant_salary' => $salary,
            'grant_benefit' => $benefit,
            'grant_level_of_effort' => $levelOfEffort,
            'grant_position_number' => $positionNumber,
            'budgetline_code' => $this->faker->unique()->numerify('BL-####'),
            'created_by' => $this->faker->randomElement(['admin', 'system', 'grant_manager']),
            'updated_by' => $this->faker->randomElement(['admin', 'system', 'grant_manager']),
        ];
    }

    /**
     * Create a grant item for a specific grant
     */
    public function forGrant(Grant $grant): static
    {
        return $this->state(fn (array $attributes) => [
            'grant_id' => $grant->id,
        ]);
    }

    /**
     * Create a full-time position (100% effort)
     */
    public function fullTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'grant_level_of_effort' => 1.0,
        ]);
    }

    /**
     * Create a part-time position (50% effort)
     */
    public function partTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'grant_level_of_effort' => 0.5,
        ]);
    }
}
