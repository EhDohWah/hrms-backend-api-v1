<?php

namespace Database\Factories;

use App\Models\Grant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Grant>
 */
class GrantFactory extends Factory
{
    protected $model = Grant::class;

    public function definition(): array
    {
        $subsidiaries = [
            'SMRU' => [
                'name' => 'Shoklo Malaria Research Unit',
                'prefix' => 'S',
                'locations' => ['Mae Sot', 'Tak Province', 'Thailand'],
            ],
            'BHF' => [
                'name' => 'British Heart Foundation',
                'prefix' => 'B',
                'locations' => ['London', 'Birmingham', 'Glasgow'],
            ],
            'MORU' => [
                'name' => 'Mahidol Oxford Tropical Medicine Research Unit',
                'prefix' => 'M',
                'locations' => ['Bangkok', 'Chiang Mai', 'Phuket'],
            ],
            'OUCRU' => [
                'name' => 'Oxford University Clinical Research Unit',
                'prefix' => 'O',
                'locations' => ['Ho Chi Minh City', 'Hanoi', 'Can Tho'],
            ],
        ];

        $grantTypes = [
            'research' => [
                'names' => [
                    'Malaria Research Initiative',
                    'Tuberculosis Control Program',
                    'Maternal Health Study',
                    'Child Nutrition Research',
                    'Dengue Fever Prevention',
                    'HIV Prevention Program',
                    'Mental Health Support',
                    'Cardiovascular Disease Study',
                    'Cancer Research Project',
                    'Diabetes Management Program',
                    'Infectious Disease Control',
                    'Public Health Initiative',
                ],
                'descriptions' => [
                    'Comprehensive research program focused on tropical disease prevention and treatment',
                    'Multi-year initiative to improve healthcare outcomes in underserved communities',
                    'Evidence-based study to develop new treatment protocols and methodologies',
                    'Cross-sectional research to understand disease patterns and risk factors',
                    'Longitudinal study tracking health outcomes over extended periods',
                    'Clinical trial for new therapeutic interventions and medications',
                ],
            ],
            'training' => [
                'names' => [
                    'Healthcare Worker Training Program',
                    'Medical Education Initiative',
                    'Community Health Capacity Building',
                    'Research Skills Development',
                    'Leadership Development Program',
                    'Technical Skills Enhancement',
                ],
                'descriptions' => [
                    'Comprehensive training program for healthcare professionals',
                    'Capacity building initiative to strengthen local expertise',
                    'Educational program designed to improve clinical competencies',
                ],
            ],
            'infrastructure' => [
                'names' => [
                    'Laboratory Equipment Upgrade',
                    'Healthcare Facility Development',
                    'Technology Infrastructure Program',
                    'Medical Equipment Procurement',
                    'Facility Modernization Project',
                ],
                'descriptions' => [
                    'Infrastructure development to enhance research capabilities',
                    'Facility improvement project to expand service delivery capacity',
                    'Technology upgrade to support modern healthcare delivery',
                ],
            ],
            'operational' => [
                'names' => [
                    'General Operations Fund',
                    'Administrative Support Grant',
                    'Operational Excellence Initiative',
                    'Core Activities Support',
                    'Hub Operations Fund',
                ],
                'descriptions' => [
                    'Core operational funding to support ongoing activities',
                    'Administrative support for essential organizational functions',
                    'General fund to maintain operational efficiency',
                ],
            ],
        ];

        $donorPrefixes = [
            'NIH', 'WHO', 'USAID', 'BMGF', 'NIHR', 'MRC', 'RCUK', 'EU',
            'NSF', 'CDC', 'DFID', 'PEPFAR', 'GAVI', 'UNITAID', 'WELLCOME',
        ];

        // Select subsidiary
        $subsidiary = $this->faker->randomElement(array_keys($subsidiaries));
        $subsidiaryData = $subsidiaries[$subsidiary];

        // Select grant type and details
        $grantType = $this->faker->randomElement(array_keys($grantTypes));
        $grantName = $this->faker->randomElement($grantTypes[$grantType]['names']);
        $grantDescription = $this->faker->randomElement($grantTypes[$grantType]['descriptions']);

        // Generate realistic grant code
        $year = $this->faker->numberBetween(2020, 2026);
        $donorPrefix = $this->faker->randomElement($donorPrefixes);
        $grantNumber = $this->faker->numberBetween(1000, 9999);
        $grantCode = "{$subsidiaryData['prefix']}{$year}-{$donorPrefix}-{$grantNumber}";

        // Determine end date based on grant type
        $endDate = null;
        if ($grantType === 'operational') {
            // 30% chance of having no end date (permanent operational funds)
            if ($this->faker->boolean(30)) {
                $endDate = null;
            } else {
                $endDate = $this->faker->dateTimeBetween('+1 year', '+5 years')->format('Y-m-d');
            }
        } else {
            // Research and training grants typically have defined end dates
            $endDate = $this->faker->dateTimeBetween('+3 months', '+3 years')->format('Y-m-d');
        }

        // Add some variation to names and descriptions
        $locationSuffix = $this->faker->randomElement($subsidiaryData['locations']);
        $enhancedName = $grantName.' - '.$locationSuffix;

        $enhancedDescription = $grantDescription.' '.
            $this->faker->randomElement([
                'This initiative aims to strengthen local capacity and improve health outcomes.',
                'The program focuses on sustainable development and community engagement.',
                'This project emphasizes evidence-based approaches and knowledge transfer.',
                'The grant supports innovative research methodologies and best practices.',
                'This funding enables collaborative partnerships and knowledge sharing.',
            ]);

        // Determine created_by and updated_by
        $systemUsers = [
            'admin', 'system', 'grant_manager', 'finance_officer',
            'program_director', 'research_coordinator', 'operations_manager',
        ];

        $createdBy = $this->faker->randomElement($systemUsers);
        $updatedBy = $this->faker->boolean(70) ? $createdBy : $this->faker->randomElement($systemUsers);

        return [
            'code' => $grantCode,
            'name' => $enhancedName,
            'subsidiary' => $subsidiary,
            'description' => $enhancedDescription,
            'end_date' => $endDate,
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
        ];
    }

