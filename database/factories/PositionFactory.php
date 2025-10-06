<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $positions = [
            'Software Developer' => ['level' => 3, 'is_manager' => false],
            'Senior Software Developer' => ['level' => 2, 'is_manager' => false],
            'Team Lead' => ['level' => 2, 'is_manager' => true],
            'Project Manager' => ['level' => 2, 'is_manager' => true],
            'Department Head' => ['level' => 1, 'is_manager' => true],
            'HR Specialist' => ['level' => 3, 'is_manager' => false],
            'HR Manager' => ['level' => 2, 'is_manager' => true],
            'Accountant' => ['level' => 3, 'is_manager' => false],
            'Finance Manager' => ['level' => 2, 'is_manager' => true],
            'Operations Coordinator' => ['level' => 3, 'is_manager' => false],
            'Operations Manager' => ['level' => 2, 'is_manager' => true],
            'Research Analyst' => ['level' => 3, 'is_manager' => false],
            'Research Manager' => ['level' => 2, 'is_manager' => true],
            'Administrative Assistant' => ['level' => 4, 'is_manager' => false],
            'Office Manager' => ['level' => 3, 'is_manager' => true],
        ];

        $selectedPosition = $this->faker->randomElement(array_keys($positions));
        $positionData = $positions[$selectedPosition];

        return [
            'title' => $selectedPosition,
            'department_id' => Department::factory(),
            'reports_to_position_id' => null, // Will be set by relationships if needed
            'level' => $positionData['level'],
            'is_manager' => $positionData['is_manager'],
            'is_active' => $this->faker->boolean(95), // 95% chance of being active
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the position is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the position is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the position is a manager position.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_manager' => true,
            'level' => $this->faker->numberBetween(1, 2), // Managers are typically level 1-2
        ]);
    }

    /**
     * Indicate that the position is not a manager position.
     */
    public function nonManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_manager' => false,
            'level' => $this->faker->numberBetween(3, 5), // Non-managers are typically level 3-5
        ]);
    }

    /**
     * Create a department head position.
     */
    public function departmentHead(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Department Head',
            'level' => 1,
            'is_manager' => true,
            'is_active' => true,
            'reports_to_position_id' => null,
        ]);
    }

    /**
     * Create a software developer position.
     */
    public function softwareDeveloper(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Software Developer',
            'level' => 3,
            'is_manager' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Create a position for a specific department.
     */
    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
        ]);
    }

    /**
     * Create a position at a specific level.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }
}
