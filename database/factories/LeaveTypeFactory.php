<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $leaveTypes = [
            'Annual Leave' => ['duration' => 15, 'description' => 'Annual vacation leave', 'attachment' => false],
            'Sick Leave' => ['duration' => 10, 'description' => 'Medical leave for illness', 'attachment' => true],
            'Personal Leave' => ['duration' => 5, 'description' => 'Personal time off', 'attachment' => false],
            'Maternity Leave' => ['duration' => 90, 'description' => 'Maternity leave for new mothers', 'attachment' => true],
            'Paternity Leave' => ['duration' => 7, 'description' => 'Paternity leave for new fathers', 'attachment' => true],
            'Emergency Leave' => ['duration' => 3, 'description' => 'Emergency leave for urgent situations', 'attachment' => true],
            'Study Leave' => ['duration' => 30, 'description' => 'Educational leave for studies', 'attachment' => true],
            'Compassionate Leave' => ['duration' => 5, 'description' => 'Leave for family emergencies', 'attachment' => true],
        ];

        $selectedType = $this->faker->randomElement(array_keys($leaveTypes));
        $typeData = $leaveTypes[$selectedType];

        return [
            'name' => $selectedType,
            'default_duration' => $typeData['duration'],
            'description' => $typeData['description'],
            'requires_attachment' => $typeData['attachment'],
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the leave type requires attachment.
     */
    public function requiresAttachment(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_attachment' => true,
        ]);
    }

    /**
     * Indicate that the leave type does not require attachment.
     */
    public function noAttachmentRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_attachment' => false,
        ]);
    }

    /**
     * Create an annual leave type.
     */
    public function annualLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Annual Leave',
            'default_duration' => 15,
            'description' => 'Annual vacation leave',
            'requires_attachment' => false,
        ]);
    }

    /**
     * Create a sick leave type.
     */
    public function sickLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Sick Leave',
            'default_duration' => 10,
            'description' => 'Medical leave for illness',
            'requires_attachment' => true,
        ]);
    }

    /**
     * Create a personal leave type.
     */
    public function personalLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Personal Leave',
            'default_duration' => 5,
            'description' => 'Personal time off',
            'requires_attachment' => false,
        ]);
    }
}
