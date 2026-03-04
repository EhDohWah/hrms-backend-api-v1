<?php

namespace Database\Factories;

use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Training>
 */
class TrainingFactory extends Factory
{
    protected $model = Training::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $trainings = [
            'First Aid & CPR Training' => 'Red Cross',
            'Leadership Development Program' => 'Internal HR',
            'Data Analysis Workshop' => 'DataCamp',
            'Project Management Fundamentals' => 'PMI',
            'Communication Skills Training' => 'Internal HR',
            'Cybersecurity Awareness' => 'IT Department',
            'Fire Safety Training' => 'Safety Division',
            'Excel Advanced Course' => 'Microsoft',
        ];

        $title = $this->faker->randomElement(array_keys($trainings));
        $startDate = $this->faker->dateTimeBetween('-60 days', '+30 days');
        $endDate = (clone $startDate)->modify('+'.$this->faker->numberBetween(1, 5).' days');

        return [
            'title' => $title.' '.$this->faker->unique()->numberBetween(1, 9999),
            'organizer' => $trainings[$title],
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'created_by' => 'Factory',
            'updated_by' => 'Factory',
        ];
    }
}
