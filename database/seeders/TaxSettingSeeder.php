<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the tax_settings table with Thai 2025 tax configuration.
 *
 * Includes employment deductions, personal allowances, PVD/Saving Fund rates,
 * and Social Security Fund settings per Thai Revenue Department regulations.
 *
 * Environment: Production + Development
 * Idempotent: Yes — skips if tax settings already exist
 * Dependencies: None
 */
class TaxSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('tax_settings')->count() > 0) {
            $this->command->info('Tax settings already seeded — skipping.');

            return;
        }

        $now = Carbon::now();
        $year = (int) date('Y');

        DB::table('tax_settings')->insert([
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
                'is_selected' => false,
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
                'is_selected' => false,
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
                'is_selected' => false,
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

            // Social Security Fund
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
                'setting_key' => 'SSF_MAX_MONTHLY',
                'setting_value' => 750.00,
                'setting_type' => 'LIMIT',
                'description' => 'Maximum monthly Social Security contribution (฿750)',
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
        ]);

        $this->command->info('Tax settings seeded: '.DB::table('tax_settings')->count().' settings for year '.$year.'.');
    }
}
