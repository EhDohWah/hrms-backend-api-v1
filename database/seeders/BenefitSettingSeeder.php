<?php

namespace Database\Seeders;

use App\Models\BenefitSetting;
use Illuminate\Database\Seeder;

class BenefitSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'setting_key' => 'health_welfare_percentage',
                'setting_value' => 5.00,
                'setting_type' => 'percentage',
                'description' => 'Health and Welfare contribution percentage',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'pvd_percentage',
                'setting_value' => 7.5,
                'setting_type' => 'percentage',
                'description' => 'Provident Fund contribution percentage',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'saving_fund_percentage',
                'setting_value' => 7.5,
                'setting_type' => 'percentage',
                'description' => 'Saving Fund contribution percentage',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'social_security_percentage',
                'setting_value' => 5.00,
                'setting_type' => 'percentage',
                'description' => 'Social Security contribution percentage (both employee and employer)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'social_security_max_amount',
                'setting_value' => 750.00,
                'setting_type' => 'numeric',
                'description' => 'Maximum Social Security contribution amount in THB',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
        ];

        foreach ($settings as $setting) {
            BenefitSetting::updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                $setting
            );
        }
    }
}
