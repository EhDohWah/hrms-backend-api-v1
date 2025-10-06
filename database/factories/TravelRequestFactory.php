<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TravelRequest>
 */
class TravelRequestFactory extends Factory
{
    protected $model = TravelRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+60 days');
        $endDate = (clone $startDate)->modify('+'.$this->faker->numberBetween(1, 14).' days');

        $destinations = [
            'Bangkok, Thailand',
            'Chiang Mai, Thailand',
            'Phuket, Thailand',
            'Singapore',
            'Kuala Lumpur, Malaysia',
            'Jakarta, Indonesia',
            'Manila, Philippines',
            'Ho Chi Minh City, Vietnam',
            'Yangon, Myanmar',
            'Phnom Penh, Cambodia',
            'Vientiane, Laos',
            'New York, USA',
            'London, UK',
            'Tokyo, Japan',
            'Sydney, Australia',
        ];

        $purposes = [
            'Business meeting with clients',
            'Conference attendance',
            'Training and development',
            'Project implementation',
            'Site inspection and evaluation',
            'Partnership negotiations',
            'Research collaboration',
            'Technical support and consultation',
            'Market research and analysis',
            'Team building and workshops',
        ];

        $grants = [
            'Company funded',
            'Project grant',
            'External funding',
            'Self-funded',
            'Donor funded',
            'Government grant',
        ];

        return [
            'employee_id' => Employee::factory(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'destination' => $this->faker->randomElement($destinations),
            'start_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d'),
            'purpose' => $this->faker->randomElement($purposes),
            'grant' => $this->faker->randomElement($grants),
            'transportation' => $this->faker->randomElement(['smru_vehicle', 'public_transportation', 'air', 'other']),
            'transportation_other_text' => $this->faker->optional(0.2)->sentence(),
            'accommodation' => $this->faker->randomElement(['smru_arrangement', 'self_arrangement', 'other']),
            'accommodation_other_text' => $this->faker->optional(0.2)->sentence(),
            'request_by_date' => $this->faker->optional()->dateTimeBetween('-30 days', 'now')?->format('Y-m-d'),
            'supervisor_approved' => $this->faker->boolean(),
            'supervisor_approved_date' => $this->faker->optional()->dateTimeBetween('-30 days', 'now')?->format('Y-m-d'),
            'hr_acknowledged' => $this->faker->boolean(),
            'hr_acknowledgement_date' => $this->faker->optional()->dateTimeBetween('-30 days', 'now')?->format('Y-m-d'),
            'remarks' => $this->faker->optional()->sentence(),
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the travel request is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_approved' => false,
            'supervisor_approved_date' => null,
            'hr_acknowledged' => false,
            'hr_acknowledgement_date' => null,
        ]);
    }

    /**
     * Indicate that the travel request is approved by supervisor.
     */
    public function supervisorApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_approved' => true,
            'supervisor_approved_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the travel request is acknowledged by HR.
     */
    public function hrAcknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'hr_acknowledged' => true,
            'hr_acknowledgement_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the travel request is fully approved.
     */
    public function fullyApproved(): static
    {
        $approvalDate = $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d');

        return $this->state(fn (array $attributes) => [
            'supervisor_approved' => true,
            'supervisor_approved_date' => $approvalDate,
            'hr_acknowledged' => true,
            'hr_acknowledgement_date' => $approvalDate,
        ]);
    }

    /**
     * Indicate that the travel request is for air transportation.
     */
    public function airTransportation(): static
    {
        return $this->state(fn (array $attributes) => [
            'transportation' => 'air',
            'transportation_other_text' => null,
        ]);
    }

    /**
     * Indicate that the travel request uses other transportation.
     */
    public function otherTransportation(?string $description = null): static
    {
        return $this->state(fn (array $attributes) => [
            'transportation' => 'other',
            'transportation_other_text' => $description ?? $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the travel request uses SMRU arrangement for accommodation.
     */
    public function smruAccommodation(): static
    {
        return $this->state(fn (array $attributes) => [
            'accommodation' => 'smru_arrangement',
            'accommodation_other_text' => null,
        ]);
    }

    /**
     * Indicate that the travel request uses other accommodation.
     */
    public function otherAccommodation(?string $description = null): static
    {
        return $this->state(fn (array $attributes) => [
            'accommodation' => 'other',
            'accommodation_other_text' => $description ?? $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the travel request is for a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Indicate that the travel request is for a specific department.
     */
    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
        ]);
    }

    /**
     * Indicate that the travel request is for a specific position.
     */
    public function forPosition(Position $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position_id' => $position->id,
        ]);
    }

    /**
     * Indicate that the travel request is for specific dates.
     */
    public function forDates(string $startDate, string $endDate): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'to_date' => $endDate,
        ]);
    }

    /**
     * Indicate that the travel request is for a specific destination.
     */
    public function toDestination(string $destination): static
    {
        return $this->state(fn (array $attributes) => [
            'destination' => $destination,
        ]);
    }
}
