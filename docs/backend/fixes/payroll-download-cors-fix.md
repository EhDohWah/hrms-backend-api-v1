# Payroll Template Download CORS Error Fix

## Issue Description

**Problem:** CORS error when downloading payroll template from frontend
**Error Message:** "CORS error" with status 200 OK but no file downloaded
**Affected Endpoint:** `GET /api/v1/downloads/payroll-template`

## Root Cause Analysis

### The Problem
The `PayrollController::downloadTemplate()` method was using **raw PHP headers** and **direct output** instead of Laravel's response helper:

```php
❌ INCORRECT APPROACH (Causing CORS Error):

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
```

**Why This Causes CORS Errors:**
1. Raw `header()` calls bypass Laravel's middleware stack
2. CORS middleware doesn't get a chance to add required headers
3. The `exit` statement terminates the request before middleware can finish
4. Browser receives response without proper CORS headers and blocks the download

### The Solution
Use Laravel's `response()->download()` helper which properly integrates with middleware:

```php
✅ CORRECT APPROACH (Fixes CORS):

// Create temporary file
$tempFile = tempnam(sys_get_temp_dir(), 'payroll_template_');
$writer->save($tempFile);

// Define response headers
$headers = [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    'Cache-Control' => 'max-age=0',
];

// Return file download response with proper CORS headers
return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);
```

**Why This Works:**
1. Uses Laravel's response helper which respects middleware
2. CORS middleware can add required headers (`Access-Control-Allow-Origin`, etc.)
3. File is saved to temp location first, then streamed properly
4. `deleteFileAfterSend(true)` cleans up the temp file automatically
5. Browser receives proper CORS headers and allows the download

## Comparison with Other Controllers

### All Other Controllers (Working Correctly) ✅

**GrantController:**
```php
return response()->download($tempFile, $filename, [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    'Cache-Control' => 'no-cache, must-revalidate',
    'Pragma' => 'no-cache',
    'Expires' => '0',
]);
```

**EmployeeController:**
```php
return response()->download($tempFile, $filename, [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    'Cache-Control' => 'no-cache, must-revalidate',
    'Pragma' => 'no-cache',
    'Expires' => '0',
]);
```

**EmploymentController:**
```php
return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);
```

**EmployeeFundingAllocationController:**
```php
return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);
```

### PayrollController (Before Fix) ❌

```php
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
```

### PayrollController (After Fix) ✅

```php
// Create temporary file
$tempFile = tempnam(sys_get_temp_dir(), 'payroll_template_');
$writer->save($tempFile);

// Define response headers
$headers = [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    'Cache-Control' => 'max-age=0',
];

// Return file download response with proper CORS headers
return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);
```

## Changes Made

### File Modified
`app/Http/Controllers/Api/PayrollController.php`

### Specific Changes

**Before (Lines 3096-3105):**
```php
$filename = 'payroll_import_template_'.date('Y-m-d_His').'.xlsx';

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
```

**After (Lines 3096-3112):**
```php
$filename = 'payroll_import_template_'.date('Y-m-d_His').'.xlsx';

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

// Create temporary file
$tempFile = tempnam(sys_get_temp_dir(), 'payroll_template_');
$writer->save($tempFile);

// Define response headers
$headers = [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    'Cache-Control' => 'max-age=0',
];

// Return file download response with proper CORS headers
return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);
```

## Benefits of the Fix

### 1. CORS Compatibility ✅
- Properly integrates with Laravel's CORS middleware
- Browser receives required CORS headers
- Download works from frontend applications

### 2. Consistency ✅
- Now matches the pattern used by all other controllers
- Easier to maintain and understand
- Follows Laravel best practices

### 3. Better Resource Management ✅
- `deleteFileAfterSend(true)` automatically cleans up temp files
- No memory leaks or orphaned files
- More efficient than direct output

### 4. Middleware Compatibility ✅
- Works with all Laravel middleware
- Authentication, authorization, logging all function properly
- No bypassing of security measures

### 5. Error Handling ✅
- Exceptions are properly caught and returned as JSON
- No abrupt `exit` statements
- Better debugging capabilities

## Testing

### Before Fix
```
❌ CORS Error
- Status: 200 OK
- Response: (blocked by CORS)
- File: Not downloaded
- Console: CORS policy error
```

### After Fix
```
✅ Success
- Status: 200 OK
- Response: Excel file blob
- File: Downloaded successfully
- Filename: payroll_import_template_2026-01-09_102036.xlsx
```

### Test Steps
1. Navigate to Administration → File Uploads
2. Scroll to Payroll Data section
3. Click "Download Template" link
4. Verify file downloads successfully
5. Open file and verify it contains proper template structure

## Related Files

### Backend
- `app/Http/Controllers/Api/PayrollController.php` - Fixed method
- `routes/api/uploads.php` - Route definition
- `app/Http/Middleware/Cors.php` - CORS middleware (if custom)

### Frontend
- `src/services/upload-payroll.service.js` - Service calling the endpoint
- `src/components/uploads/payroll-upload.vue` - Component using the service

## Laravel Best Practices

### ✅ DO: Use Laravel Response Helpers
```php
return response()->download($file, $filename, $headers);
return response()->json($data);
return response()->file($file);
```

### ❌ DON'T: Use Raw PHP Headers
```php
header('Content-Type: application/json');
echo json_encode($data);
exit;
```

### Why?
- Laravel's response helpers integrate with middleware
- Proper exception handling
- Testability
- Consistency
- Framework features (CORS, compression, etc.)

## Additional Notes

### Temporary File Cleanup
The `deleteFileAfterSend(true)` method ensures the temporary file is deleted after the response is sent to the client. This prevents:
- Disk space issues
- Orphaned files in temp directory
- Security concerns (sensitive data in temp files)

### Alternative Approaches
If you need to stream large files without creating temp files, use:
```php
return response()->streamDownload(function () use ($writer) {
    $writer->save('php://output');
}, $filename, $headers);
```

However, `response()->download()` with temp files is preferred for:
- Better error handling
- Middleware compatibility
- Easier testing
- More reliable CORS support

## Verification

### Code Formatting
```bash
vendor/bin/pint app/Http/Controllers/Api/PayrollController.php
```
Result: ✅ PASS - 1 file formatted

### Manual Testing
1. ✅ Template downloads successfully
2. ✅ No CORS errors in browser console
3. ✅ File has correct name with timestamp
4. ✅ File opens in Excel without errors
5. ✅ Template contains all required sheets and data

### Automated Testing (Recommended)
```php
public function test_payroll_template_download()
{
    $user = User::factory()->create();
    $user->givePermissionTo('employee_salary.read');
    
    $response = $this->actingAs($user)
        ->get('/api/v1/downloads/payroll-template');
    
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertDownload();
}
```

## Conclusion

The CORS error was caused by using raw PHP headers instead of Laravel's response helpers. By switching to `response()->download()`, the payroll template download now:

✅ Works correctly with CORS
✅ Matches other controllers' implementation
✅ Follows Laravel best practices
✅ Properly integrates with middleware
✅ Automatically cleans up temp files

**Status:** ✅ Fixed and Tested
**Date:** January 9, 2026
**Impact:** High - Fixes critical functionality
**Breaking Changes:** None - API endpoint unchanged
