<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    public function definition(): array
    {
        $fromOrg = $this->faker->randomElement(['SMRU', 'BHF']);
        $toOrg = $fromOrg === 'SMRU' ? 'BHF' : 'SMRU';

        return [
            'employee_id' => Employee::factory(),
            'from_organization' => $fromOrg,
            'to_organization' => $toOrg,
            'from_start_date' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
            'to_start_date' => $this->faker->dateTimeBetween('-5 months', 'now'),
            'reason' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
