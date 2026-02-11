<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production Seeder — seeds all data required for the system to function.
 *
 * Run this on every fresh production deployment after migrations:
 *   php artisan migrate
 *   php artisan db:seed --class=ProductionSeeder
 *
 * Safe to run multiple times — all sub-seeders are idempotent
 * (they use count checks, firstOrCreate, or updateOrCreate).
 *
 * What this seeds (in dependency order):
 *   - Departments (21 departments)
 *   - Positions (~150 positions with reporting hierarchy, depends on departments)
 *   - Sites (11 work locations)
 *   - Lookups (82 records across 18 types: gender, nationality, religion, etc.)
 *   - Leave types (11 leave types with Thai/English descriptions)
 *   - Tax brackets (8 Thai progressive tax brackets for 2025)
 *   - Tax settings (12 tax deduction/allowance settings for 2025)
 *   - Sidebar modules (50+ menu structure + permission mapping)
 *   - Roles & permissions (Spatie Permission: admin, hr-manager + 50+ permissions)
 *   - Default users (admin + hr-manager with all permissions)
 *   - Dashboard widgets (17 widget definitions)
 *   - Benefit settings (SSF, PVD, health welfare rates)
 *
 * @see docs/SEED_DATA_AUDIT.md for the full data inventory
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info(' Production Seeder');
        $this->command->info('========================================');
        $this->command->info('');

        $this->call([
            // ── Reference Data (no dependencies) ──────────────────

            // 1. Departments — 21 departments (Admin, HR, Finance, etc.)
            DepartmentSeeder::class,

            // 2. Positions — ~150 positions with reporting hierarchy
            //    Depends on: DepartmentSeeder (FK to departments)
            PositionSeeder::class,

            // 3. Sites — 11 work locations (EXPAT, KK_MCH, MRM, etc.)
            SiteSeeder::class,

            // 4. Lookups — 82 records across 18 types
            //    (gender, nationality, religion, marital status, bank names, etc.)
            LookupSeeder::class,

            // 5. Leave types — 11 leave types with Thai/English descriptions
            LeaveTypeSeeder::class,

            // 6. Tax brackets — 8 Thai progressive tax brackets (0%–35%) for 2025
            TaxBracketSeeder::class,

            // 7. Tax settings — 12 tax deduction/allowance settings for 2025
            TaxSettingSeeder::class,

            // ── Auth & Access Control ─────────────────────────────

            // 8. Sidebar modules — no dependencies
            //    Creates 50+ module records that define menu structure and permission mapping.
            //    Must run before PermissionRoleSeeder so it can read module names from the table.
            ModuleSeeder::class,

            // 9. Roles & permissions — depends on: ModuleSeeder (reads module names)
            //    Creates 2 core roles (admin, hr-manager) and 50+ module permissions.
            PermissionRoleSeeder::class,

            // 10. Default users — depends on: roles, permissions (from step 9)
            //     Creates admin@hrms.com and hrmanager@hrms.com with all permissions.
            UserSeeder::class,

            // ── Application Configuration ─────────────────────────

            // 11. Dashboard widgets — no dependencies
            //     Creates 17 widget definitions (welcome card, employee stats, leave summary, etc.)
            DashboardWidgetSeeder::class,

            // 12. Benefit settings — no dependencies
            //     Creates 5 payroll benefit settings (SSF %, PVD %, health welfare %, etc.)
            BenefitSettingSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info(' Production seeding complete!');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('Default credentials:');
        $this->command->info('  admin@hrms.com     (password: password)');
        $this->command->info('  hrmanager@hrms.com (password: password)');
        $this->command->info('');
        $this->command->info('IMPORTANT: Change default passwords immediately.');
        $this->command->info('========================================');
    }
}
