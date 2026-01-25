<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('now', '+1 year');

        return [
            'name' => $this->faker->randomElement([
                'New Year\'s Day',
                'Labor Day',
                'Independence Day',
                'Christmas Day',
                'Thanksgiving',
                'Memorial Day',
                'Veterans Day',
            ]).' '.$this->faker->year(),
            'name_th' => null,
            'date' => $date,
            'year' => (int) $date->format('Y'),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'created_by' => 'Factory',
        ];
    }

    /**
     * Indicate that the holiday is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a holiday for a specific date.
     */
    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
            'year' => (int) date('Y', strtotime($date)),
        ]);
    }

    /**
     * Create a holiday for a specific year.
     */
    public function forYear(int $year): static
    {
        $date = $this->faker->dateTimeBetween("$year-01-01", "$year-12-31");

        return $this->state(fn (array $attributes) => [
            'date' => $date,
            'year' => $year,
        ]);
    }

    /**
     * Create a New Year's Day holiday.
     */
    public function newYearsDay(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'New Year\'s Day',
            'name_th' => 'วันขึ้นปีใหม่',
            'date' => "$year-01-01",
            'year' => $year,
            'description' => 'First day of the new year',
        ]);
    }

    /**
     * Create a Thai holiday with Thai name.
     */
    public function thai(): static
    {
        $thaiHolidays = [
            ['name' => 'Songkran Festival', 'name_th' => 'วันสงกรานต์', 'month' => 4, 'day' => 13],
            ['name' => 'Chakri Memorial Day', 'name_th' => 'วันจักรี', 'month' => 4, 'day' => 6],
            ['name' => 'Coronation Day', 'name_th' => 'วันฉัตรมงคล', 'month' => 5, 'day' => 4],
            ['name' => 'Queen\'s Birthday', 'name_th' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าฯ', 'month' => 6, 'day' => 3],
            ['name' => 'King\'s Birthday', 'name_th' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว', 'month' => 7, 'day' => 28],
        ];

        $holiday = $this->faker->randomElement($thaiHolidays);
        $year = $this->faker->year();

        return $this->state(fn (array $attributes) => [
            'name' => $holiday['name'],
            'name_th' => $holiday['name_th'],
            'date' => sprintf('%d-%02d-%02d', $year, $holiday['month'], $holiday['day']),
            'year' => (int) $year,
        ]);
    }
}
