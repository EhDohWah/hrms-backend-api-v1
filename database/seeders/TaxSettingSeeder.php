<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TaxSetting;
use Carbon\Carbon;

class TaxSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = date('Y');
        
        // Thai Tax Settings for 2025 (Based on Thai Revenue Department and Social Security Office)
        $taxSettings = [
            // Personal Deductions
            [
                'setting_key' => TaxSetting::KEY_PERSONAL_ALLOWANCE,
                'setting_value' => 60000,
                'setting_type' => TaxSetting::TYPE_DEDUCTION,
                'description' => 'Personal allowance for individual taxpayers',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => TaxSetting::KEY_SPOUSE_ALLOWANCE,
                'setting_value' => 60000,
                'setting_type' => TaxSetting::TYPE_DEDUCTION,
                'description' => 'Spouse allowance for married taxpayers',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => TaxSetting::KEY_CHILD_ALLOWANCE,
                'setting_value' => 30000,
                'setting_type' => TaxSetting::TYPE_DEDUCTION,
                'description' => 'Child allowance per child (maximum 3 children)',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],

            // Personal Expense Deductions
            [
                'setting_key' => TaxSetting::KEY_PERSONAL_EXPENSE_RATE,
                'setting_value' => 40,
                'setting_type' => TaxSetting::TYPE_RATE,
                'description' => 'Personal expense deduction rate (40% of income)',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => TaxSetting::KEY_PERSONAL_EXPENSE_MAX,
                'setting_value' => 60000,
                'setting_type' => TaxSetting::TYPE_LIMIT,
                'description' => 'Maximum personal expense deduction amount',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],

            // Social Security Fund (SSF) Settings
            [
                'setting_key' => TaxSetting::KEY_SSF_RATE,
                'setting_value' => 5,
                'setting_type' => TaxSetting::TYPE_RATE,
                'description' => 'Social Security Fund contribution rate (5% each for employee and employer)',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => TaxSetting::KEY_SSF_MAX_MONTHLY,
                'setting_value' => 750,
                'setting_type' => TaxSetting::TYPE_LIMIT,
                'description' => 'Maximum monthly Social Security Fund contribution',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => TaxSetting::KEY_SSF_MAX_YEARLY,
                'setting_value' => 9000,
                'setting_type' => TaxSetting::TYPE_LIMIT,
                'description' => 'Maximum yearly Social Security Fund contribution',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],

            // Provident Fund (PF) Settings
            [
                'setting_key' => TaxSetting::KEY_PF_MIN_RATE,
                'setting_value' => 3,
                'setting_type' => TaxSetting::TYPE_RATE,
                'description' => 'Minimum Provident Fund contribution rate (3% of salary)',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => TaxSetting::KEY_PF_MAX_RATE,
                'setting_value' => 15,
                'setting_type' => TaxSetting::TYPE_RATE,
                'description' => 'Maximum Provident Fund contribution rate (15% of salary)',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],

            // Additional Common Tax Settings
            [
                'setting_key' => 'DISABILITY_ALLOWANCE',
                'setting_value' => 60000,
                'setting_type' => TaxSetting::TYPE_DEDUCTION,
                'description' => 'Allowance for disabled taxpayers or disabled dependents',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => 'EDUCATION_ALLOWANCE',
                'setting_value' => 100000,
                'setting_type' => TaxSetting::TYPE_DEDUCTION,
                'description' => 'Education allowance for skill development courses',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => 'HEALTH_INSURANCE_MAX',
                'setting_value' => 25000,
                'setting_type' => TaxSetting::TYPE_LIMIT,
                'description' => 'Maximum health insurance premium deduction',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => 'LIFE_INSURANCE_MAX',
                'setting_value' => 100000,
                'setting_type' => TaxSetting::TYPE_LIMIT,
                'description' => 'Maximum life insurance premium deduction',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => 'PENSION_INSURANCE_MAX',
                'setting_value' => 200000,
                'setting_type' => TaxSetting::TYPE_LIMIT,
                'description' => 'Maximum pension insurance premium deduction',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => 'HOUSE_INTEREST_MAX',
                'setting_value' => 100000,
                'setting_type' => TaxSetting::TYPE_LIMIT,
                'description' => 'Maximum home loan interest deduction',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
            [
                'setting_key' => 'CHARITABLE_DONATION_RATE',
                'setting_value' => 10,
                'setting_type' => TaxSetting::TYPE_RATE,
                'description' => 'Maximum charitable donation deduction rate (10% of net income)',
                'effective_year' => $currentYear,
                'is_active' => true,
                'created_by' => 'system',
            ],
        ];

        // Delete existing settings for current year to avoid duplicates
        TaxSetting::where('effective_year', $currentYear)->delete();
        TaxSetting::where('effective_year', $currentYear + 1)->delete();

        // Insert new tax settings
        foreach ($taxSettings as $setting) {
            TaxSetting::create([
                'setting_key' => $setting['setting_key'],
                'setting_value' => $setting['setting_value'],
                'setting_type' => $setting['setting_type'],
                'description' => $setting['description'],
                'effective_year' => $setting['effective_year'],
                'is_active' => $setting['is_active'],
                'created_by' => $setting['created_by'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info('Tax settings seeded successfully for year ' . $currentYear);
        
        // Also create settings for next year (for planning purposes)
        $nextYear = $currentYear + 1;
        foreach ($taxSettings as $setting) {
            $setting['effective_year'] = $nextYear;
            $setting['is_active'] = false; // Inactive until the year starts
            
            TaxSetting::create([
                'setting_key' => $setting['setting_key'],
                'setting_value' => $setting['setting_value'],
                'setting_type' => $setting['setting_type'],
                'description' => $setting['description'],
                'effective_year' => $setting['effective_year'],
                'is_active' => $setting['is_active'],
                'created_by' => $setting['created_by'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info('Tax settings seeded successfully for year ' . $nextYear . ' (inactive)');
    }
}