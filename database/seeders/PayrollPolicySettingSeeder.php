<?php

namespace Database\Seeders;

use App\Models\PayrollPolicySetting;
use Illuminate\Database\Seeder;

/**
 * Seeds payroll_policy_settings — one row per policy.
 *
 * - 13th Month Salary: just active/inactive (divisor 12 hardcoded, applies in December)
 * - Salary Increase:   percentage rate (1%), applies in January to all employees receiving January payroll
 *
 * Idempotent: uses firstOrCreate keyed on policy_key.
 */
class PayrollPolicySettingSeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'policy_key' => PayrollPolicySetting::KEY_THIRTEENTH_MONTH,
                'policy_value' => null,
                'setting_type' => 'boolean',
                'category' => PayrollPolicySetting::KEY_THIRTEENTH_MONTH,
                'description' => 'Enable/disable 13th month salary calculation (YTD gross / 12 in December)',
                'effective_date' => '2025-01-01',
            ],
            [
                'policy_key' => PayrollPolicySetting::KEY_SALARY_INCREASE,
                'policy_value' => 1.00,
                'setting_type' => 'percentage',
                'category' => PayrollPolicySetting::KEY_SALARY_INCREASE,
                'description' => 'Annual salary increase rate applied in January to all employees receiving January payroll',
                'effective_date' => '2025-01-01',
            ],
        ];

        foreach ($policies as $policy) {
            PayrollPolicySetting::firstOrCreate(
                ['policy_key' => $policy['policy_key']],
                array_merge($policy, [
                    'is_active' => true,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                ])
            );
        }

        $this->command->info('Payroll policy settings seeded: '.PayrollPolicySetting::count().' policies.');
    }
}
