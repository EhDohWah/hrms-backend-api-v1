<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+30 days');
        $endDate = (clone $startDate)->modify('+'.$this->faker->numberBetween(1, 5).' days');
        $totalDays = $startDate->diff($endDate)->days + 1;

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_days' => $totalDays,
            'reason' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'declined', 'cancelled']),
            'supervisor_approved' => $this->faker->boolean(),
            'supervisor_approved_date' => $this->faker->optional()->dateTimeBetween('-30 days', 'now')?->format('Y-m-d'),
            'hr_site_admin_approved' => $this->faker->boolean(),
            'hr_site_admin_approved_date' => $this->faker->optional()->dateTimeBetween('-30 days', 'now')?->format('Y-m-d'),
            'attachment_notes' => $this->faker->optional()->sentence(),
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the leave request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'supervisor_approved' => false,
            'supervisor_approved_date' => null,
            'hr_site_admin_approved' => false,
            'hr_site_admin_approved_date' => null,
        ]);
    }

    /**
     * Indicate that the leave request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'supervisor_approved' => true,
            'supervisor_approved_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'hr_site_admin_approved' => true,
            'hr_site_admin_approved_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the leave request is declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'declined',
            'supervisor_approved' => false,
            'hr_site_admin_approved' => false,
        ]);
    }

    /**
     * Indicate that the leave request is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the leave request is for a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Indicate that the leave request is for a specific leave type.
     */
    public function forLeaveType(LeaveType $leaveType): static
    {
        return $this->state(fn (array $attributes) => [
            'leave_type_id' => $leaveType->id,
        ]);
    }

    /**
     * Indicate that the leave request is for specific dates.
     */
    public function forDates(string $startDate, string $endDate): static
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $totalDays = $start->diffInDays($end) + 1;

        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
        ]);
    }

    /**
     * Indicate that the leave request has full approvals.
     */
    public function fullyApproved(): static
    {
        $approvalDate = $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d');

        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'supervisor_approved' => true,
            'supervisor_approved_date' => $approvalDate,
            'hr_site_admin_approved' => true,
            'hr_site_admin_approved_date' => $approvalDate,
        ]);
    }
}
