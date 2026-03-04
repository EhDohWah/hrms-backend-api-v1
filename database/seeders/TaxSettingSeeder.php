<?php

namespace Database\Seeders;

use App\Models\TaxSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the tax_settings table with Thai tax law configuration.
 *
 * Only includes employment deductions and personal allowances per Thai Revenue Department.
 * SSF, PVD, and Saving Fund rates have been moved to benefit_settings table
 * (see BenefitSettingSeeder).
 *
 * Environment: Production + Development
 * Idempotent: Yes — uses updateOrCreate keyed on setting_key + effective_year
 * Dependencies: None
 */
class TaxSettingSeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) date('Y');

        $settings = [
            // Employment deductions (Personal Expense 50%, max 100k)
            [
                'setting_key' => 'EMPLOYMENT_DEDUCTION_RATE',
                'setting_value' => 50.00,
                'setting_type' => 'DEDUCTION',
                'description' => 'Personal Expense deduction rate (50% of income)',
                'effective_year' => $year,
                'is_selected' => true,
            ],
            [
                'setting_key' => 'EMPLOYMENT_DEDUCTION_MAX',
                'setting_value' => 100000.00,
                'setting_type' => 'LIMIT',
                'description' => 'Maximum Personal Expense deduction (฿100,000)',
                'effective_year' => $year,
                'is_selected' => true,
            ],

            // Personal allowances — all enabled; individual eligibility checked per employee
            [
                'setting_key' => 'PERSONAL_ALLOWANCE',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Personal allowance per taxpayer (฿60,000)',
                'effective_year' => $year,
                'is_selected' => true,
            ],
            [
                'setting_key' => 'SPOUSE_ALLOWANCE',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Spouse allowance (if spouse has no income) - ฿60,000',
                'effective_year' => $year,
                'is_selected' => true,
            ],
            [
                'setting_key' => 'CHILD_ALLOWANCE',
                'setting_value' => 30000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Child allowance (first child) - ฿30,000',
                'effective_year' => $year,
                'is_selected' => true,
            ],
            [
                'setting_key' => 'CHILD_ALLOWANCE_SUBSEQUENT',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Child allowance (subsequent children born 2018+) - ฿60,000',
                'effective_year' => $year,
                'is_selected' => true,
            ],
            [
                'setting_key' => 'PARENT_ALLOWANCE',
                'setting_value' => 30000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Parent allowance (age 60+, income < ฿30,000/year) - ฿30,000 per parent',
                'effective_year' => $year,
                'is_selected' => true,
            ],

            // NOTE: SSF, PVD, and Saving Fund rates are now in benefit_settings table.
            // See BenefitSettingSeeder for those settings.
        ];

        foreach ($settings as $setting) {
            TaxSetting::updateOrCreate(
                [
                    'setting_key' => $setting['setting_key'],
                    'effective_year' => $setting['effective_year'],
                ],
                array_merge($setting, [
                    'created_by' => 'system',
                    'updated_by' => 'system',
                ])
            );
        }

        $this->command->info('Tax settings seeded: '.TaxSetting::where('effective_year', $year)->count().' settings for year '.$year.'.');
    }
}