    /**
     * Create a grant that is currently active (no end date or future end date)
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $endDate = $this->faker->boolean(40)
                ? null
                : $this->faker->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d');

            return [
                'end_date' => $endDate,
            ];
        });
    }

    /**
     * Create a grant that has expired
     */
    public function expired(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'end_date' => $this->faker->dateTimeBetween('-2 years', '-1 day')->format('Y-m-d'),
            ];
        });
    }

    /**
     * Create a grant that is ending soon (within 30 days)
     */
    public function endingSoon(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'end_date' => $this->faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            ];
        });
    }

    /**
     * Create a research grant
     */
    public function research(): static
    {
        return $this->state(function (array $attributes) {
            $researchNames = [
                'Malaria Elimination Program',
                'TB Drug Resistance Study',
                'Maternal Mortality Reduction',
                'Child Malnutrition Research',
                'Vector Control Initiative',
                'Antimicrobial Resistance Study',
                'Climate Change Health Impact',
                'Digital Health Innovation',
            ];

            return [
                'name' => $this->faker->randomElement($researchNames).' - '.
                         $this->faker->randomElement(['Phase I', 'Phase II', 'Phase III', 'Pilot Study', 'Main Study']),
                'description' => 'Advanced research initiative focusing on evidence-based interventions and sustainable health outcomes. '.
                               'This multi-year program involves community engagement, capacity building, and knowledge translation.',
                'end_date' => $this->faker->dateTimeBetween('+6 months', '+4 years')->format('Y-m-d'),
            ];
        });
    }

    /**
     * Create an operational/hub grant
     */
    public function operational(): static
    {
        return $this->state(function (array $attributes) {
            $operationalNames = [
                'Core Operations Fund',
                'Administrative Hub Grant',
                'General Support Fund',
                'Institutional Support Grant',
                'Operational Excellence Fund',
            ];

            return [
                'name' => $this->faker->randomElement($operationalNames),
                'description' => 'Core operational funding to support essential organizational functions, administrative activities, and ongoing program maintenance.',
                'end_date' => $this->faker->boolean(60) ? null : $this->faker->dateTimeBetween('+2 years', '+10 years')->format('Y-m-d'),
            ];
        });
    }

    /**
     * Create a grant for a specific subsidiary
     */
    public function forSubsidiary(string $subsidiary): static
    {
        return $this->state(function (array $attributes) use ($subsidiary) {
            $subsidiaryPrefixes = [
                'SMRU' => 'S',
                'BHF' => 'B',
                'MORU' => 'M',
                'OUCRU' => 'O',
            ];

            $prefix = $subsidiaryPrefixes[$subsidiary] ?? 'G';
            $year = $this->faker->numberBetween(2020, 2026);
            $number = $this->faker->numberBetween(1000, 9999);

            return [
                'subsidiary' => $subsidiary,
                'code' => "{$prefix}{$year}-{$this->faker->randomElement(['NIH', 'WHO', 'USAID'])}-{$number}",
            ];
        });
    }

    /**
     * Create grants without end dates (permanent grants)
     */
    public function permanent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'end_date' => null,
                'name' => $this->faker->randomElement([
                    'Institutional Core Fund',
                    'General Operations Grant',
                    'Hub Maintenance Fund',
                    'Administrative Support Grant',
                    'Permanent Operations Fund',
                ]),
                'description' => 'Permanent operational funding with no specified end date to support ongoing institutional activities and core functions.',
            ];
        });
    }
}
