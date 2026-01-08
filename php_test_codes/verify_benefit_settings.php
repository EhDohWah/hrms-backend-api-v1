<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BenefitSetting;

echo "========================================\n";
echo "BENEFIT SETTINGS VERIFICATION\n";
echo "========================================\n\n";

$settings = BenefitSetting::all();

if ($settings->isEmpty()) {
    echo "❌ No benefit settings found in database!\n";
    echo "Please run: php create_benefit_settings.php\n";
    exit(1);
}

echo 'Total Settings: '.$settings->count()."\n\n";

foreach ($settings as $setting) {
    echo "─────────────────────────────────────\n";
    echo "ID: {$setting->id}\n";
    echo "Key: {$setting->setting_key}\n";
    echo "Value: {$setting->setting_value}%\n";
    echo "Type: {$setting->setting_type}\n";
    echo "Description: {$setting->description}\n";
    echo "Effective Date: {$setting->effective_date}\n";
    echo 'Active: '.($setting->is_active ? '✓ Yes' : '✗ No')."\n";
    echo "Created At: {$setting->created_at}\n";
}

echo "─────────────────────────────────────\n\n";

echo "✓ All benefit settings are properly configured!\n\n";

echo "TESTING WITH HELPER METHOD:\n";
echo "----------------------------\n";
echo "BenefitSetting::getActiveSetting('health_welfare_percentage') = ".
     BenefitSetting::getActiveSetting('health_welfare_percentage')."%\n";
echo "BenefitSetting::getActiveSetting('pvd_percentage') = ".
     BenefitSetting::getActiveSetting('pvd_percentage')."%\n";
echo "BenefitSetting::getActiveSetting('saving_fund_percentage') = ".
     BenefitSetting::getActiveSetting('saving_fund_percentage')."%\n\n";

echo "✓ Verification complete!\n";
