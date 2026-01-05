<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Site',
            'code' => strtoupper(fake()->lexify('???')),
            'description' => fake()->sentence(),
            'address' => fake()->address(),
            'contact_person' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->safeEmail(),
            'is_active' => true,
            'created_by' => 'Test',
            'updated_by' => 'Test',
        ];
    }
}
