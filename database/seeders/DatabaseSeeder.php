<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core System - Run First
            PermissionRoleSeeder::class,  // Creates permissions and roles
            ModuleSeeder::class,           // Creates modules with permission references
            UserSeeder::class,             // Creates default users
            DashboardWidgetSeeder::class,  // Creates dashboard widgets

            // Optional Seeders (Uncomment as needed)
            // BudgetLineSeeder::class,
            // EmployeeSeeder::class,
            // InterviewSeeder::class,
            // GrantSeeder::class,
            // JobOfferSeeder::class,
            // Thai2025TaxDataSeeder::class,
            // SectionDepartmentSeeder::class,
            // ProbationAllocationSeeder::class,  // TODO: Fix grant_id column issue
            BenefitSettingSeeder::class,
        ]);
    }
}
