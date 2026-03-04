<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $effectiveDate = '2025-01-01';

        $contributionSettings = [
            // Social Security Fund
            [
                'setting_key' => 'ssf_employee_rate',
                'setting_value' => 5.0,
                'setting_type' => 'percentage',
                'category' => 'social_security',
                'description' => 'Employee Social Security Fund contribution rate - 5%',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'ssf_employer_rate',
                'setting_value' => 5.0,
                'setting_type' => 'percentage',
                'category' => 'social_security',
                'description' => 'Employer Social Security Fund contribution rate - 5%',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'ssf_min_salary',
                'setting_value' => 1650.0,
                'setting_type' => 'numeric',
                'category' => 'social_security',
                'description' => 'Minimum monthly salary for SSF calculation - 1,650 Baht',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'ssf_max_salary',
                'setting_value' => 15000.0,
                'setting_type' => 'numeric',
                'category' => 'social_security',
                'description' => 'Maximum monthly salary for SSF calculation - 15,000 Baht',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'ssf_max_monthly',
                'setting_value' => 750.0,
                'setting_type' => 'numeric',
                'category' => 'social_security',
                'description' => 'Maximum monthly SSF contribution - 750 Baht',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'ssf_max_yearly',
                'setting_value' => 9000.0,
                'setting_type' => 'numeric',
                'category' => 'social_security',
                'description' => 'Maximum annual SSF contribution - 9,000 Baht',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Provident Fund
            [
                'setting_key' => 'pvd_employee_rate',
                'setting_value' => 7.5,
                'setting_type' => 'percentage',
                'category' => 'provident_fund',
                'description' => 'Employee Provident Fund (PVD) contribution rate for Local ID employees',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'pvd_employer_rate',
                'setting_value' => 7.5,
                'setting_type' => 'percentage',
                'category' => 'provident_fund',
                'description' => 'Employer Provident Fund (PVD) contribution rate',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'pvd_max_annual',
                'setting_value' => 500000.0,
                'setting_type' => 'numeric',
                'category' => 'provident_fund',
                'description' => 'Maximum annual Provident Fund contribution - 500,000 Baht',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Saving Fund
            [
                'setting_key' => 'saving_fund_employee_rate',
                'setting_value' => 7.5,
                'setting_type' => 'percentage',
                'category' => 'saving_fund',
                'description' => 'Employee Saving Fund contribution rate for Local non ID employees',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'saving_fund_employer_rate',
                'setting_value' => 7.5,
                'setting_type' => 'percentage',
                'category' => 'saving_fund',
                'description' => 'Employer Saving Fund contribution rate',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'saving_fund_max_annual',
                'setting_value' => 500000.0,
                'setting_type' => 'numeric',
                'category' => 'saving_fund',
                'description' => 'Maximum annual Saving Fund contribution - 500,000 Baht',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Health Welfare Employer
            [
                'setting_key' => 'health_welfare_employer_enabled',
                'setting_value' => 1.0,
                'setting_type' => 'boolean',
                'category' => 'health_welfare',
                'description' => 'Employer pays health welfare for eligible employees (Non-Thai ID, Expat at SMRU)',
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'applies_to' => json_encode([
                    'eligible_statuses' => ['Non-Thai ID', 'Expat'],
                    'eligible_organizations' => ['SMRU'],
                ]),
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Insert new contribution settings (skip if already exists)
        foreach ($contributionSettings as $setting) {
            $exists = DB::table('benefit_settings')
                ->where('setting_key', $setting['setting_key'])
                ->exists();

            if (! $exists) {
                DB::table('benefit_settings')->insert($setting);
            }
        }

        // Mark old SSF/PVD/Saving Fund rows in tax_settings as deselected
        $oldKeys = [
            'SSF_RATE', 'SSF_MIN_SALARY', 'SSF_MAX_SALARY', 'SSF_MAX_MONTHLY', 'SSF_MAX_YEARLY',
            'PVD_FUND_RATE', 'PVD_FUND_MAX', 'SAVING_FUND_RATE', 'SAVING_FUND_MAX',
        ];

        DB::table('tax_settings')
            ->whereIn('setting_key', $oldKeys)
            ->update([
                'is_selected' => false,
                'updated_at' => $now,
            ]);

        // Append audit note to description
        DB::table('tax_settings')
            ->whereIn('setting_key', $oldKeys)
            ->whereNotNull('description')
            ->where('description', 'not like', '%[MOVED TO BENEFIT_SETTINGS]%')
            ->update([
                'description' => DB::raw("CONCAT(description, ' [MOVED TO BENEFIT_SETTINGS]')"),
            ]);
    }

    public function down(): void
    {
        // Re-enable the old tax_settings rows
        $oldKeys = [
            'SSF_RATE', 'SSF_MIN_SALARY', 'SSF_MAX_SALARY', 'SSF_MAX_MONTHLY', 'SSF_MAX_YEARLY',
            'PVD_FUND_RATE', 'PVD_FUND_MAX', 'SAVING_FUND_RATE', 'SAVING_FUND_MAX',
        ];

        DB::table('tax_settings')
            ->whereIn('setting_key', $oldKeys)
            ->update(['is_selected' => true]);

        // Remove the migrated contribution settings
        $newKeys = [
            'ssf_employee_rate', 'ssf_employer_rate', 'ssf_min_salary', 'ssf_max_salary',
            'ssf_max_monthly', 'ssf_max_yearly', 'pvd_employee_rate', 'pvd_employer_rate',
            'pvd_max_annual', 'saving_fund_employee_rate', 'saving_fund_employer_rate',
            'saving_fund_max_annual', 'health_welfare_employer_enabled',
        ];

        DB::table('benefit_settings')
            ->whereIn('setting_key', $newKeys)
            ->delete();
    }
};
