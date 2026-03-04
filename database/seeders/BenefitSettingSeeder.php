<?php

namespace Database\Seeders;

use App\Models\BenefitSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds benefit_settings table with:
 * - Health welfare legacy tiers (6 rows, inactive — replaced by Thai/Non-Thai tiers)
 * - Health welfare Thai/Non-Thai tiers (9 rows)
 * - Health Welfare employer eligibility (1 row)
 * - Social Security Fund rates (6 rows, 2026 Thai rates)
 * - Provident Fund rates (3 rows)
 * - Saving Fund rates (3 rows)
 *
 * Idempotent: Yes — uses updateOrCreate
 */
class BenefitSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── Health Welfare Legacy Tiers (inactive — kept for audit trail) ──
            [
                'setting_key' => 'health_welfare_percentage',
                'setting_value' => 5.00,
                'setting_type' => 'percentage',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Health and Welfare contribution percentage (display only)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => 'health_welfare_high_threshold',
                'setting_value' => 15000.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Salary threshold for high tier health welfare contribution (Baht)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => 'health_welfare_high_amount',
                'setting_value' => 150.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Health welfare contribution for high tier [REPLACED BY THAI/NON-THAI TIERS]',
                'effective_date' => '2025-01-01',
                'is_active' => false,
            ],
            [
                'setting_key' => 'health_welfare_medium_threshold',
                'setting_value' => 5000.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Salary threshold for medium tier health welfare contribution (Baht)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => 'health_welfare_medium_amount',
                'setting_value' => 100.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Health welfare contribution for medium tier [REPLACED BY THAI/NON-THAI TIERS]',
                'effective_date' => '2025-01-01',
                'is_active' => false,
            ],
            [
                'setting_key' => 'health_welfare_low_amount',
                'setting_value' => 60.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Health welfare contribution for low tier [REPLACED BY THAI/NON-THAI TIERS]',
                'effective_date' => '2025-01-01',
                'is_active' => false,
            ],

            // ── Health Welfare Non-Thai Employee Tiers ─────────────
            [
                'setting_key' => BenefitSetting::KEY_HW_NONTHAI_EMPLOYEE_LOW,
                'setting_value' => 30.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Non-Thai employee health welfare - salary <= 5,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_HW_NONTHAI_EMPLOYEE_MEDIUM,
                'setting_value' => 50.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Non-Thai employee health welfare - salary 5,001-15,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_HW_NONTHAI_EMPLOYEE_HIGH,
                'setting_value' => 75.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Non-Thai employee health welfare - salary > 15,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],

            // ── Health Welfare Non-Thai Employer Tiers ─────────────
            [
                'setting_key' => BenefitSetting::KEY_HW_NONTHAI_EMPLOYER_LOW,
                'setting_value' => 60.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Non-Thai employer health welfare - salary <= 5,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_HW_NONTHAI_EMPLOYER_MEDIUM,
                'setting_value' => 100.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Non-Thai employer health welfare - salary 5,001-15,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_HW_NONTHAI_EMPLOYER_HIGH,
                'setting_value' => 150.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Non-Thai employer health welfare - salary > 15,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],

            // ── Health Welfare Thai Employee Tiers (no employer contribution) ──
            [
                'setting_key' => BenefitSetting::KEY_HW_THAI_EMPLOYEE_LOW,
                'setting_value' => 50.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Thai employee health welfare - salary <= 5,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_HW_THAI_EMPLOYEE_MEDIUM,
                'setting_value' => 80.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Thai employee health welfare - salary 5,001-15,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_HW_THAI_EMPLOYEE_HIGH,
                'setting_value' => 100.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Thai employee health welfare - salary > 15,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],

            // ── Health Welfare Employer Eligibility ──────────────
            [
                'setting_key' => BenefitSetting::KEY_HEALTH_WELFARE_EMPLOYER_ENABLED,
                'setting_value' => 1.00,
                'setting_type' => 'boolean',
                'category' => BenefitSetting::CATEGORY_HEALTH_WELFARE,
                'description' => 'Employer pays health welfare for eligible employees (Local non ID Staff, Expats at SMRU/BHF)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => [
                    'eligible_statuses' => ['Local non ID Staff', 'Expats (Local)'],
                    'eligible_organizations' => ['SMRU', 'BHF'],
                ],
            ],

            // ── Social Security Fund (2026 Thai rates) ────────────
            [
                'setting_key' => BenefitSetting::KEY_SSF_EMPLOYEE_RATE,
                'setting_value' => 5.00,
                'setting_type' => 'percentage',
                'category' => BenefitSetting::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Employee Social Security Fund contribution rate - 5%',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_SSF_EMPLOYER_RATE,
                'setting_value' => 5.00,
                'setting_type' => 'percentage',
                'category' => BenefitSetting::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Employer Social Security Fund contribution rate - 5%',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_SSF_MIN_SALARY,
                'setting_value' => 1650.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Minimum monthly salary for SSF calculation - 1,650 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_SSF_MAX_SALARY,
                'setting_value' => 17500.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Maximum monthly salary for SSF calculation - 17,500 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_SSF_MAX_MONTHLY,
                'setting_value' => 875.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Maximum monthly SSF contribution - 875 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_SSF_MAX_YEARLY,
                'setting_value' => 10500.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Maximum annual SSF contribution - 10,500 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],

            // ── Provident Fund (PVD) ─────────────────────────────
            [
                'setting_key' => BenefitSetting::KEY_PVD_EMPLOYEE_RATE,
                'setting_value' => 7.50,
                'setting_type' => 'percentage',
                'category' => BenefitSetting::CATEGORY_PROVIDENT_FUND,
                'description' => 'Employee Provident Fund (PVD) contribution rate for Local ID employees',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_PVD_EMPLOYER_RATE,
                'setting_value' => 7.50,
                'setting_type' => 'percentage',
                'category' => BenefitSetting::CATEGORY_PROVIDENT_FUND,
                'description' => 'Employer Provident Fund (PVD) contribution rate',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_PVD_MAX_ANNUAL,
                'setting_value' => 500000.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_PROVIDENT_FUND,
                'description' => 'Maximum annual Provident Fund contribution - 500,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],

            // ── Saving Fund ─────────────────────────────────────
            [
                'setting_key' => BenefitSetting::KEY_SAVING_FUND_EMPLOYEE_RATE,
                'setting_value' => 7.50,
                'setting_type' => 'percentage',
                'category' => BenefitSetting::CATEGORY_SAVING_FUND,
                'description' => 'Employee Saving Fund contribution rate for Local non ID employees',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_SAVING_FUND_EMPLOYER_RATE,
                'setting_value' => 7.50,
                'setting_type' => 'percentage',
                'category' => BenefitSetting::CATEGORY_SAVING_FUND,
                'description' => 'Employer Saving Fund contribution rate',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
            [
                'setting_key' => BenefitSetting::KEY_SAVING_FUND_MAX_ANNUAL,
                'setting_value' => 500000.00,
                'setting_type' => 'numeric',
                'category' => BenefitSetting::CATEGORY_SAVING_FUND,
                'description' => 'Maximum annual Saving Fund contribution - 500,000 Baht',
                'effective_date' => '2025-01-01',
                'is_active' => true,
            ],
        ];

        foreach ($settings as $setting) {
            BenefitSetting::updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                array_merge($setting, [
                    'created_by' => 'system',
                    'updated_by' => 'system',
                ])
            );
        }

        $this->command->info('Benefit settings seeded: '.BenefitSetting::count().' settings.');
    }
}
