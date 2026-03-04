<?php

namespace Database\Factories;

use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lookup>
 */
class LookupFactory extends Factory
{
    protected $model = Lookup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            'gender' => ['Male', 'Female', 'Other'],
            'organization' => ['SMRU', 'BHF'],
            'religion' => ['Buddhism', 'Christianity', 'Islam', 'Hinduism', 'Other'],
            'marital_status' => ['Single', 'Married', 'Divorced', 'Widowed'],
            'nationality' => ['Thai', 'Myanmar', 'Cambodian', 'Laotian'],
        ];

        $type = $this->faker->randomElement(array_keys($types));

        return [
            'type' => $type,
            'value' => $this->faker->randomElement($types[$type]).' '.$this->faker->unique()->numberBetween(1, 9999),
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Create a lookup for a specific type.
     */
    public function forType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
