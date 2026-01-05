<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmployeeFundingAllocation;
use Carbon\Carbon;

echo "Fixing empty salary_type values...\n\n";

$allocations = EmployeeFundingAllocation::where(function ($q) {
    $q->where('salary_type', '')->orWhereNull('salary_type');
})->get();

echo "Found {$allocations->count()} allocations to fix\n";

foreach ($allocations as $alloc) {
    $employment = $alloc->employment;
    if ($employment) {
        $today = Carbon::today();
        $salaryType = 'pass_probation_salary';

        if ($employment->pass_probation_date && $today->lt($employment->pass_probation_date)) {
            $salaryType = $employment->probation_salary ? 'probation_salary' : 'pass_probation_salary';
        }

        $alloc->update(['salary_type' => $salaryType]);
        echo "  ✓ Updated Allocation #{$alloc->id}: salary_type = {$salaryType}\n";
    }
}

echo "\n=== VERIFICATION ===\n";
$all = EmployeeFundingAllocation::with('employment.employee')->get();
foreach ($all as $alloc) {
    echo "Allocation #{$alloc->id}: {$alloc->employment->employee->staff_id}";
    echo ' | FTE: '.($alloc->fte * 100).'%';
    echo ' | Amount: '.number_format($alloc->allocated_amount, 2);
    echo " | Type: {$alloc->salary_type}";
    echo " | Status: {$alloc->status}\n";
}

echo "\nDone!\n";
