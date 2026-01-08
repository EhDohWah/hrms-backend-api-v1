# Upload Menu Creation Guide

> **Version:** 1.0  
> **Last Updated:** January 8, 2026  
> **Purpose:** Step-by-step guide for creating new upload menus in the HRMS system

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Information Needed](#information-needed)
4. [Backend Implementation](#backend-implementation)
5. [Frontend Implementation](#frontend-implementation)
6. [Permission Setup](#permission-setup)
7. [Testing Checklist](#testing-checklist)
8. [Common Patterns](#common-patterns)
9. [Troubleshooting](#troubleshooting)

---

## Overview

This guide provides a systematic approach to adding new bulk upload functionality to the HRMS system. Each upload menu requires:

- **Backend:** Database table, model, import class, controller methods, routes
- **Frontend:** Service, component, integration with upload list page
- **Permissions:** Module definition, permission seeding, role assignment

---

## Prerequisites

Before starting, ensure you have:

✅ Database table created (migration)  
✅ Eloquent model with fillable fields  
✅ Understanding of the data structure  
✅ Sample data for template  
✅ Knowledge of validation rules  

---

## Information Needed

### Required Information Checklist

Before creating a new upload menu, provide the following information:

#### 1. **Module Information**

```yaml
Module Name: [e.g., "employee_salaries", "leave_applications"]
Display Name: [e.g., "Employee Salaries", "Leave Applications"]
Description: [e.g., "Manage employee salary records"]
Category: [e.g., "Employee", "Payroll", "Leave", "Grants"]
Icon: [e.g., "wallet", "calendar", "award"] # Tabler Icons
Route: [e.g., "/employee/salaries", "/leave/applications"]
```

#### 2. **Database Table Information**

```yaml
Table Name: [e.g., "employee_salaries"]
Model Name: [e.g., "EmployeeSalary"]
Migration File: [e.g., "2025_01_08_create_employee_salaries_table.php"]
```

#### 3. **Template Columns**

List all columns for the Excel template:

```yaml
Columns:
  - name: staff_id
    type: string
    required: true
    validation: "Employee staff ID (must exist in system)"
    sample: "EMP001"
    
  - name: effective_date
    type: date
    required: true
    validation: "Date (YYYY-MM-DD)"
    sample: "2025-01-01"
    
  - name: base_salary
    type: decimal
    required: true
    validation: "Decimal(10,2) - Base salary amount"
    sample: "50000.00"
  
  # ... add all columns
```

#### 4. **Upload Behavior**

```yaml
Duplicate Detection:
  match_fields: ["employee_id", "effective_date"]
  on_duplicate: "update" # or "skip" or "error"
  
Auto-Calculations:
  - field: employee_id
    logic: "Lookup from staff_id"
  - field: total_salary
    logic: "base_salary + allowances"
    
Relationships:
  - field: employee_id
    references: "employees.id"
  - field: position_id
    references: "positions.id"
```

#### 5. **File Naming**

```yaml
Controller: [e.g., "EmployeeSalaryController"]
Import Class: [e.g., "EmployeeSalariesImport"]
Frontend Service: [e.g., "upload-salary.service.js"]
Frontend Component: [e.g., "salary-upload.vue"]
Route Names:
  upload: [e.g., "uploads.employee-salary"]
  template: [e.g., "downloads.employee-salary-template"]
```

#### 6. **UI Information**

```yaml
Section Name: [e.g., "Employee Salary Uploads"]
Section Icon: [e.g., "ti ti-wallet"]
Section Description: [e.g., "Upload Excel file with employee salary information"]
Position: [e.g., "Below Employment, Above Payroll"]
Color Theme: [e.g., "#ffc107"] # Hex color for category header
```

---

## Backend Implementation

### Step 1: Create Import Class

**File:** `app/Imports/[YourModule]Import.php`

```php
<?php

namespace App\Imports;

use App\Models\YourModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Validators\Failure;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Notifications\ImportedCompletedNotification;
use App\Models\User;

class YourModuleImport extends DefaultValueBinder implements 
    ShouldQueue, 
    SkipsEmptyRows, 
    SkipsOnFailure, 
    ToCollection, 
    WithChunkReading, 
    WithCustomValueBinder, 
    WithEvents, 
    WithHeadingRow
{
    use Importable, RegistersEventListeners;

    public $userId;
    public $importId;

    // Prefetch data for performance
    protected $lookupData = [];

    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;

        // Prefetch lookup data (e.g., employee IDs, positions, etc.)
        $this->lookupData['employees'] = Employee::pluck('id', 'staff_id')->toArray();

        // Initialize cache
        Cache::put("import_{$this->importId}_errors", [], 3600);
        Cache::put("import_{$this->importId}_validation_failures", [], 3600);
        Cache::put("import_{$this->importId}_processed_count", 0, 3600);
        Cache::put("import_{$this->importId}_updated_count", 0, 3600);
    }

    public function bindValue(Cell $cell, $value)
    {
        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
        return true;
    }

    public function onFailure(Failure ...$failures)
    {
        $errors = Cache::get("import_{$this->importId}_errors", []);
        $validationFailures = Cache::get("import_{$this->importId}_validation_failures", []);

        foreach ($failures as $failure) {
            $validationFailure = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
            $validationFailures[] = $validationFailure;

            $msg = "Row {$failure->row()} [{$failure->attribute()}]: " 
                . implode(', ', $failure->errors());
            Log::warning($msg, ['values' => $failure->values()]);
            $errors[] = $msg;
        }

        Cache::put("import_{$this->importId}_errors", $errors, 3600);
        Cache::put("import_{$this->importId}_validation_failures", $validationFailures, 3600);
    }

    public function collection(Collection $rows)
    {
        Log::info('Import chunk started', [
            'rows_in_chunk' => $rows->count(),
            'import_id' => $this->importId,
        ]);

        // Normalize data (dates, numbers, etc.)
        $normalized = $rows->map(function ($r) {
            // Normalize date fields
            foreach (['start_date', 'end_date'] as $dateField) {
                if (!empty($r[$dateField]) && is_numeric($r[$dateField])) {
                    try {
                        $r[$dateField] = ExcelDate::excelToDateTimeObject($r[$dateField])->format('Y-m-d');
                    } catch (\Exception $e) {
                        Log::warning("Failed to parse {$dateField}", [
                            'value' => $r[$dateField],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            return $r;
        });

        // Validate
        $validator = Validator::make(
            $normalized->toArray(),
            $this->rules(),
            $this->messages()
        );

        if ($validator->fails()) {
            Log::error('Validation failed for chunk', [
                'errors' => $validator->errors()->all(),
                'import_id' => $this->importId,
            ]);
            foreach ($validator->errors()->all() as $error) {
                $this->onFailure(new Failure(0, '', [$error], []));
            }
            return;
        }

        DB::disableQueryLog();

        try {
            DB::transaction(function () use ($normalized) {
                $batch = [];
                $updates = [];
                $errors = Cache::get("import_{$this->importId}_errors", []);

                foreach ($normalized as $index => $row) {
                    if (!$row->filter()->count()) {
                        continue;
                    }

                    // YOUR IMPORT LOGIC HERE
                    // 1. Validate and lookup foreign keys
                    // 2. Prepare data array
                    // 3. Check for duplicates
                    // 4. Add to batch or updates array

                    // Example:
                    $data = [
                        'field1' => $row['field1'],
                        'field2' => $row['field2'],
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Check for duplicates
                    $existing = YourModel::where('unique_field', $row['unique_field'])->first();
                    
                    if ($existing) {
                        $updates[$existing->id] = $data;
                    } else {
                        $batch[] = $data;
                    }
                }

                Cache::put("import_{$this->importId}_errors", $errors, 3600);

                // Insert new records
                if (count($batch)) {
                    YourModel::insert($batch);
                    $currentCount = Cache::get("import_{$this->importId}_processed_count", 0);
                    Cache::put("import_{$this->importId}_processed_count", $currentCount + count($batch), 3600);
                }

                // Update existing records
                if (count($updates)) {
                    foreach ($updates as $id => $data) {
                        unset($data['created_at']);
                        YourModel::where('id', $id)->update($data);
                    }
                    $currentUpdatedCount = Cache::get("import_{$this->importId}_updated_count", 0);
                    Cache::put("import_{$this->importId}_updated_count", $currentUpdatedCount + count($updates), 3600);
                }
            });
        } catch (\Exception $e) {
            $errorMessage = 'Error: ' . $e->getMessage();
            $errors = Cache::get("import_{$this->importId}_errors", []);
            $errors[] = $errorMessage;
            Cache::put("import_{$this->importId}_errors", $errors, 3600);
            Log::error('Import failed', ['exception' => $e->getMessage()]);
        }

        Log::info('Finished processing chunk', ['import_id' => $this->importId]);
    }

    public function rules(): array
    {
        return [
            '*.field1' => 'required|string',
            '*.field2' => 'required|numeric',
            // Add all your validation rules
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function chunkSize(): int
    {
        return 50;
    }

    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {
                $exception = $event->getException();
                Log::error('Import failed', ['error' => $exception->getMessage()]);
                
                $user = User::find($this->userId);
                if ($user) {
                    $user->notify(new ImportedCompletedNotification(
                        'Import failed: ' . $exception->getMessage()
                    ));
                }
            },
            AfterImport::class => function (AfterImport $event) {
                $errors = Cache::get("import_{$this->importId}_errors", []);
                $processedCount = Cache::get("import_{$this->importId}_processed_count", 0);
                $updatedCount = Cache::get("import_{$this->importId}_updated_count", 0);

                $message = "Import finished! Created: {$processedCount}, Updated: {$updatedCount}";
                if (count($errors) > 0) {
                    $message .= ', Errors: ' . count($errors);
                }

                $user = User::find($this->userId);
                if ($user) {
                    $user->notify(new ImportedCompletedNotification($message));
                }

                // Clean up cache
                Cache::forget("import_{$this->importId}_errors");
                Cache::forget("import_{$this->importId}_validation_failures");
                Cache::forget("import_{$this->importId}_processed_count");
                Cache::forget("import_{$this->importId}_updated_count");
            },
        ];
    }

    // Helper methods
    protected function parseDate($value)
    {
        if (empty($value)) return null;
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        return $value;
    }

    protected function parseNumeric($value)
    {
        if (empty($value)) return null;
        return floatval(str_replace(',', '', $value));
    }
}
```

### Step 2: Add Controller Methods

**File:** `app/Http/Controllers/Api/YourController.php`

Add these two methods to your existing controller:

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\Request;
use App\Imports\YourModuleImport;
use Illuminate\Support\Str;

/**
 * @OA\Post(
 *     path="/uploads/your-module",
 *     summary="Upload your module data from Excel file",
 *     description="Upload an Excel file containing records. The import is processed asynchronously.",
 *     tags={"Your Module"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="file", type="string", format="binary")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=202, description="Import started successfully"),
 *     @OA\Response(response=422, description="Validation error"),
 *     @OA\Response(response=500, description="Import failed")
 * )
 */
public function upload(Request $request)
{
    try {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');
        $importId = 'your_module_' . Str::uuid();
        $userId = auth()->id();

        // Queue the import
        (new YourModuleImport($importId, $userId))->queue($file);

        return response()->json([
            'success' => true,
            'message' => 'Import started successfully. You will be notified when complete.',
            'import_id' => $importId,
        ], 202);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to start import',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * @OA\Get(
 *     path="/downloads/your-module-template",
 *     summary="Download import template",
 *     description="Downloads an Excel template for bulk import",
 *     operationId="downloadYourModuleTemplate",
 *     tags={"Your Module"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Template downloaded successfully"),
 *     @OA\Response(response=500, description="Failed to generate template")
 * )
 */
public function downloadTemplate()
{
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Your Module Import');

        // SECTION 1: HEADERS
        $headers = [
            'field1',
            'field2',
            'field3',
            // Add all your columns
        ];

        // SECTION 2: VALIDATION RULES
        $validationRules = [
            'String - NOT NULL - Description',
            'Integer - NOT NULL - Description',
            'Date (YYYY-MM-DD) - NULLABLE - Description',
            // Add all validation descriptions
        ];

        // SECTION 3: WRITE HEADERS (Row 1)
        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($col, 1);
            $cell->setValue($header);

            // Style header
            $cell->getStyle()->getFont()->setBold(true)->setSize(11);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
            $cell->getStyle()->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $col++;
        }

        // SECTION 4: WRITE VALIDATION RULES (Row 2)
        $col = 1;
        foreach ($validationRules as $rule) {
            $cell = $sheet->getCellByColumnAndRow($col, 2);
            $cell->setValue($rule);

            // Style validation row
            $cell->getStyle()->getFont()->setItalic(true)->setSize(9);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E7E6E6');
            $cell->getStyle()->getAlignment()
                ->setWrapText(true)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

            $col++;
        }

        $sheet->getRowDimension(2)->setRowHeight(60);

        // SECTION 5: SAMPLE DATA (Rows 3-5)
        $sampleData = [
            ['value1', 'value2', 'value3'],
            ['value1', 'value2', 'value3'],
            ['value1', 'value2', 'value3'],
        ];

        $row = 3;
        foreach ($sampleData as $data) {
            $col = 1;
            foreach ($data as $value) {
                $sheet->getCellByColumnAndRow($col, $row)->setValue($value);
                $col++;
            }
            $row++;
        }

        // SECTION 6: SET COLUMN WIDTHS
        $columnWidths = [
            'A' => 20,
            'B' => 20,
            'C' => 20,
            // Set width for each column
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Generate and download
        $filename = 'your_module_import_template_' . date('Y-m-d_His') . '.xlsx';
        
        $writer = new Xlsx($spreadsheet);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate template',
            'error' => $e->getMessage(),
        ], 500);
    }
}
```

### Step 3: Register Routes

**File:** `routes/api/uploads.php`

```php
use App\Http\Controllers\Api\YourController;

// Inside Route::middleware('auth:sanctum')->group(function () {

    // UPLOADS
    Route::prefix('uploads')->group(function () {
        Route::post('/your-module', [YourController::class, 'upload'])
            ->name('uploads.your-module')
            ->middleware('permission:your_module.edit');
    });

    // DOWNLOADS
    Route::prefix('downloads')->group(function () {
        Route::get('/your-module-template', [YourController::class, 'downloadTemplate'])
            ->name('downloads.your-module-template')
            ->middleware('permission:your_module.read');
    });

// });
```

---

## Frontend Implementation

### Step 1: Update API Config

**File:** `src/config/api.config.js`

```javascript
// Find the UPLOAD section and add:
UPLOAD: {
    // ... existing uploads ...
    YOUR_MODULE: '/uploads/your-module',
    YOUR_MODULE_TEMPLATE: '/downloads/your-module-template',
},
```

### Step 2: Create Upload Service

**File:** `src/services/upload-your-module.service.js`

```javascript
import { apiService } from './api.service';
import { API_ENDPOINTS } from '../config/api.config';

class UploadYourModuleService {
    /**
     * Upload Excel file
     * @param {File} file - Excel file
     * @param {Function} onProgress - Progress callback
     * @returns {Promise<Object>} API response
     */
    async uploadYourModuleData(file, onProgress = null) {
        const formData = new FormData();
        formData.append('file', file);

        const config = {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        };

        if (onProgress) {
            config.onUploadProgress = (progressEvent) => {
                const percentCompleted = Math.round(
                    (progressEvent.loaded * 100) / progressEvent.total
                );
                onProgress(percentCompleted);
            };
        }

        try {
            const response = await apiService.post(
                API_ENDPOINTS.UPLOAD.YOUR_MODULE,
                formData,
                config
            );
            return response;
        } catch (error) {
            console.error('Error uploading data:', error);
            throw error;
        }
    }

    /**
     * Download import template
     * @returns {Promise<void>}
     */
    async downloadTemplate() {
        try {
            const blob = await apiService.get(
                API_ENDPOINTS.UPLOAD.YOUR_MODULE_TEMPLATE,
                { responseType: 'blob' }
            );

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            
            const timestamp = new Date().toISOString()
                .slice(0, 19).replace(/:/g, '-');
            link.setAttribute(
                'download', 
                `your_module_import_template_${timestamp}.xlsx`
            );
            
            document.body.appendChild(link);
            link.click();
            link.parentNode.removeChild(link);
            window.URL.revokeObjectURL(url);

            return { success: true };
        } catch (error) {
            console.error('Error downloading template:', error);
            throw error;
        }
    }

    /**
     * Validate file before upload
     * @param {File} file - File to validate
     * @returns {Object} Validation result
     */
    validateFile(file) {
        const errors = [];

        const validTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];
        const validExtensions = ['.xlsx', '.xls'];
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();

        if (!validTypes.includes(file.type) && 
            !validExtensions.includes(fileExtension)) {
            errors.push('Invalid file type. Please upload an Excel file (.xlsx or .xls)');
        }

        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            errors.push('File size exceeds 10MB limit');
        }

        return {
            isValid: errors.length === 0,
            errors
        };
    }
}

export const uploadYourModuleService = new UploadYourModuleService();
```

### Step 3: Create Upload Component

**File:** `src/components/uploads/your-module-upload.vue`

```vue
<template>
    <UploadRow 
        ref="uploadRow"
        :upload="upload" 
        :uploading="uploading"
        :upload-progress="uploadProgress"
        @upload="handleUpload"
        @file-selected="onFileSelected"
        @file-cleared="onFileCleared"
        @download-template="downloadTemplate"
    />
</template>

<script>
import UploadRow from '@/components/uploads/upload-row.vue';
import { message } from 'ant-design-vue';
import { uploadYourModuleService } from '@/services/upload-your-module.service';

export default {
    name: 'YourModuleUpload',
    components: {
        UploadRow
    },
    props: {
        canEdit: {
            type: Boolean,
            default: false
        }
    },
    emits: ['upload-complete'],
    data() {
        return {
            upload: {
                id: 99, // Use unique ID
                name: "Your Module Data Import",
                description: "Upload Excel file with your module data (bulk import)",
                icon: "your-icon", // Tabler icon name
                templateUrl: true
            },
            uploading: false,
            uploadProgress: 0,
            selectedFile: null
        };
    },
    methods: {
        onFileSelected(file) {
            this.selectedFile = file;
        },
        onFileCleared() {
            this.selectedFile = null;
            this.uploadProgress = 0;
        },
        async downloadTemplate() {
            try {
                message.loading({ content: 'Downloading template...', key: 'template' });
                await uploadYourModuleService.downloadTemplate();
                message.success({ content: 'Template downloaded!', key: 'template' });
            } catch (error) {
                console.error('Error downloading template:', error);
                message.error({ content: 'Failed to download template.', key: 'template' });
            }
        },
        async handleUpload(file) {
            if (!file) {
                message.error('Please select a file to upload');
                return;
            }

            // Validate file
            const validation = uploadYourModuleService.validateFile(file);
            if (!validation.isValid) {
                validation.errors.forEach(error => message.error(error));
                return;
            }

            this.uploading = true;
            this.uploadProgress = 0;

            try {
                message.loading({ content: 'Uploading data...', key: 'upload' });

                const response = await uploadYourModuleService.uploadYourModuleData(
                    file, 
                    (progress) => {
                        this.uploadProgress = progress;
                    }
                );

                this.uploadProgress = 100;

                message.success({ 
                    content: response.data?.message || 'Upload successful!', 
                    key: 'upload',
                    duration: 5
                });

                this.$emit('upload-complete');
                
                // Clear file after successful upload
                if (this.$refs.uploadRow) {
                    this.$refs.uploadRow.clearFile();
                }

            } catch (error) {
                console.error('Upload error:', error);
                const errorMsg = error.response?.data?.message || 
                               error.response?.data?.error || 
                               'Upload failed. Please try again.';
                message.error({ content: errorMsg, key: 'upload', duration: 5 });
            } finally {
                this.uploading = false;
                setTimeout(() => {
                    this.uploadProgress = 0;
                }, 1000);
            }
        }
    }
};
</script>
```

### Step 4: Add to Upload List Page

**File:** `src/views/pages/administration/file-uploads/file-uploads-list.vue`

```vue
<script>
// 1. Import component
import YourModuleUpload from '@/components/uploads/your-module-upload.vue';

export default {
    components: {
        // ... existing components ...
        YourModuleUpload
    },
    // ... rest of component ...
};
</script>

<template>
  <!-- Add new section in the appropriate position -->
  
  <!-- Your Module Uploads Section -->
  <div class="upload-category">
    <div class="category-header">
      <h6 class="mb-0">
        <i class="ti ti-your-icon"></i> Your Module Data
      </h6>
    </div>
    <div class="table-responsive">
      <table class="table custom-table mb-0">
        <thead>
          <tr>
            <th>Upload Type</th>
            <th>Select File</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <YourModuleUpload 
            :can-edit="canEdit" 
            @upload-complete="onUploadComplete" 
          />
        </tbody>
      </table>
    </div>
  </div>

</template>

<style scoped>
/* Add color for the new section (use nth-child based on position) */
.upload-category:nth-child(X) .category-header {
  border-left-color: #YOUR_COLOR; /* e.g., #ffc107 */
}

.upload-category:nth-child(X) .category-header i {
  color: #YOUR_COLOR;
}
</style>
```

---

## Permission Setup

### Step 1: Add Module to ModuleSeeder

**File:** `database/seeders/ModuleSeeder.php`

```php
// Add to the $modules array in the appropriate category
[
    'name' => 'your_module',
    'display_name' => 'Your Module',
    'description' => 'Manage your module data',
    'icon' => 'your-icon',
    'category' => 'YourCategory', // e.g., 'Employee', 'Payroll', etc.
    'route' => '/your/module/route',
    'active_link' => '/your/module/route',
    'read_permission' => 'your_module.read',
    'edit_permissions' => ['your_module.edit'],
    'order' => XX, // Choose appropriate order number
],

// Update category count
// Find the category info message and increment the count
```

### Step 2: Run Seeders

```bash
# 1. Seed modules (creates permission definitions)
php artisan db:seed --class=ModuleSeeder

# 2. Seed permissions (creates permission records)
php artisan db:seed --class=PermissionRoleSeeder

# 3. Seed users (assigns permissions to admin/HR manager)
php artisan db:seed --class=UserSeeder

# 4. Clear cache
php artisan permission:cache-reset
php artisan cache:clear
```

### Step 3: Verify Permissions

Create a temporary verification script:

```php
<?php
// verify_permissions.php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$admin = \App\Models\User::where('email', 'admin@hrms.com')->first();

echo "Checking permissions for: {$admin->email}\n";
echo "Has your_module.read: " . 
    ($admin->hasPermissionTo('your_module.read') ? '✓ YES' : '✗ NO') . "\n";
echo "Has your_module.edit: " . 
    ($admin->hasPermissionTo('your_module.edit') ? '✓ YES' : '✗ NO') . "\n";
```

Run: `php verify_permissions.php`

---

## Testing Checklist

### Backend Testing

- [ ] Migration runs successfully
- [ ] Model has correct fillable fields
- [ ] Import class exists and follows naming convention
- [ ] Controller methods exist (upload, downloadTemplate)
- [ ] Routes are registered
- [ ] Permissions are defined in ModuleSeeder
- [ ] Permissions are created in database
- [ ] Admin user has both read and edit permissions
- [ ] Template download works (GET request)
- [ ] Template has correct columns and validation rules

### Frontend Testing

- [ ] Service file created and exported
- [ ] Upload component created
- [ ] Component integrated in file-uploads-list page
- [ ] API endpoints added to api.config.js
- [ ] Download template button works
- [ ] Downloaded template has correct structure
- [ ] File selection works
- [ ] File validation works (type, size)
- [ ] Upload progress shows correctly
- [ ] Upload success message displays
- [ ] Upload error messages display
- [ ] File clears after successful upload
- [ ] Permission check works (canEdit prop)

### Import Testing

- [ ] Upload with minimal required fields
- [ ] Upload with all fields populated
- [ ] Upload with invalid data shows errors
- [ ] Upload creates new records
- [ ] Upload updates existing records (if applicable)
- [ ] Import notification received
- [ ] Import handles large files (1000+ rows)
- [ ] Import handles duplicate detection correctly
- [ ] Import logs errors properly
- [ ] Import completion stats are accurate

---

## Common Patterns

### Pattern 1: Lookup Foreign Keys

```php
// In constructor
$this->lookupData['employees'] = Employee::pluck('id', 'staff_id')->toArray();

// In collection method
$employeeId = $this->lookupData['employees'][$staffId] ?? null;
if (!$employeeId) {
    $errors[] = "Row {$index}: Employee '{$staffId}' not found";
    continue;
}
```

### Pattern 2: Date Normalization

```php
protected function parseDate($value)
{
    if (empty($value)) return null;
    
    // Excel serial date
    if (is_numeric($value)) {
        try {
            return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
    
    // String date
    return $value;
}
```

### Pattern 3: Numeric Normalization

```php
protected function parseNumeric($value)
{
    if (empty($value)) return null;
    
    // Remove commas and convert to float
    return floatval(str_replace(',', '', $value));
}
```

### Pattern 4: Duplicate Detection

```php
// Simple unique field
$existing = YourModel::where('unique_field', $value)->first();

// Multiple fields
$existing = YourModel::where([
    'field1' => $value1,
    'field2' => $value2,
])->first();

if ($existing) {
    // Update
    $updates[$existing->id] = $data;
} else {
    // Create
    $batch[] = $data;
}
```

### Pattern 5: Auto-Calculation

```php
// Auto-calculate if not provided
$calculatedValue = null;
if (!empty($row['explicit_value'])) {
    $calculatedValue = $this->parseNumeric($row['explicit_value']);
} else {
    // Calculate from other fields
    $calculatedValue = $someValue * $someMultiplier;
}
```

---

## Troubleshooting

### Issue: Permission Denied Error

**Symptoms:** 403 Forbidden when downloading template or uploading

**Solutions:**
1. Check permission exists: `SELECT * FROM permissions WHERE name = 'your_module.read';`
2. Check user has permission: Run verification script
3. Re-run seeders: `php artisan db:seed --class=UserSeeder`
4. Clear cache: `php artisan permission:cache-reset`

### Issue: Template Download Not Working

**Symptoms:** Template download fails or returns JSON error

**Solutions:**
1. Check route is registered: `php artisan route:list --path=your-module`
2. Check controller method exists
3. Check permission middleware on route
4. Check API endpoint in api.config.js

### Issue: Upload File Not Found

**Symptoms:** "file field is required" error

**Solutions:**
1. Check form data: `formData.append('file', file);`
2. Check content type: `'Content-Type': 'multipart/form-data'`
3. Check file input ref in component

### Issue: Import Not Processing

**Symptoms:** Upload succeeds but no data imported

**Solutions:**
1. Check queue is running: `php artisan queue:work`
2. Check import class implements `ShouldQueue`
3. Check logs: `storage/logs/laravel.log`
4. Check cache for import errors

### Issue: Duplicate Records Created

**Symptoms:** Multiple records created instead of updating

**Solutions:**
1. Check duplicate detection logic in import
2. Check unique fields match correctly
3. Add database indexes for performance

---

## File Checklist

Use this checklist to ensure all files are created:

### Backend Files
- [ ] `app/Imports/YourModuleImport.php`
- [ ] `app/Http/Controllers/Api/YourController.php` (methods added)
- [ ] `routes/api/uploads.php` (routes added)
- [ ] `database/seeders/ModuleSeeder.php` (module added)
- [ ] `docs/uploads/YOUR_MODULE_DOCUMENTATION.md` (optional)

### Frontend Files
- [ ] `src/services/upload-your-module.service.js`
- [ ] `src/components/uploads/your-module-upload.vue`
- [ ] `src/config/api.config.js` (endpoints added)
- [ ] `src/views/pages/administration/file-uploads/file-uploads-list.vue` (section added)

### Commands Run
- [ ] `php artisan db:seed --class=ModuleSeeder`
- [ ] `php artisan db:seed --class=PermissionRoleSeeder`
- [ ] `php artisan db:seed --class=UserSeeder`
- [ ] `php artisan permission:cache-reset`
- [ ] `php artisan cache:clear`
- [ ] `vendor/bin/pint` (code formatting)

---

## Quick Reference

### Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Import Class | `{Module}Import` | `EmployeeSalariesImport` |
| Controller | `{Module}Controller` | `EmployeeSalaryController` |
| Model | `{Singular}` | `EmployeeSalary` |
| Table | `{plural_snake}` | `employee_salaries` |
| Service | `upload-{module}.service.js` | `upload-salary.service.js` |
| Component | `{module}-upload.vue` | `salary-upload.vue` |
| Route (upload) | `uploads.{module}` | `uploads.employee-salary` |
| Route (template) | `downloads.{module}-template` | `downloads.employee-salary-template` |
| Permission | `{module}.{action}` | `employee_salaries.read` |

### Common Icons (Tabler Icons)

- `users` - Employees
- `briefcase` - Employment
- `wallet` - Salary/Payment
- `calendar` - Leave/Attendance
- `award` - Grants
- `chart-pie` - Allocations
- `file-upload` - Uploads
- `building` - Organization
- `settings` - Settings

---

## Summary

To create a new upload menu, provide:

1. ✅ Module name, display name, category, icon
2. ✅ Table name, model name
3. ✅ All template columns with types, validation, samples
4. ✅ Duplicate detection strategy
5. ✅ Auto-calculation logic (if any)
6. ✅ Upload position in UI
7. ✅ Color theme for section

With this information, you can systematically create all necessary backend and frontend files following this guide!

---

**Last Updated:** January 8, 2026  
**Version:** 1.0  
**Maintained By:** HRMS Development Team

