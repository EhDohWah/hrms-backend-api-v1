<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => 'EMP'.$this->faker->unique()->numberBetween(1000, 9999),
            'organization' => $this->faker->randomElement(['SMRU', 'BHF']),
            'first_name_en' => $this->faker->firstName(),
            'last_name_en' => $this->faker->lastName(),
            'first_name_th' => null,
            'last_name_th' => null,
            'gender' => $this->faker->randomElement(['Male', 'Female']),
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['Expats', 'Local ID', 'Local non ID']),
            'nationality' => $this->faker->country(),
            'religion' => $this->faker->randomElement(['Buddhism', 'Christianity', 'Islam', 'Hinduism', 'Other']),
            'mobile_phone' => $this->faker->phoneNumber(),
            'permanent_address' => $this->faker->address(),
            'current_address' => $this->faker->address(),
            'military_status' => $this->faker->boolean(),
            'marital_status' => $this->faker->randomElement(['Single', 'Married', 'Divorced', 'Widowed']),
            'emergency_contact_person_name' => $this->faker->name(),
            'emergency_contact_person_relationship' => $this->faker->randomElement(['Parent', 'Spouse', 'Sibling', 'Friend']),
            'emergency_contact_person_phone' => $this->faker->phoneNumber(),
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the employee is from SMRU organization.
     */
    public function smru(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization' => 'SMRU',
        ]);
    }

    /**
     * Indicate that the employee is from BHF organization.
     */
    public function bhf(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization' => 'BHF',
        ]);
    }

    /**
     * Indicate that the employee is male.
     */
    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 'Male',
        ]);
    }

    /**
     * Indicate that the employee is female.
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 'Female',
        ]);
    }
}
