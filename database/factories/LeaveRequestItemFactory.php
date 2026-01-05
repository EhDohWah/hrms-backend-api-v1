<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestItem;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveRequestItem>
 */
class LeaveRequestItemFactory extends Factory
{
    protected $model = LeaveRequestItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'leave_type_id' => LeaveType::factory(),
            'days' => $this->faker->randomFloat(2, 0.5, 5),
        ];
    }

    /**
     * Indicate the item is for a specific leave request.
     */
    public function forLeaveRequest(LeaveRequest $leaveRequest): static
    {
        return $this->state(fn (array $attributes) => [
            'leave_request_id' => $leaveRequest->id,
        ]);
    }

    /**
     * Indicate the item is for a specific leave type.
     */
    public function forLeaveType(LeaveType $leaveType): static
    {
        return $this->state(fn (array $attributes) => [
            'leave_type_id' => $leaveType->id,
        ]);
    }

    /**
     * Indicate the number of days for this item.
     */
    public function withDays(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'days' => $days,
        ]);
    }
}
