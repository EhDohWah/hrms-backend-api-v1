<?php

namespace Database\Factories;

use App\Enums\IdentificationType;
use App\Models\Employee;
use App\Models\EmployeeIdentification;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeIdentificationFactory extends Factory
{
    protected $model = EmployeeIdentification::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'identification_type' => $this->faker->randomElement(IdentificationType::values()),
            'identification_number' => $this->faker->unique()->numerify('ID-########'),
            'identification_issue_date' => $this->faker->dateTimeBetween('-5 years', '-1 year'),
            'identification_expiry_date' => $this->faker->dateTimeBetween('+1 year', '+5 years'),
            'first_name_en' => $this->faker->firstName(),
            'last_name_en' => $this->faker->lastName(),
            'is_primary' => false,
            'created_by' => 'factory',
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
