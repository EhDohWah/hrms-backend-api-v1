<?php

namespace Database\Seeders;

use App\Models\BenefitSetting;
use Illuminate\Database\Seeder;

class BenefitSettingSeeder extends Seeder
{
    /**
     * Seed health welfare benefit settings.
     *
     * SSF, PVD, and Saving Fund settings are managed in TaxSettingSeeder
     * (single source of truth). BenefitSetting only holds health welfare tiers.
     */
    public function run(): void
    {
        $settings = [
            [
                'setting_key' => 'health_welfare_percentage',
                'setting_value' => 5.00,
                'setting_type' => 'percentage',
                'description' => 'Health and Welfare contribution percentage (display only)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'health_welfare_high_threshold',
                'setting_value' => 15000.00,
                'setting_type' => 'numeric',
                'description' => 'Salary threshold for high tier health welfare contribution (Baht)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'health_welfare_high_amount',
                'setting_value' => 150.00,
                'setting_type' => 'numeric',
                'description' => 'Health welfare contribution for high tier (salary > high threshold)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'health_welfare_medium_threshold',
                'setting_value' => 5000.00,
                'setting_type' => 'numeric',
                'description' => 'Salary threshold for medium tier health welfare contribution (Baht)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'health_welfare_medium_amount',
                'setting_value' => 100.00,
                'setting_type' => 'numeric',
                'description' => 'Health welfare contribution for medium tier (salary > medium threshold)',
                'effective_date' => '2025-01-01',
                'is_active' => true,
                'applies_to' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ],
            [
                'setting_key' => 'health_welfare_low_amount',
                'setting_value' => 60.00,
                'setting_type' => 'numeric',
                'description' => 'Health welfare contribution for low tier (salary <= medium threshold)',
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
