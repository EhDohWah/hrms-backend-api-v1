<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Thai2025TaxDataSeeder extends Seeder
{
    /**
     * Seed Thai 2025 tax data with updated values:
     * - Personal Expense (50%, max ฿100,000): ฿100,000
     * - Personal Allowance: ฿60,000
     * - Provident Fund: ฿27,000
     * - Spouse Allowance: ฿0 (available ฿60,000)
     * - Children Allowance: ฿0 (available ฿60,000)
     * - Social Security: ฿9,000
     */
    public function run(): void
    {
        $now = Carbon::now();
        $year = 2025;

        // Check if data already exists
        $existingBrackets = DB::table('tax_brackets')->where('effective_year', $year)->count();
        $existingSettings = DB::table('tax_settings')->where('effective_year', $year)->count();

        if ($existingBrackets > 0 || $existingSettings > 0) {
            $this->command->info('Thai 2025 tax data already exists, skipping.');
            return;
        }

        // Clear existing data for 2025
        DB::table('tax_brackets')->where('effective_year', $year)->delete();
        DB::table('tax_settings')->where('effective_year', $year)->delete();

        $this->command->info('Seeding Thai 2025 tax data with updated values...');

        // === TAX BRACKETS (8 official Thai brackets) ===
        $brackets = [
            [
                'min_income' => 0,
                'max_income' => 150000,
                'tax_rate' => 0,
                'base_tax' => 0,
                'bracket_order' => 1,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '0% - Income ฿0 to ฿150,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 150001,
                'max_income' => 300000,
                'tax_rate' => 5,
                'base_tax' => 0,
                'bracket_order' => 2,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '5% - Income ฿150,001 to ฿300,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 300001,
                'max_income' => 500000,
                'tax_rate' => 10,
                'base_tax' => 7500,
                'bracket_order' => 3,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '10% - Income ฿300,001 to ฿500,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 500001,
                'max_income' => 750000,
                'tax_rate' => 15,
                'base_tax' => 27500,
                'bracket_order' => 4,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '15% - Income ฿500,001 to ฿750,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 750001,
                'max_income' => 1000000,
                'tax_rate' => 20,
                'base_tax' => 65000,
                'bracket_order' => 5,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '20% - Income ฿750,001 to ฿1,000,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 1000001,
                'max_income' => 2000000,
                'tax_rate' => 25,
                'base_tax' => 115000,
                'bracket_order' => 6,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '25% - Income ฿1,000,001 to ฿2,000,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 2000001,
                'max_income' => 5000000,
                'tax_rate' => 30,
                'base_tax' => 365000,
                'bracket_order' => 7,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '30% - Income ฿2,000,001 to ฿5,000,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 5000001,
                'max_income' => null,
                'tax_rate' => 35,
                'base_tax' => 1265000,
                'bracket_order' => 8,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '35% - Income ฿5,000,001 and above',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('tax_brackets')->insert($brackets);
        $this->command->info('Tax brackets seeded: 8 brackets (0% to 35%)');

        // === TAX SETTINGS (Updated with requested values) ===
        $settings = [
            // Employment deductions (Personal Expense 50%, max 100k)
            [
                'setting_key' => 'EMPLOYMENT_DEDUCTION_RATE',
                'setting_value' => 50.00,
                'setting_type' => 'DEDUCTION',
                'description' => 'Personal Expense deduction rate (50% of income)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'EMPLOYMENT_DEDUCTION_MAX',
                'setting_value' => 100000.00,
                'setting_type' => 'LIMIT',
                'description' => 'Maximum Personal Expense deduction (฿100,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // Personal allowances
            [
                'setting_key' => 'PERSONAL_ALLOWANCE',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Personal allowance per taxpayer (฿60,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'SPOUSE_ALLOWANCE',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Spouse allowance (if no income) - Available ฿60,000',
                'effective_year' => $year,
                'is_selected' => false, // Set to ฿0 (disabled by default as requested)
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'CHILD_ALLOWANCE',
                'setting_value' => 30000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Child allowance (first child) - Available ฿30,000',
                'effective_year' => $year,
                'is_selected' => false, // Set to ฿0 (disabled by default as requested)
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'CHILD_ALLOWANCE_SUBSEQUENT',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Child allowance (subsequent children born 2018+) - Available ฿60,000',
                'effective_year' => $year,
                'is_selected' => false, // Set to ฿0 (disabled by default as requested)
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // Provident Fund - Thai Citizens (PVD Fund)
            [
                'setting_key' => 'PVD_FUND_RATE',
                'setting_value' => 7.5,
                'setting_type' => 'RATE',
                'description' => 'PVD Fund contribution rate for Thai citizens (Local ID Staff) - 7.5% of annual income',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'PVD_FUND_MAX',
                'setting_value' => 500000.00,
                'setting_type' => 'LIMIT',
                'description' => 'PVD Fund maximum annual deduction for Thai citizens (฿500,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // Saving Fund - Non-Thai Citizens
            [
                'setting_key' => 'SAVING_FUND_RATE',
                'setting_value' => 7.5,
                'setting_type' => 'RATE',
                'description' => 'Saving Fund contribution rate for non-Thai citizens (Local non ID Staff) - 7.5% of annual income',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'SAVING_FUND_MAX',
                'setting_value' => 500000.00,
                'setting_type' => 'LIMIT',
                'description' => 'Saving Fund maximum annual deduction for non-Thai citizens (฿500,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // Social Security Fund (฿9,000 as requested)
            [
                'setting_key' => 'SSF_RATE',
                'setting_value' => 5.0,
                'setting_type' => 'DEDUCTION',
                'description' => 'Social Security Fund rate (5%)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'SSF_MAX_ANNUAL',
                'setting_value' => 9000.00,
                'setting_type' => 'LIMIT',
                'description' => 'Maximum annual Social Security (฿9,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('tax_settings')->insert($settings);
        $this->command->info('Tax settings seeded: ' . count($settings) . ' settings');

        // Display seeded values summary
        $this->command->info('=== Thai 2025 Tax Settings Summary ===');
        $this->command->info('✓ Personal Expense (50%, max ฿100,000): ฿100,000');
        $this->command->info('✓ Personal Allowance: ฿60,000');
        $this->command->info('✓ PVD Fund (Thai citizens/Local ID Staff): 7.5% of annual income (max ฿500,000)');
        $this->command->info('✓ Saving Fund (Non-Thai citizens/Local non ID Staff): 7.5% of annual income (max ฿500,000)');
        $this->command->info('✓ Spouse Allowance: ฿0 (available ฿60,000 - disabled)');
        $this->command->info('✓ Children Allowance: ฿0 (available ฿30,000/฿60,000 - disabled)');
        $this->command->info('✓ Social Security: ฿9,000');

        $this->command->info('Thai 2025 tax data seeded successfully!');
    }
}