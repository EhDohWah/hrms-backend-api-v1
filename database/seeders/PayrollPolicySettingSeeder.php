<?php

namespace Database\Seeders;

use App\Models\PayrollPolicySetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the payroll_policy_settings table with default company policies.
 *
 * Includes:
 * - 13th Month Salary: enabled, divisor=12, min_months=6, accrual=monthly
 * - Salary Increase: enabled, rate=1%, min_working_days=365
 *
 * Idempotent: Yes — uses firstOrCreate
 */
class PayrollPolicySettingSeeder extends Seeder
{
    public function run(): void
    {
        PayrollPolicySetting::firstOrCreate(
            ['effective_date' => '2025-01-01', 'is_active' => true],
            [
                'thirteenth_month_enabled' => true,
                'thirteenth_month_divisor' => 12,
                'thirteenth_month_min_months' => 6,
                'thirteenth_month_accrual_method' => 'monthly',
                'salary_increase_enabled' => true,
                'salary_increase_rate' => 1.00,
                'salary_increase_min_working_days' => 365,
                'salary_increase_effective_month' => null,
                'description' => 'Default payroll policies effective from 2025',
                'created_by' => 'system',
                'updated_by' => 'system',
            ]
        );

        $this->command->info('Payroll policy settings seeded: '.PayrollPolicySetting::count().' policies.');
    }
}
