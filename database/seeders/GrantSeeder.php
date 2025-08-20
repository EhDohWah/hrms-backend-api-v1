<?php

namespace Database\Seeders;

use App\Models\Grant;
use Illuminate\Database\Seeder;

class GrantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Note: The migration already creates 2 default hub grants (S0031 and S22001)
        // We'll create additional diverse grants for testing

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

        // Display summary
        $this->command->info('Grant seeding completed!');
        $this->command->info('Summary:');
        $this->command->info('- Total Active Grants: '.Grant::active()->count());
        $this->command->info('- Total Expired Grants: '.Grant::expired()->count());
        $this->command->info('- Total Ending Soon: '.Grant::endingSoon()->count());
        $this->command->info('- Total Grants: '.Grant::count());

        // Show grants by subsidiary
        $this->command->info('');
        $this->command->info('Grants by Subsidiary:');
        $subsidiaryStats = Grant::selectRaw('subsidiary, COUNT(*) as count')
            ->groupBy('subsidiary')
            ->orderBy('subsidiary')
            ->get();

        foreach ($subsidiaryStats as $stat) {
            $this->command->info("- {$stat->subsidiary}: {$stat->count} grants");
        }
    }
}
