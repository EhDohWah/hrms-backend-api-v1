<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Local Development Seeder — seeds production data + fake test data.
 *
 * Usage:
 *   php artisan migrate --seed        (runs this seeder)
 *   php artisan db:seed               (runs this seeder)
 *
 * For production, use ProductionSeeder instead:
 *   php artisan db:seed --class=ProductionSeeder
 *
 * @see ProductionSeeder for production-only data
 * @see docs/SEED_DATA_AUDIT.md for the full data inventory
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // =====================================================================
        // Production data (roles, permissions, modules, users, widgets, etc.)
        // This is the same data that runs on production deployments.
        // =====================================================================
        $this->call(ProductionSeeder::class);

        // =====================================================================
        // Dev/test data below — fake records for local development only.
        // These seeders are NOT idempotent — running them twice will duplicate.
        // NEVER run these in production.
        // =====================================================================
        $this->call([
            SectionDepartmentSeeder::class,   // Sub-department sections
            EmployeeSeeder::class,            // 100 fake employees
            GrantSeeder::class,               // 26 test grants
            InterviewSeeder::class,           // 300 fake interviews
            JobOfferSeeder::class,            // 100 fake job offers

            // ProbationAllocationSeeder::class, // Disabled — references deleted models
        ]);
    }
}
