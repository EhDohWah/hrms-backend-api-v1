<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'display_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['users', 'briefcase', 'calendar', 'chart-bar', 'cog', 'document']),
            'category' => fake()->randomElement(['Administration', 'HR', 'Payroll', 'Reports']),
            'route' => '/'.$name,
            'read_permission' => $name.'.read',
            'edit_permissions' => [
                $name.'.create',
                $name.'.update',
                $name.'.delete',
            ],
            'order' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'parent_id' => null,
        ];
    }

    /**
     * Indicate that the module is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the module is a child module.
     */
    public function childOf(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}
