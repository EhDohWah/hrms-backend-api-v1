<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clockIn = $this->faker->dateTimeBetween('08:00', '10:00')->format('H:i');
        $clockOut = $this->faker->dateTimeBetween('16:00', '18:00')->format('H:i');

        return [
            'employee_id' => Employee::factory(),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'status' => $this->faker->randomElement(AttendanceStatus::values()),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the employee is present.
     */
    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    /**
     * Indicate that the employee is absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Absent->value,
            'clock_in' => null,
            'clock_out' => null,
        ]);
    }

    /**
     * Indicate that the employee was late.
     */
    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Late->value,
        ]);
    }

    /**
     * Set a specific date for the attendance.
     */
    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
