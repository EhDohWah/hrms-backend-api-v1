# Reset Bulk Payroll for Testing

## Issue
The queue worker might be running the OLD code from before the fix was applied. Queue workers cache the code in memory and need to be restarted.

## Solution Steps

### Step 1: Stop ALL Queue Workers

**Windows (Git Bash/MSYS2)**:
```bash
# Find and kill queue workers
ps aux | grep "queue:work"

# Kill specific processes (replace PID with actual process ID)
kill -9 <PID>

# Or use taskkill on Windows
taskkill /F /IM php.exe
```

**Alternative**: Just close your terminal running `php artisan queue:work`

### Step 2: Clear Laravel Caches
```bash
cd "C:\Users\Turtle\Desktop\HR Management System\3. Implementation\HRMS-V1\hrms-backend-api-v1"

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan queue:restart
```

### Step 3: Clear Failed Queue Jobs
```bash
# Clear all failed jobs
php artisan queue:flush

# Or clear specific queue
php artisan queue:clear import
```

### Step 4: Delete Failed Batch Record

**SQL**:
```sql
-- Delete failed batch
DELETE FROM bulk_payroll_batches WHERE id = 1;

-- Or truncate to reset ID counter
TRUNCATE TABLE bulk_payroll_batches;

-- Verify it's gone
SELECT * FROM bulk_payroll_batches;
```

### Step 5: Verify Code Changes

```bash
# Check that the fix is in place
grep -n "gross_salary_by_fte" app/Services/PayrollService.php

# Should show line 452:
# 452:    'gross_salary_by_fte' => $grossSalaryCurrentYearByFTE, // Fixed: lowercase 'fte'
```

### Step 6: Restart Queue Worker with Fresh Code

```bash
# Start queue worker with verbose output
php artisan queue:work --queue=import --verbose

# Keep this terminal open to monitor the job
```

### Step 7: Test Bulk Payroll Creation

1. Open frontend: `http://localhost:8080/payroll/employee-salary`
2. Click "Create Bulk Payroll"
3. Select:
   - Pay Period: **October 2025**
   - Subsidiary: **SMRU**
4. Click "Calculate Preview"
5. Click "Create Payroll (1)"
6. Watch the queue worker terminal for:
   ```
   [2025-10-27 XX:XX:XX] Processing: App\Jobs\ProcessBulkPayroll
   [2025-10-27 XX:XX:XX] Processed:  App\Jobs\ProcessBulkPayroll
   ```

### Step 8: Verify Success

**Check Database**:
```sql
-- Should show payroll records NOW
SELECT * FROM payrolls
WHERE payroll_month = '2025-10';

-- Should show successful batch
SELECT
    id,
    pay_period,
    total_payrolls,
    successful_payrolls,
    failed_payrolls,
    status,
    errors
FROM bulk_payroll_batches
WHERE id = (SELECT MAX(id) FROM bulk_payroll_batches);
```

**Expected Result**:
```
id: 2 (or next ID)
pay_period: 2025-10
total_payrolls: 1
successful_payrolls: 1  ← Should be 1 now!
failed_payrolls: 0      ← Should be 0 now!
status: completed
errors: []              ← Should be empty!
```

---

## If Still Failing

### Debug: Check What Keys Are Actually Being Passed

Add temporary debug logging to `ProcessBulkPayroll.php:148`:

```php
// Add BEFORE line 148
Log::info('PayrollService returned keys:', [
    'keys' => array_keys($payrollData['calculations'] ?? []),
    'has_gross_salary_by_fte' => isset($payrollData['calculations']['gross_salary_by_fte']),
    'has_gross_salary_by_FTE' => isset($payrollData['calculations']['gross_salary_by_FTE']),
]);

// Existing line 148
$payrollData = $payrollService->calculateAllocationPayrollForController(
    $employee,
    $allocation,
    $payPeriodDate
);
```

Then check `storage/logs/laravel.log` to see what keys are actually present.

### Debug: Check Employee Data

The error shows `"employee":"Unknown"` which suggests the employee relationship might not be loaded properly.

Check the employment record:
```sql
SELECT
    e.id,
    e.employee_id,
    emp.first_name_en,
    emp.last_name_en,
    emp.staff_id
FROM employments e
LEFT JOIN employees emp ON e.employee_id = emp.id
WHERE e.id = 1;
```

If `employee_id` is NULL, that's the problem!

---

## Common Pitfalls

### ❌ Queue Worker Not Restarted
**Symptom**: Still getting old error even after code fix
**Solution**: Kill the old worker and start a new one

### ❌ Cache Not Cleared
**Symptom**: Changes not taking effect
**Solution**: Run all cache clear commands

### ❌ Wrong Key Still in Code
**Symptom**: Error persists
**Solution**: Verify line 452 has `'gross_salary_by_fte'` (lowercase)

### ❌ Employee Data Missing
**Symptom**: "Unknown" employee in error
**Solution**: Ensure employment has valid `employee_id`

---

## Quick Reset Script (Windows Git Bash)

Save as `reset_bulk_payroll.sh`:

```bash
#!/bin/bash

echo "🔧 Resetting Bulk Payroll System..."

# Navigate to backend
cd "C:/Users/Turtle/Desktop/HR Management System/3. Implementation/HRMS-V1/hrms-backend-api-v1"

# Clear caches
echo "📦 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan queue:restart

# Clear queue
echo "🗑️  Clearing queue..."
php artisan queue:flush
php artisan queue:clear import

echo "✅ System reset complete!"
echo "⚠️  Now:"
echo "   1. Delete bulk_payroll_batches record manually in DB"
echo "   2. Restart queue worker: php artisan queue:work --queue=import --verbose"
echo "   3. Test bulk payroll creation"
```

Run with:
```bash
bash reset_bulk_payroll.sh
```

---

**Next Step**: Follow Step 1-8 above to properly reset and test the system with the fixed code.
