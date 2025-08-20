<?php

namespace Database\Factories;

use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobOffer>
 */
class JobOfferFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = JobOffer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $positions = [
            'Software Developer',
            'Senior Software Engineer',
            'Project Manager',
            'Data Analyst',
            'UI/UX Designer',
            'DevOps Engineer',
            'Quality Assurance Engineer',
            'Business Analyst',
            'System Administrator',
            'Database Administrator',
            'Frontend Developer',
            'Backend Developer',
            'Full Stack Developer',
            'Mobile App Developer',
            'Machine Learning Engineer',
            'Cybersecurity Specialist',
            'Technical Writer',
            'Product Manager',
            'Scrum Master',
            'Solutions Architect',
        ];

        $statuses = [
            'Pending',
            'Accepted',
            'Declined',
            'Expired',
            'Withdrawn',
            'Under Review',
        ];

        $salaryRanges = [
            '$45,000 - $60,000 per annum',
            '$50,000 - $70,000 per annum',
            '$60,000 - $80,000 per annum',
            '$70,000 - $90,000 per annum',
            '$80,000 - $100,000 per annum',
            '$90,000 - $120,000 per annum',
            '$100,000 - $130,000 per annum',
            '$110,000 - $150,000 per annum',
        ];

        $offerDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $acceptanceDeadline = $this->faker->dateTimeBetween($offerDate, '+30 days');

        return [
            'custom_offer_id' => $this->generateCustomOfferId(),
            'date' => $offerDate,
            'candidate_name' => $this->faker->name(),
            'position_name' => $this->faker->randomElement($positions),
            'salary_detail' => $this->faker->randomElement($salaryRanges),
            'acceptance_deadline' => $acceptanceDeadline,
            'acceptance_status' => $this->faker->randomElement($statuses),
            'note' => $this->faker->optional(0.7)->sentence(10) ?? 'Standard job offer with competitive benefits package.',
            'created_by' => $this->faker->randomElement(['admin', 'hr_manager', 'system']),
            'updated_by' => $this->faker->randomElement(['admin', 'hr_manager', 'system']),
        ];
    }

    /**
     * Generate a unique custom offer ID.
     */
    private function generateCustomOfferId(): string
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $subsidiaries = ['SMRU', 'BHF', 'MORU', 'SHOKLO'];
        $subsidiary = $this->faker->randomElement($subsidiaries);
        $counter = $this->faker->numberBetween(1, 9999);

        return sprintf('%s%s%s-%s-%04d', $year, $month, $day, $subsidiary, $counter);
    }

    /**
     * Indicate that the job offer is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'acceptance_status' => 'Pending',
        ]);
    }

    /**
     * Indicate that the job offer is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'acceptance_status' => 'Accepted',
        ]);
    }

    /**
     * Indicate that the job offer is declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'acceptance_status' => 'Declined',
        ]);
    }

    /**
     * Indicate that the job offer is for a senior position.
     */
    public function seniorPosition(): static
    {
        $seniorPositions = [
            'Senior Software Engineer',
            'Lead Developer',
            'Senior Project Manager',
            'Senior Data Analyst',
            'Senior UI/UX Designer',
            'Solutions Architect',
        ];

        $seniorSalaries = [
            '$90,000 - $120,000 per annum',
            '$100,000 - $130,000 per annum',
            '$110,000 - $150,000 per annum',
            '$120,000 - $160,000 per annum',
        ];

        return $this->state(fn (array $attributes) => [
            'position_name' => $this->faker->randomElement($seniorPositions),
            'salary_detail' => $this->faker->randomElement($seniorSalaries),
        ]);
    }
}
