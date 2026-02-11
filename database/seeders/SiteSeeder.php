<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the sites table with organizational work locations.
 *
 * Environment: Production + Development
 * Idempotent: Yes — skips if sites already exist
 * Dependencies: None
 */
class SiteSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('sites')->count() > 0) {
            $this->command->info('Sites already seeded — skipping.');

            return;
        }

        $sites = [
            ['name' => 'Expat', 'code' => 'EXPAT', 'description' => 'Expatriate Staff', 'is_active' => true],
            ['name' => 'KK-MCH', 'code' => 'KK_MCH', 'description' => 'KK-MCH site', 'is_active' => true],
            ['name' => 'TB-KK', 'code' => 'TB_KK', 'description' => 'TB-Koh Kong', 'is_active' => true],
            ['name' => 'MKT', 'code' => 'MKT', 'description' => 'MKT site', 'is_active' => true],
            ['name' => 'MRM', 'code' => 'MRM', 'description' => 'Mae Ramat', 'is_active' => true],
            ['name' => 'MSL', 'code' => 'MSL', 'description' => 'MSL site', 'is_active' => true],
            ['name' => 'Mutraw', 'code' => 'MUTRAW', 'description' => 'Mutraw site', 'is_active' => true],
            ['name' => 'TB-MRM', 'code' => 'TB_MRM', 'description' => 'TB-MRM', 'is_active' => true],
            ['name' => 'WP', 'code' => 'WP', 'description' => 'WP site', 'is_active' => true],
            ['name' => 'WPA', 'code' => 'WPA', 'description' => 'WPA site', 'is_active' => true],
            ['name' => 'Yangon', 'code' => 'YANGON', 'description' => 'Yangon site', 'is_active' => true],
        ];

        foreach ($sites as $site) {
            DB::table('sites')->insert(array_merge($site, [
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'System',
                'updated_by' => 'System',
            ]));
        }

        $this->command->info('Sites seeded: '.DB::table('sites')->count().' records.');
    }
}
