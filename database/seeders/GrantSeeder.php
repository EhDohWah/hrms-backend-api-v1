<?php

namespace Database\Seeders;

use App\Models\Grant;
use App\Models\GrantItem;
use Illuminate\Database\Seeder;

class GrantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        // Create Active Research Grants for SMRU (8 grants)
        Grant::factory()
            ->count(4)
            ->forSubsidiary('SMRU')
            ->research()
            ->active()
            ->create();

        Grant::factory()
            ->count(4)
            ->forSubsidiary('BHF')
            ->research()
            ->active()
            ->create();

        // Create Operational Grants (6 grants)
        Grant::factory()
            ->count(2)
            ->forSubsidiary('SMRU')
            ->operational()
            ->active()
            ->create();

        Grant::factory()
            ->count(2)
            ->forSubsidiary('BHF')
            ->operational()
            ->active()
            ->create();

        Grant::factory()
            ->count(1)
            ->forSubsidiary('SMRU')
            ->operational()
            ->permanent()
            ->create();

        Grant::factory()
            ->count(1)
            ->forSubsidiary('BHF')
            ->operational()
            ->permanent()
            ->create();

        // Create Expired Grants (8 grants)
        Grant::factory()
            ->count(3)
            ->forSubsidiary('SMRU')
            ->research()
            ->expired()
            ->create();

        Grant::factory()
            ->count(3)
            ->forSubsidiary('BHF')
            ->research()
            ->expired()
            ->create();

        Grant::factory()
            ->count(1)
            ->forSubsidiary('SMRU')
            ->operational()
            ->expired()
            ->create();

        Grant::factory()
            ->count(1)
            ->forSubsidiary('BHF')
            ->operational()
            ->expired()
            ->create();

        // Create Grants Ending Soon (4 grants)
        Grant::factory()
            ->count(2)
            ->forSubsidiary('SMRU')
            ->research()
            ->endingSoon()
            ->create();

        Grant::factory()
            ->count(2)
            ->forSubsidiary('BHF')
            ->research()
            ->endingSoon()
            ->create();

        // Create grant items (positions) for each grant
        $totalItems = 0;
        Grant::all()->each(function (Grant $grant) use ($faker, &$totalItems) {
            $itemCount = $faker->numberBetween(2, 6);

            GrantItem::factory()
                ->count($itemCount)
                ->forGrant($grant)
                ->create();

            $totalItems += $itemCount;
        });

        // Display summary
        $this->command->info('Grant seeding completed!');
        $this->command->info('Summary:');
        $this->command->info('- Total Active Grants: '.Grant::active()->count());
        $this->command->info('- Total Expired Grants: '.Grant::expired()->count());
        $this->command->info('- Total Ending Soon: '.Grant::endingSoon()->count());
        $this->command->info('- Total Grants: '.Grant::count());
        $this->command->info("- Total Grant Items (positions): {$totalItems}");

        // Show grants by organization
        $this->command->info('');
        $this->command->info('Grants by Subsidiary:');
        $organizationStats = Grant::selectRaw('organization, COUNT(*) as count')
            ->groupBy('organization')
            ->orderBy('organization')
            ->get();

        foreach ($organizationStats as $stat) {
            $this->command->info("- {$stat->organization}: {$stat->count} grants");
        }
    }
}
