<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BenefitSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "CREATING BENEFIT SETTINGS\n";
echo "========================================\n\n";

try {
    DB::beginTransaction();

    echo "Checking existing benefit settings...\n";
    $existingCount = BenefitSetting::count();

    if ($existingCount > 0) {
        echo "⚠️  Found {$existingCount} existing benefit settings.\n";
        echo 'Do you want to delete them and create new ones? (y/n): ';
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        fclose($handle);

        if (trim($line) !== 'y') {
            echo "Aborted. No changes made.\n";
            DB::rollBack();
            exit(0);
        }

        echo "Deleting existing benefit settings...\n";
        BenefitSetting::truncate();
        echo "✓ Deleted all existing benefit settings\n\n";
    }

    echo "Creating benefit settings...\n\n";

    // 1. Health & Welfare Percentage
    $healthWelfare = BenefitSetting::create([
        'setting_key' => 'health_welfare_percentage',
        'setting_value' => 5.00,
        'setting_type' => 'percentage',
        'description' => 'Health and Welfare contribution percentage',
        'effective_date' => Carbon::now()->startOfYear(),
        'is_active' => true,
        'applies_to' => null, // Applies to all
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "✓ Created: {$healthWelfare->setting_key} = {$healthWelfare->setting_value}%\n";
    echo "  Description: {$healthWelfare->description}\n";
    echo "  Effective Date: {$healthWelfare->effective_date}\n";
    echo '  Status: '.($healthWelfare->is_active ? 'Active' : 'Inactive')."\n\n";

    // 2. PVD (Provident Fund) Percentage
    $pvd = BenefitSetting::create([
        'setting_key' => 'pvd_percentage',
        'setting_value' => 3.00,
        'setting_type' => 'percentage',
        'description' => 'Provident Fund contribution percentage',
        'effective_date' => Carbon::now()->startOfYear(),
        'is_active' => true,
        'applies_to' => null, // Applies to all
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "✓ Created: {$pvd->setting_key} = {$pvd->setting_value}%\n";
    echo "  Description: {$pvd->description}\n";
    echo "  Effective Date: {$pvd->effective_date}\n";
    echo '  Status: '.($pvd->is_active ? 'Active' : 'Inactive')."\n\n";

    // 3. Saving Fund Percentage
    $savingFund = BenefitSetting::create([
        'setting_key' => 'saving_fund_percentage',
        'setting_value' => 3.00,
        'setting_type' => 'percentage',
        'description' => 'Saving Fund contribution percentage',
        'effective_date' => Carbon::now()->startOfYear(),
        'is_active' => true,
        'applies_to' => null, // Applies to all
        'created_by' => 'system',
        'updated_by' => 'system',
    ]);
    echo "✓ Created: {$savingFund->setting_key} = {$savingFund->setting_value}%\n";
    echo "  Description: {$savingFund->description}\n";
    echo "  Effective Date: {$savingFund->effective_date}\n";
    echo '  Status: '.($savingFund->is_active ? 'Active' : 'Inactive')."\n\n";

    DB::commit();

    echo "========================================\n";
    echo "BENEFIT SETTINGS CREATED SUCCESSFULLY!\n";
    echo "========================================\n\n";

    echo "SUMMARY:\n";
    echo "--------\n";
    echo "✓ Health & Welfare Percentage: 5.00%\n";
    echo "✓ PVD Percentage: 3.00%\n";
    echo "✓ Saving Fund Percentage: 3.00%\n\n";

    echo "NOTES:\n";
    echo "------\n";
    echo '1. All settings are active and effective from '.Carbon::now()->startOfYear()->format('Y-m-d')."\n";
    echo "2. These percentages apply globally to all employments\n";
    echo "3. Individual employments only store boolean flags (enabled/disabled)\n";
    echo "4. The actual percentages are fetched from this benefit_settings table\n\n";

    echo "CACHE INFO:\n";
    echo "-----------\n";
    echo "Benefit settings are cached for 1 hour for performance.\n";
    echo "Cache is automatically cleared when settings are updated.\n\n";

    echo "TESTING:\n";
    echo "--------\n";
    echo "You can now test the employment API endpoints:\n";
    echo "- GET /api/v1/employments (should show benefit percentages)\n";
    echo "- POST /api/v1/employments (should only need boolean flags)\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: ".$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
    exit(1);
}
