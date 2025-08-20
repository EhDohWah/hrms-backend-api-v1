<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\ImportedCompletedNotification;
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
use Maatwebsite\Excel\Validators\Failure;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeesImport extends DefaultValueBinder implements ShouldQueue, SkipsEmptyRows, SkipsOnFailure, ToCollection, WithChunkReading, WithCustomValueBinder, WithEvents, WithHeadingRow
{
    use Importable, RegistersEventListeners;

    public $userId;

    public $importId;

    protected $existingStaffIds = [];

    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;
        // Prefetch staff_ids from DB only once at start (for duplicate checking)
        $this->existingStaffIds = Employee::pluck('staff_id')->map('strval')->toArray();

        // Initialize cache keys for this import
        Cache::put("import_{$this->importId}_errors", [], 3600);  // 1 hour TTL
        Cache::put("import_{$this->importId}_validation_failures", [], 3600);
        Cache::put("import_{$this->importId}_processed_staff_ids", [], 3600);
        Cache::put("import_{$this->importId}_seen_staff_ids", [], 3600);
        Cache::put("import_{$this->importId}_processed_count", 0, 3600);
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
                .implode(', ', $failure->errors());
            Log::warning($msg, ['values' => $failure->values()]);
            $errors[] = $msg;
        }

        Cache::put("import_{$this->importId}_errors", $errors, 3600);
        Cache::put("import_{$this->importId}_validation_failures", $validationFailures, 3600);
    }

    public function collection(Collection $rows)
    {
        Log::info('Import chunk started', ['rows_in_chunk' => $rows->count(), 'import_id' => $this->importId]);

        $normalized = $rows->map(function ($r) {
            if (! empty($r['date_of_birth']) && is_numeric($r['date_of_birth'])) {
                try {
                    $r['date_of_birth'] =
                        ExcelDate::excelToDateTimeObject($r['date_of_birth'])
                            ->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            // Map id_type text
            $map = [
                '10 years ID' => '10YearsID',
                'Burmese ID' => 'BurmeseID',
                'CI' => 'CI',
                'Borderpass' => 'Borderpass',
                'Thai ID' => 'ThaiID',
                'Passport' => 'Passport',
                'Other' => 'Other',
            ];
            if (! empty($r['id_type'])) {
                $r['id_type'] = $map[$r['id_type']] ?? 'Other';
            }

            unset($r['age']);  // drop formula

            return $r;
        });

        Log::debug('Rows after normalization', ['normalized_count' => $normalized->count(), 'import_id' => $this->importId]);

        // Validate per-row fields except staff_id uniqueness
        $validator = Validator::make(
            $normalized->toArray(),
            $this->rules(),
            $this->messages()
        );
        if ($validator->fails()) {
            Log::error('Validation failed for chunk', ['errors' => $validator->errors()->all(), 'import_id' => $this->importId]);
            foreach ($validator->errors()->all() as $error) {
                $this->onFailure(new Failure(0, '', [$error], []));
            }

            return;
        }

        // Capture first row debug (only once)
        if (! Cache::has("import_{$this->importId}_first_row_snapshot") && $rows->count() > 0) {
            $first = $rows->first()->toArray();
            $firstRowSnapshot = [
                'columns' => array_keys($first),
                'values' => $first,
            ];
            Cache::put("import_{$this->importId}_first_row_snapshot", $firstRowSnapshot, 3600);
            Log::debug('First row snapshot for import debug', $firstRowSnapshot);
        }

        DB::disableQueryLog();

        try {
            Log::info('Starting employee import process', ['rows_count' => $rows->count()]);

            DB::transaction(function () use ($normalized) {
                $employeeBatch = [];
                $identBatch = [];
                $beneBatch = [];
                $allStaffIds = [];

                // Get current seen staff IDs from cache
                $seenStaffIds = Cache::get("import_{$this->importId}_seen_staff_ids", []);
                $errors = Cache::get("import_{$this->importId}_errors", []);

                foreach ($normalized as $index => $row) {
                    if (! $row->filter()->count()) {
                        Log::debug('Skipping empty row', ['row_index' => $index, 'import_id' => $this->importId]);

                        continue;
                    }

                    $staffId = trim($row['staff_id'] ?? '');
                    if (! $staffId) {
                        $errors[] = "Row {$index}: Missing staff_id";

                        continue;
                    }

                    // Check duplicates in same import file
                    if (in_array($staffId, $seenStaffIds)) {
                        $this->onFailure(new Failure($index + 1, 'staff_id', ['Duplicate staff_id in import file'], $row->toArray()));

                        continue;
                    }
                    $seenStaffIds[] = $staffId;

                    // Check existing in DB
                    if (in_array($staffId, $this->existingStaffIds)) {
                        $this->onFailure(new Failure($index + 1, 'staff_id', ['Staff_id already exists in database'], $row->toArray()));

                        continue;
                    }

                    $allStaffIds[] = $staffId;

                    // Parse date
                    $dateOfBirth = null;
                    try {
                        if (isset($row['date_of_birth']) && ! empty($row['date_of_birth'])) {
                            $dateOfBirth = \Carbon\Carbon::parse($row['date_of_birth'])->format('Y-m-d');
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse date of birth', [
                            'staff_id' => $staffId,
                            'value' => $row['date_of_birth'] ?? 'null',
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $employeeBatch[] = [
                        'staff_id' => $staffId,
                        'subsidiary' => $row['org'] ?? null,
                        'initial_en' => $row['initial'] ?? null,
                        'first_name_en' => $row['first_name'] ?? null,
                        'last_name_en' => $row['last_name'] ?? null,
                        'initial_th' => $row['initial_th'] ?? null,
                        'first_name_th' => $row['first_name_th'] ?? null,
                        'last_name_th' => $row['last_name_th'] ?? null,
                        'gender' => $row['gender'] ?? null,
                        'date_of_birth' => $dateOfBirth,
                        'status' => $row['status'] ?? null,
                        'nationality' => $row['nationality'] ?? null,
                        'religion' => $row['religion'] ?? null,
                        'social_security_number' => $row['social_security_no'] ?? null,
                        'tax_number' => $row['tax_no'] ?? null,
                        'driver_license_number' => $row['driver_license'] ?? null,
                        'bank_name' => $row['bank_name'] ?? null,
                        'bank_branch' => $row['bank_branch'] ?? null,
                        'bank_account_name' => $row['bank_acc_name'] ?? null,
                        'bank_account_number' => $row['bank_acc_no'] ?? null,
                        'mobile_phone' => $row['mobile_no'] ?? null,
                        'current_address' => $row['current_address'] ?? null,
                        'permanent_address' => $row['permanent_address'] ?? null,
                        'marital_status' => $row['marital_status'] ?? null,
                        'spouse_name' => $row['spouse_name'] ?? null,
                        'spouse_phone_number' => $row['spouse_mobile_no'] ?? null,
                        'emergency_contact_person_name' => $row['emergency_name'] ?? null,
                        'emergency_contact_person_relationship' => $row['relationship'] ?? null,
                        'emergency_contact_person_phone' => $row['emergency_mobile_no'] ?? null,
                        'father_name' => $row['father_name'] ?? null,
                        'father_occupation' => $row['father_occupation'] ?? null,
                        'father_phone_number' => $row['father_mobile_no'] ?? null,
                        'mother_name' => $row['mother_name'] ?? null,
                        'mother_occupation' => $row['mother_occupation'] ?? null,
                        'mother_phone_number' => $row['mother_mobile_no'] ?? null,
                        'military_status' => $row['military_status'] ?? null,
                        'remark' => $row['remark'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Update cache with current chunk's seen staff IDs and errors
                Cache::put("import_{$this->importId}_seen_staff_ids", $seenStaffIds, 3600);
                Cache::put("import_{$this->importId}_errors", $errors, 3600);

                if (count($employeeBatch)) {
                    Employee::insert($employeeBatch);

                    // Update processed count in cache
                    $currentCount = Cache::get("import_{$this->importId}_processed_count", 0);
                    Cache::put("import_{$this->importId}_processed_count", $currentCount + count($employeeBatch), 3600);

                    // Store processed staff IDs in cache
                    $processedStaffIds = Cache::get("import_{$this->importId}_processed_staff_ids", []);
                    $processedStaffIds = array_merge($processedStaffIds, $allStaffIds);
                    Cache::put("import_{$this->importId}_processed_staff_ids", $processedStaffIds, 3600);

                    Log::info('Inserted employee batch', ['count' => count($employeeBatch), 'import_id' => $this->importId]);
                }

                // Fetch new IDs
                $employeeMap = Employee::whereIn('staff_id', $allStaffIds)
                    ->pluck('id', 'staff_id')
                    ->toArray();

                Log::debug('Fetched employee IDs for related data', ['count' => count($employeeMap), 'import_id' => $this->importId]);

                // Build batches for related tables
                foreach ($normalized as $index => $row) {
                    if (! isset($row['staff_id'])) {
                        continue;
                    }
                    $staffId = trim($row['staff_id']);
                    if (! isset($employeeMap[$staffId])) {
                        continue;
                    }
                    $empId = $employeeMap[$staffId];

                    if (! empty($row['id_type']) && ! empty($row['id_no'])) {
                        $identBatch[] = [
                            'employee_id' => $empId,
                            'id_type' => $row['id_type'],
                            'document_number' => $row['id_no'],
                            'issue_date' => null,
                            'expiry_date' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (! empty($row['kin1_name'])) {
                        $beneBatch[] = [
                            'employee_id' => $empId,
                            'beneficiary_name' => $row['kin1_name'],
                            'beneficiary_relationship' => $row['kin1_relationship'] ?? null,
                            'phone_number' => $row['kin1_mobile'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (! empty($row['kin2_name'])) {
                        $beneBatch[] = [
                            'employee_id' => $empId,
                            'beneficiary_name' => $row['kin2_name'],
                            'beneficiary_relationship' => $row['kin2_relationship'] ?? null,
                            'phone_number' => $row['kin2_mobile'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (count($identBatch)) {
                    DB::table('employee_identifications')->insert($identBatch);
                    Log::info('Inserted employee_identifications batch', ['count' => count($identBatch), 'import_id' => $this->importId]);
                }
                if (count($beneBatch)) {
                    DB::table('employee_beneficiaries')->insert($beneBatch);
                    Log::info('Inserted employee_beneficiaries batch', ['count' => count($beneBatch), 'import_id' => $this->importId]);
                }
            });
        } catch (\Exception $e) {
            $errorMessage = 'Error in '.__METHOD__.' at line '.$e->getLine().': '.$e->getMessage();
            $errors = Cache::get("import_{$this->importId}_errors", []);
            $errors[] = $errorMessage;
            Cache::put("import_{$this->importId}_errors", $errors, 3600);

            Log::error('Employee import failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'import_id' => $this->importId,
            ]);
        }

        Log::info('Finished processing chunk', ['import_id' => $this->importId]);
    }

    // 🟢 Only use column-level validation (no unique:employees,staff_id here)
    public function rules(): array
    {
        return [
            '*.org' => 'nullable|string|max:10',
            '*.staff_id' => 'required|string|max:50',
            '*.initial' => 'nullable|string|max:10',
            '*.first_name' => 'required|string|max:255',
            '*.last_name' => 'nullable|string|max:255',
            '*.initial_th' => 'nullable|string|max:20',
            '*.first_name_th' => 'nullable|string|max:255',
            '*.last_name_th' => 'nullable|string|max:255',
            '*.gender' => 'required|string|in:M,F',
            '*.date_of_birth' => 'required|date',
            '*.status' => 'nullable|string|max:20',
            '*.nationality' => 'nullable|string|max:100',
            '*.religion' => 'nullable|string|max:100',
            '*.id_type' => 'nullable|string|max:100',
            '*.id_no' => 'nullable|string',
            '*.social_security_no' => 'nullable|string|max:50',
            '*.tax_no' => 'nullable|string|max:50',
            '*.driver_license' => 'nullable|string|max:100',
            '*.bank_name' => 'nullable|string|max:100',
            '*.bank_branch' => 'nullable|string|max:100',
            '*.bankacc_name' => 'nullable|string|max:100',
            '*.bankacc_no' => 'nullable|string|max:50',
            '*.mobile_no' => 'nullable|string|max:50',
            '*.current_address' => 'nullable|string',
            '*.permanent_address' => 'nullable|string',
            '*.marital_status' => 'nullable|string|max:50',
            '*.spouse_name' => 'nullable|string|max:200',
            '*.spouse_mobile_no' => 'nullable|string|max:50',
            '*.emergency_name' => 'nullable|string|max:100',
            '*.relationship' => 'nullable|string|max:100',
            '*.emergency_mobile_no' => 'nullable|string|max:50',
            '*.father_name' => 'nullable|string|max:200',
            '*.father_occupation' => 'nullable|string|max:200',
            '*.father_mobile_no' => 'nullable|string|max:50',
            '*.mother_name' => 'nullable|string|max:200',
            '*.mother_occupation' => 'nullable|string|max:200',
            '*.mother_mobile_no' => 'nullable|string|max:50',
            '*.kin1_name' => 'nullable|string|max:255',
            '*.kin1_relationship' => 'nullable|string|max:255',
            '*.kin1_mobile' => 'nullable|string|max:50',
            '*.kin2_name' => 'nullable|string|max:255',
            '*.kin2_relationship' => 'nullable|string|max:255',
            '*.kin2_mobile' => 'nullable|string|max:50',
            '*.military_status' => 'nullable|string|max:50',
            '*.remark' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [];
    }

    // MSSQL: keep it at 40
    public function chunkSize(): int
    {
        return 40;
    }

    public function getProcessedEmployees(): array
    {
        return [];  // No longer stored in memory
    }

    public function getErrors(): array
    {
        return Cache::get("import_{$this->importId}_errors", []);
    }

    public function getValidationFailures(): array
    {
        return Cache::get("import_{$this->importId}_validation_failures", []);
    }

    public function getFirstRowSnapshot(): array
    {
        return Cache::get("import_{$this->importId}_first_row_snapshot", []);
    }

    public function getProcessedEmployeeStaffIds(): array
    {
        return Cache::get("import_{$this->importId}_processed_staff_ids", []);
    }

    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {
                $this->handleImportFailed($event);
            },
            AfterImport::class => function (AfterImport $event) {
                \Log::info("After import: userId is {$this->userId}");

                // Get import results from cache
                $errors = Cache::get("import_{$this->importId}_errors", []);
                $processedCount = Cache::get("import_{$this->importId}_processed_count", 0);
                $validationFailures = Cache::get("import_{$this->importId}_validation_failures", []);

                $message = "Employee import finished! Processed: {$processedCount} employees";
                if (count($errors) > 0) {
                    $message .= ', Errors: '.count($errors);
                }
                if (count($validationFailures) > 0) {
                    $message .= ', Validation failures: '.count($validationFailures);
                }

                $user = User::find($this->userId);
                if ($user) {
                    $user->notify(new ImportedCompletedNotification($message));
                }

                // Clean up cache after notification (optional - cache will expire anyway)
                Cache::forget("import_{$this->importId}_errors");
                Cache::forget("import_{$this->importId}_validation_failures");
                Cache::forget("import_{$this->importId}_processed_staff_ids");
                Cache::forget("import_{$this->importId}_seen_staff_ids");
                Cache::forget("import_{$this->importId}_processed_count");
                Cache::forget("import_{$this->importId}_first_row_snapshot");
            },
        ];
    }

    protected function handleImportFailed(ImportFailed $event): void
    {
        $exception = $event->getException();
        $errorMessage = 'Excel import failed';
        $errorDetails = $exception->getMessage();
        $trace = $exception->getTraceAsString();

        $this->logError($errorMessage, [
            'exception' => $errorDetails,
            'trace' => $trace,
        ]);

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new ImportFailedNotification($errorMessage, $errorDetails, $this->importId));
        }
    }
}
