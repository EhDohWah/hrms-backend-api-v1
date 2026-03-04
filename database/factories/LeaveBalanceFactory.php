<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalDays = $this->faker->randomFloat(2, 5, 30);
        $usedDays = $this->faker->randomFloat(2, 0, $totalDays);

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'total_days' => $totalDays,
            'used_days' => $usedDays,
            'remaining_days' => round($totalDays - $usedDays, 2),
            'year' => now()->year,
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Set balance for a specific year.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $year,
        ]);
    }

    /**
     * Set a fresh balance with no usage.
     */
    public function fresh(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_days' => 0,
            'remaining_days' => $attributes['total_days'] ?? 15,
        ]);
    }
}
