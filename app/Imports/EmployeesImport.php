<?php

namespace App\Imports;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\{
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    WithCustomValueBinder,
    SkipsEmptyRows,
    WithBatchInserts,
    WithUpserts
};
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Employee;
use App\Models\EmployeeIdentification;
use App\Models\EmployeeBeneficiary;
use Maatwebsite\Excel\Concerns\Importable;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Notifications\ImportedCompletedNotification;
use App\Models\User;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Events\ImportFailed;

class EmployeesImport extends DefaultValueBinder implements
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    WithCustomValueBinder,
    SkipsEmptyRows,
    SkipsOnFailure,
    ShouldQueue,
    WithEvents,
    WithBatchInserts,
    WithUpserts
{
    use Importable, RegistersEventListeners;

    public $userId;
    public $importId;
    protected $processedInCurrentChunk = 0;
    protected $chunkStartTime;
    
    // Memory thresholds
    const MEMORY_LIMIT_MB = 300; // 300MB threshold
    const MAX_ERRORS_CACHE = 500; // Limit cached errors
    
    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;
        
        // Initialize cache with memory-efficient approach
        $this->initializeImportCache();
        
        // Set up memory monitoring
        $this->chunkStartTime = microtime(true);
        
        Log::info('Import initialized', [
            'import_id' => $this->importId,
            'initial_memory' => $this->formatBytes(memory_get_usage(true)),
            'memory_limit' => ini_get('memory_limit')
        ]);
    }

    protected function initializeImportCache(): void
    {
        // Use Redis for better memory management if available
        $cacheStore = Cache::getStore();
        
        Cache::put("import_{$this->importId}_stats", [
            'processed_count' => 0,
            'error_count' => 0,
            'validation_failure_count' => 0,
            'start_time' => now(),
            'status' => 'processing'
        ], 7200); // 2 hours TTL
        
        // Initialize smaller cache entries
        Cache::put("import_{$this->importId}_errors", [], 7200);
        Cache::put("import_{$this->importId}_validation_failures", [], 7200);
    }

    public function bindValue(Cell $cell, $value)
    {
        // More memory-efficient binding
        $cell->setValueExplicit((string)$value, DataType::TYPE_STRING);
        return true;
    }

    public function model(array $row): ?Model
    {
        // Memory management at the start of each row
        if ($this->processedInCurrentChunk % 10 === 0) {
            $this->checkMemoryUsage();
        }
        
        $this->processedInCurrentChunk++;
        
        // Normalize the row data
        $normalizedRow = $this->normalizeRowData($row);
        
        // Validate the row
        if (!$this->validateRow($normalizedRow)) {
            return null;
        }
        
        // Check for duplicates
        if (!$this->checkDuplicates($normalizedRow)) {
            return null;
        }
        
        // Create the employee model
        $employee = $this->createEmployeeModel($normalizedRow);
        
        // Process related data after employee is created
        $this->processRelatedData($employee, $normalizedRow);
        
        // Update statistics
        $this->updateProcessedCount();
        
        return $employee;
    }

    protected function normalizeRowData(array $row): array
    {
        // Handle date normalization
        if (!empty($row['date_of_birth']) && is_numeric($row['date_of_birth'])) {
            try {
                $row['date_of_birth'] = ExcelDate::excelToDateTimeObject($row['date_of_birth'])
                    ->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning('Date parsing failed', [
                    'value' => $row['date_of_birth'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Map id_type text
        $idTypeMap = [
            '10 years ID' => '10YearsID',
            'Burmese ID'  => 'BurmeseID',
            'CI'          => 'CI',
            'Borderpass'  => 'Borderpass',
            'Thai ID'     => 'ThaiID',
            'Passport'    => 'Passport',
            'Other'       => 'Other',
        ];
        
        if (!empty($row['id_type'])) {
            $row['id_type'] = $idTypeMap[$row['id_type']] ?? 'Other';
        }

        // Remove unnecessary fields
        unset($row['age']);
        
        return $row;
    }

    protected function validateRow(array $row): bool
    {
        $validator = Validator::make($row, [
            'org'           => 'nullable|string|max:10',
            'staff_id'      => 'required|string|max:50',
            'first_name'    => 'required|string|max:255',
            'gender'        => 'required|string|in:M,F',
            'date_of_birth' => 'required|date',
            'id_type'       => 'nullable|string|max:50',
            // Add other validation rules as needed
        ]);

        if ($validator->fails()) {
            $this->logValidationFailure($row, $validator->errors()->toArray());
            return false;
        }

        return true;
    }

    protected function checkDuplicates(array $row): bool
    {
        $staffId = trim($row['staff_id'] ?? '');
        
        if (empty($staffId)) {
            $this->logError("Missing staff_id in row");
            return false;
        }

        // Use more efficient duplicate checking
        if ($this->isStaffIdDuplicate($staffId)) {
            $this->logValidationFailure($row, ['staff_id' => ['Staff ID already exists']]);
            return false;
        }

        return true;
    }

    protected function isStaffIdDuplicate(string $staffId): bool
    {
        // Use Redis for fast duplicate checking if available
        $cacheKey = "import_{$this->importId}_seen_staff_ids";
        
        // Check if we've seen this staff_id in current import
        if (Cache::has($cacheKey)) {
            $seenIds = Cache::get($cacheKey, []);
            if (in_array($staffId, $seenIds)) {
                return true;
            }
        }
        
        // Check database for existing staff_id
        if (Employee::where('staff_id', $staffId)->exists()) {
            return true;
        }
        
        // Add to seen list (limit size to prevent memory issues)
        $seenIds = Cache::get($cacheKey, []);
        if (count($seenIds) < 10000) { // Limit to 10k entries
            $seenIds[] = $staffId;
            Cache::put($cacheKey, $seenIds, 7200);
        }
        
        return false;
    }

    protected function createEmployeeModel(array $row): Employee
    {
        // Parse date of birth
        $dateOfBirth = null;
        try {
            if (!empty($row['date_of_birth'])) {
                $dateOfBirth = \Carbon\Carbon::parse($row['date_of_birth'])->format('Y-m-d');
            }
        } catch (\Exception $e) {
            Log::warning("Failed to parse date of birth", [
                'staff_id' => $row['staff_id'] ?? 'unknown',
                'value' => $row['date_of_birth'] ?? 'null',
                'error' => $e->getMessage()
            ]);
        }

        return new Employee([
            'staff_id'                  => trim($row['staff_id']),
            'subsidiary'                => $row['org'] ?? null,
            'initial_en'                => $row['initial'] ?? null,
            'first_name_en'             => $row['first_name'] ?? null,
            'last_name_en'              => $row['last_name'] ?? null,
            'initial_th'                => $row['initial_th'] ?? null,
            'first_name_th'             => $row['first_name_th'] ?? null,
            'last_name_th'              => $row['last_name_th'] ?? null,
            'gender'                    => $row['gender'] ?? null,
            'date_of_birth'             => $dateOfBirth,
            'status'                    => $row['status'] ?? null,
            'nationality'               => $row['nationality'] ?? null,
            'religion'                  => $row['religion'] ?? null,
            'social_security_number'    => $row['social_security_no'] ?? null,
            'tax_number'                => $row['tax_no'] ?? null,
            'driver_license_number'     => $row['driver_license'] ?? null,
            'bank_name'                 => $row['bank_name'] ?? null,
            'bank_branch'               => $row['bank_branch'] ?? null,
            'bank_account_name'         => $row['bank_acc_name'] ?? null,
            'bank_account_number'       => $row['bank_acc_no'] ?? null,
            'mobile_phone'              => $row['mobile_no'] ?? null,
            'current_address'           => $row['current_address'] ?? null,
            'permanent_address'         => $row['permanent_address'] ?? null,
            'marital_status'            => $row['marital_status'] ?? null,
            'spouse_name'               => $row['spouse_name'] ?? null,
            'spouse_phone_number'       => $row['spouse_mobile_no'] ?? null,
            'emergency_contact_person_name'         => $row['emergency_name'] ?? null,
            'emergency_contact_person_relationship' => $row['relationship'] ?? null,
            'emergency_contact_person_phone'        => $row['emergency_mobile_no'] ?? null,
            'father_name'               => $row['father_name'] ?? null,
            'father_occupation'         => $row['father_occupation'] ?? null,
            'father_phone_number'       => $row['father_mobile_no'] ?? null,
            'mother_name'               => $row['mother_name'] ?? null,
            'mother_occupation'         => $row['mother_occupation'] ?? null,
            'mother_phone_number'       => $row['mother_mobile_no'] ?? null,
            'military_status'           => $row['military_status'] ?? null,
            'remark'                    => $row['remark'] ?? null,
        ]);
    }

    protected function processRelatedData(Employee $employee, array $row): void
    {
        // Process after the employee is saved (in a separate job if needed)
        dispatch(function () use ($employee, $row) {
            $this->createEmployeeIdentification($employee, $row);
            $this->createEmployeeBeneficiaries($employee, $row);
        })->afterResponse();
    }

    protected function createEmployeeIdentification(Employee $employee, array $row): void
    {
        if (!empty($row['id_type']) && !empty($row['id_no'])) {
            EmployeeIdentification::create([
                'employee_id'     => $employee->id,
                'id_type'         => $row['id_type'],
                'document_number' => $row['id_no'],
                'issue_date'      => null,
                'expiry_date'     => null,
            ]);
        }
    }

    protected function createEmployeeBeneficiaries(Employee $employee, array $row): void
    {
        // Create beneficiary 1
        if (!empty($row['kin1_name'])) {
            EmployeeBeneficiary::create([
                'employee_id'                => $employee->id,
                'beneficiary_name'           => $row['kin1_name'],
                'beneficiary_relationship'   => $row['kin1_relationship'] ?? null,
                'phone_number'               => $row['kin1_mobile'] ?? null,
            ]);
        }

        // Create beneficiary 2
        if (!empty($row['kin2_name'])) {
            EmployeeBeneficiary::create([
                'employee_id'                => $employee->id,
                'beneficiary_name'           => $row['kin2_name'],
                'beneficiary_relationship'   => $row['kin2_relationship'] ?? null,
                'phone_number'               => $row['kin2_mobile'] ?? null,
            ]);
        }
    }

    protected function updateProcessedCount(): void
    {
        $stats = Cache::get("import_{$this->importId}_stats", []);
        $stats['processed_count'] = ($stats['processed_count'] ?? 0) + 1;
        $stats['last_processed'] = now();
        Cache::put("import_{$this->importId}_stats", $stats, 7200);
    }

    protected function logError(string $message): void
    {
        $errors = Cache::get("import_{$this->importId}_errors", []);
        
        if (count($errors) < self::MAX_ERRORS_CACHE) {
            $errors[] = $message;
            Cache::put("import_{$this->importId}_errors", $errors, 7200);
        }
        
        // Update error count in stats
        $stats = Cache::get("import_{$this->importId}_stats", []);
        $stats['error_count'] = ($stats['error_count'] ?? 0) + 1;
        Cache::put("import_{$this->importId}_stats", $stats, 7200);
        
        Log::warning($message, ['import_id' => $this->importId]);
    }

    protected function logValidationFailure(array $row, array $errors): void
    {
        $failures = Cache::get("import_{$this->importId}_validation_failures", []);
        
        if (count($failures) < self::MAX_ERRORS_CACHE) {
            $failures[] = [
                'staff_id' => $row['staff_id'] ?? 'unknown',
                'errors' => $errors,
                'timestamp' => now()->toISOString()
            ];
            Cache::put("import_{$this->importId}_validation_failures", $failures, 7200);
        }
        
        // Update validation failure count in stats
        $stats = Cache::get("import_{$this->importId}_stats", []);
        $stats['validation_failure_count'] = ($stats['validation_failure_count'] ?? 0) + 1;
        Cache::put("import_{$this->importId}_stats", $stats, 7200);
    }

    protected function checkMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryUsageMB = $memoryUsage / 1024 / 1024;
        
        if ($memoryUsageMB > self::MEMORY_LIMIT_MB) {
            Log::warning('High memory usage detected', [
                'import_id' => $this->importId,
                'memory_usage' => $this->formatBytes($memoryUsage),
                'processed_in_chunk' => $this->processedInCurrentChunk
            ]);
            
            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        // Log memory usage every 100 rows
        if ($this->processedInCurrentChunk % 100 === 0) {
            Log::info('Memory usage update', [
                'import_id' => $this->importId,
                'memory_usage' => $this->formatBytes($memoryUsage),
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                'processed_in_chunk' => $this->processedInCurrentChunk,
                'elapsed_time' => round(microtime(true) - $this->chunkStartTime, 2) . 's'
            ]);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->logValidationFailure(
                $failure->values(),
                [$failure->attribute() => $failure->errors()]
            );
        }
    }

    // Batch processing configuration
    public function batchSize(): int
    {
        return 100; // Process 100 rows at once
    }

    public function chunkSize(): int
    {
        return 50; // Smaller chunks for better memory management
    }

    // Upsert configuration for handling duplicates
    public function uniqueBy(): string
    {
        return 'staff_id';
    }

    // Getter methods for backward compatibility
    public function getErrors(): array
    {
        return Cache::get("import_{$this->importId}_errors", []);
    }
    
    public function getValidationFailures(): array
    {
        return Cache::get("import_{$this->importId}_validation_failures", []);
    }
    
    public function getImportStats(): array
    {
        return Cache::get("import_{$this->importId}_stats", []);
    }

    public function registerEvents(): array
    {
        return [
            ImportFailed::class  => function (ImportFailed $event) {
                Log::error('Excel import failed', [
                    'import_id' => $this->importId,
                    'exception' => $event->getException()->getMessage(),
                    'trace'     => $event->getException()->getTraceAsString(),
                ]);
                // Optionally notify user, update cache status, etc.
                
            },

            AfterImport::class => function (AfterImport $event) {
                $this->handleImportCompletion();
            },
        ];
    }

    protected function handleImportCompletion(): void
    {
        $stats = $this->getImportStats();
        $errors = $this->getErrors();
        $validationFailures = $this->getValidationFailures();
        
        $stats['status'] = 'completed';
        $stats['end_time'] = now();
        $stats['duration'] = $stats['end_time']->diffInSeconds($stats['start_time']);
        
        Cache::put("import_{$this->importId}_stats", $stats, 7200);
        
        $message = sprintf(
            "Employee import completed! Processed: %d employees, Errors: %d, Validation failures: %d, Duration: %d seconds",
            $stats['processed_count'] ?? 0,
            count($errors),
            count($validationFailures),
            $stats['duration'] ?? 0
        );
        
        Log::info('Import completed', [
            'import_id' => $this->importId,
            'stats' => $stats,
            'final_memory_usage' => $this->formatBytes(memory_get_peak_usage(true))
        ]);
        
        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new ImportedCompletedNotification($message));
        }
        
        // Clean up cache after a delay to allow for status checking
        dispatch(function () {
            Cache::forget("import_{$this->importId}_errors");
            Cache::forget("import_{$this->importId}_validation_failures");
            Cache::forget("import_{$this->importId}_stats");
            Cache::forget("import_{$this->importId}_seen_staff_ids");
        })->delay(now()->addHours(1));
    }
}