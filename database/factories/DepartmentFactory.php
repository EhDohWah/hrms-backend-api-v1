<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = [
            'Information Technology' => 'Manages IT infrastructure and software development',
            'Human Resources' => 'Handles employee relations and organizational development',
            'Finance' => 'Manages financial operations and accounting',
            'Operations' => 'Oversees daily business operations and logistics',
            'Research & Development' => 'Conducts research and develops new products',
            'Marketing' => 'Handles marketing campaigns and brand management',
            'Sales' => 'Manages sales operations and customer relationships',
            'Administration' => 'Provides administrative support and office management',
            'Quality Assurance' => 'Ensures product and service quality standards',
            'Legal' => 'Handles legal matters and compliance',
        ];

        $selectedDept = $this->faker->randomElement(array_keys($departments));

        return [
            'name' => $selectedDept.' '.$this->faker->unique()->numberBetween(1, 9999),
            'description' => $departments[$selectedDept],
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the department is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the department is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an IT department.
     */
    public function it(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Information Technology',
            'description' => 'Manages IT infrastructure and software development',
            'is_active' => true,
        ]);
    }

    /**
     * Create an HR department.
     */
    public function hr(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Human Resources',
            'description' => 'Handles employee relations and organizational development',
            'is_active' => true,
        ]);
    }
}
