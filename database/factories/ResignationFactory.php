<?php

namespace Database\Factories;

use App\Enums\ResignationAcknowledgementStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Resignation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Resignation>
 */
class ResignationFactory extends Factory
{
    protected $model = Resignation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasons = [
            'Career Advancement',
            'Personal Reasons',
            'Relocation',
            'Health Issues',
            'Better Compensation',
            'Work-Life Balance',
            'Further Education',
            'Family Reasons',
        ];

        $resignationDate = $this->faker->dateTimeBetween('-30 days', 'now');
        $lastWorkingDate = (clone $resignationDate)->modify('+30 days');

        return [
            'employee_id' => Employee::factory(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'resignation_date' => $resignationDate->format('Y-m-d'),
            'last_working_date' => $lastWorkingDate->format('Y-m-d'),
            'reason' => $this->faker->randomElement($reasons),
            'reason_details' => $this->faker->optional(0.7)->paragraph(),
            'acknowledgement_status' => ResignationAcknowledgementStatus::Pending,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the resignation has been acknowledged.
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledgement_status' => ResignationAcknowledgementStatus::Acknowledged,
            'acknowledged_by' => User::factory(),
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Indicate that the resignation has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledgement_status' => ResignationAcknowledgementStatus::Rejected,
            'acknowledged_by' => User::factory(),
            'acknowledged_at' => now(),
        ]);
    }
}
