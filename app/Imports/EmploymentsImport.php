<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\Position;
use App\Models\SectionDepartment;
use App\Models\Site;
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
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Validators\Failure;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmploymentsImport extends DefaultValueBinder implements ShouldQueue, SkipsEmptyRows, SkipsOnFailure, ToCollection, WithChunkReading, WithCustomValueBinder, WithEvents, WithHeadingRow
{
    use Importable, RegistersEventListeners;

    public $userId;

    public $importId;

    protected $existingStaffIds = [];

    protected $existingEmployments = [];

    protected $siteLookup = [];

    protected $departmentLookup = [];

    protected $sectionDepartmentLookup = [];

    protected $positionLookup = [];

    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;

        // Prefetch staff_ids from DB
        $this->existingStaffIds = Employee::pluck('id', 'staff_id')->toArray();

        // Prefetch existing employments (staff_id -> employment_id)
        $this->existingEmployments = Employment::join('employees', 'employments.employee_id', '=', 'employees.id')
            ->where('employments.status', true)
            ->pluck('employments.id', 'employees.staff_id')
            ->toArray();

        // Prefetch site lookup (code -> id)
        $this->siteLookup = Site::pluck('id', 'code')->toArray();

        // Prefetch department lookup (name -> id)
        $this->departmentLookup = Department::pluck('id', 'name')->toArray();

        // Prefetch section department lookup (name -> id)
        $this->sectionDepartmentLookup = SectionDepartment::pluck('id', 'name')->toArray();

        // Prefetch position lookup (title -> id)
        $this->positionLookup = Position::pluck('id', 'title')->toArray();

        // Initialize cache keys for this import
        Cache::put("import_{$this->importId}_errors", [], 3600);
        Cache::put("import_{$this->importId}_validation_failures", [], 3600);
        Cache::put("import_{$this->importId}_processed_staff_ids", [], 3600);
        Cache::put("import_{$this->importId}_seen_staff_ids", [], 3600);
        Cache::put("import_{$this->importId}_processed_count", 0, 3600);
        Cache::put("import_{$this->importId}_updated_count", 0, 3600);
        Cache::put("import_{$this->importId}_skipped_count", 0, 3600);
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

        // Normalize data
        $normalized = $rows->map(function ($r) {
            // Normalize date fields
            foreach (['start_date', 'pass_probation_date', 'end_date'] as $dateField) {
                if (! empty($r[$dateField]) && is_numeric($r[$dateField])) {
                    try {
                        $r[$dateField] = ExcelDate::excelToDateTimeObject($r[$dateField])->format('Y-m-d');
                    } catch (\Exception $e) {
                        Log::warning("Failed to parse {$dateField}", ['value' => $r[$dateField], 'error' => $e->getMessage()]);
                    }
                }
            }

            return $r;
        });

        Log::debug('Rows after normalization', ['normalized_count' => $normalized->count(), 'import_id' => $this->importId]);

        // Validate per-row fields
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

        // Capture first row debug
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
            Log::info('Starting employment import process', ['rows_count' => $rows->count()]);

            DB::transaction(function () use ($normalized) {
                $employmentBatch = [];
                $employmentUpdates = [];
                $allStaffIds = [];

                $seenStaffIds = Cache::get("import_{$this->importId}_seen_staff_ids", []);
                $errors = Cache::get("import_{$this->importId}_errors", []);

                foreach ($normalized as $index => $row) {
                    if (! $row->filter()->count()) {
                        Log::debug('Skipping empty row', ['row_index' => $index, 'import_id' => $this->importId]);

                        continue;
                    }

                    $staffId = trim($row['staff_id'] ?? '');
                    if (! $staffId) {
                        $errors[] = "Row {$index}: Missing staff ID";

                        continue;
                    }

                    // Check if employee exists
                    if (! isset($this->existingStaffIds[$staffId])) {
                        $errors[] = "Row {$index}: Employee with staff_id '{$staffId}' not found in database";

                        continue;
                    }

                    $employeeId = $this->existingStaffIds[$staffId];

                    // Check duplicates in import file
                    if (in_array($staffId, $seenStaffIds)) {
                        $this->onFailure(new Failure($index + 1, 'staff_id', ['Duplicate staff_id in import file'], $row->toArray()));

                        continue;
                    }
                    $seenStaffIds[] = $staffId;
                    $allStaffIds[] = $staffId;

                    // Parse employment type
                    $employmentType = trim($row['employment_type'] ?? 'Full-time');
                    if (! in_array($employmentType, ['Full-time', 'Part-time', 'Contract', 'Temporary'])) {
                        $errors[] = "Row {$index}: Invalid employment type '{$employmentType}'";

                        continue;
                    }

                    // Parse dates
                    $startDate = $this->parseDate($row['start_date'] ?? null);
                    $passProbDate = $this->parseDate($row['pass_probation_date'] ?? null);
                    $endDate = $this->parseDate($row['end_date'] ?? null);

                    if (! $startDate) {
                        $errors[] = "Row {$index}: Missing start date";

                        continue;
                    }

                    // Parse salary
                    $salary = $this->parseNumeric($row['pass_probation_salary'] ?? null);
                    if (! $salary) {
                        $errors[] = "Row {$index}: Missing or invalid pass_probation_salary";

                        continue;
                    }

                    // Parse probation salary
                    $probationSalary = $this->parseNumeric($row['probation_salary'] ?? null);

                    // Parse site by code
                    $siteCode = trim($row['site_code'] ?? '');
                    $siteId = $this->siteLookup[$siteCode] ?? null;

                    // Parse department by name
                    $departmentName = trim($row['department'] ?? '');
                    $departmentId = $this->departmentLookup[$departmentName] ?? null;

                    // Parse section department by name
                    $sectionDepartmentName = trim($row['section_department'] ?? '');
                    $sectionDepartmentId = $this->sectionDepartmentLookup[$sectionDepartmentName] ?? null;

                    // Parse position by title
                    $positionTitle = trim($row['position'] ?? '');
                    $positionId = $this->positionLookup[$positionTitle] ?? null;

                    // Parse pay method
                    $payMethod = $this->mapPayMethod($row['pay_method'] ?? '');

                    // Parse benefits (percentages are managed globally in benefit_settings table)
                    $healthWelfare = $this->parseBoolean($row['health_welfare'] ?? '0');
                    $isPVD = $this->parseBoolean($row['pvd'] ?? '0');
                    $isSavingFund = $this->parseBoolean($row['saving_fund'] ?? '0');

                    // Parse status
                    $status = $this->parseBoolean($row['status'] ?? '1');

                    // Prepare employment data
                    $employmentData = [
                        'employee_id' => $employeeId,
                        'employment_type' => $employmentType,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'pass_probation_date' => $passProbDate,
                        'pay_method' => $payMethod,
                        'site_id' => $siteId,
                        'department_id' => $departmentId,
                        'section_department_id' => $sectionDepartmentId,
                        'position_id' => $positionId,
                        'pass_probation_salary' => $salary,
                        'probation_salary' => $probationSalary,
                        'health_welfare' => $healthWelfare,
                        'pvd' => $isPVD,
                        'saving_fund' => $isSavingFund,
                        'status' => $status,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Check if employment already exists for this employee
                    if (isset($this->existingEmployments[$staffId])) {
                        // Update existing employment
                        $existingEmploymentId = $this->existingEmployments[$staffId];
                        $employmentUpdates[$existingEmploymentId] = $employmentData;
                    } else {
                        // Create new employment
                        $employmentBatch[] = $employmentData;
                    }
                }

                // Update cache
                Cache::put("import_{$this->importId}_seen_staff_ids", $seenStaffIds, 3600);
                Cache::put("import_{$this->importId}_errors", $errors, 3600);

                // Insert new employments
                if (count($employmentBatch)) {
                    Employment::insert($employmentBatch);

                    $currentCount = Cache::get("import_{$this->importId}_processed_count", 0);
                    Cache::put("import_{$this->importId}_processed_count", $currentCount + count($employmentBatch), 3600);

                    $processedStaffIds = Cache::get("import_{$this->importId}_processed_staff_ids", []);
                    $processedStaffIds = array_merge($processedStaffIds, $allStaffIds);
                    Cache::put("import_{$this->importId}_processed_staff_ids", $processedStaffIds, 3600);

                    Log::info('Inserted employment batch', ['count' => count($employmentBatch), 'import_id' => $this->importId]);
                }

                // Update existing employments
                if (count($employmentUpdates)) {
                    foreach ($employmentUpdates as $employmentId => $data) {
                        unset($data['created_at']); // Don't update created_at
                        Employment::where('id', $employmentId)->update($data);
                    }

                    $currentUpdatedCount = Cache::get("import_{$this->importId}_updated_count", 0);
                    Cache::put("import_{$this->importId}_updated_count", $currentUpdatedCount + count($employmentUpdates), 3600);

                    Log::info('Updated employment batch', ['count' => count($employmentUpdates), 'import_id' => $this->importId]);
                }
            });
        } catch (\Exception $e) {
            $errorMessage = 'Error in '.__METHOD__.' at line '.$e->getLine().': '.$e->getMessage();
            $errors = Cache::get("import_{$this->importId}_errors", []);
            $errors[] = $errorMessage;
            Cache::put("import_{$this->importId}_errors", $errors, 3600);

            Log::error('Employment import failed', [
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

    public function rules(): array
    {
        return [
            '*.staff_id' => 'required|string',
            '*.employment_type' => 'required|string|in:Full-time,Part-time,Contract,Temporary',
            '*.start_date' => 'required|date',
            '*.pass_probation_salary' => 'required|numeric',
            '*.pass_probation_date' => 'nullable|date',
            '*.probation_salary' => 'nullable|numeric',
            '*.end_date' => 'nullable|date',
            '*.pay_method' => 'nullable|string',
            '*.site_code' => 'nullable|string',
            '*.department' => 'nullable|string',
            '*.section_department' => 'nullable|string',
            '*.position' => 'nullable|string',
            '*.health_welfare' => 'nullable|in:0,1',
            '*.pvd' => 'nullable|in:0,1',
            '*.saving_fund' => 'nullable|in:0,1',
            '*.status' => 'nullable|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function chunkSize(): int
    {
        return 40;
    }

    public function getProcessedEmployments(): array
    {
        return [];
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

    public function getProcessedStaffIds(): array
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
                Log::info("After import: userId is {$this->userId}");

                $errors = Cache::get("import_{$this->importId}_errors", []);
                $processedCount = Cache::get("import_{$this->importId}_processed_count", 0);
                $updatedCount = Cache::get("import_{$this->importId}_updated_count", 0);
                $validationFailures = Cache::get("import_{$this->importId}_validation_failures", []);

                $message = "Employment import finished! Created: {$processedCount}, Updated: {$updatedCount}";
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

                // Clean up cache
                Cache::forget("import_{$this->importId}_errors");
                Cache::forget("import_{$this->importId}_validation_failures");
                Cache::forget("import_{$this->importId}_processed_staff_ids");
                Cache::forget("import_{$this->importId}_seen_staff_ids");
                Cache::forget("import_{$this->importId}_processed_count");
                Cache::forget("import_{$this->importId}_updated_count");
                Cache::forget("import_{$this->importId}_skipped_count");
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

        Log::error($errorMessage, [
            'exception' => $errorDetails,
            'trace' => $trace,
        ]);

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new \App\Notifications\ImportFailedNotification($errorMessage, $errorDetails, $this->importId));
        }
    }

    /**
     * Parse date field
     */
    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', ['value' => $value, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Parse numeric value
     */
    private function parseNumeric($value): ?float
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        return floatval(preg_replace('/[^0-9.-]/', '', $value));
    }

    /**
     * Parse boolean value
     */
    private function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Map pay method text
     */
    private function mapPayMethod(string $payMethodText): ?string
    {
        $payMethodLower = strtolower(trim($payMethodText));

        if (stripos($payMethodLower, 'bank') !== false || stripos($payMethodLower, 'transfer') !== false) {
            return 'Bank Transfer';
        }

        if (stripos($payMethodLower, 'cash') !== false) {
            return 'Cash';
        }

        if (stripos($payMethodLower, 'cheque') !== false || stripos($payMethodLower, 'check') !== false) {
            return 'Cheque';
        }

        return $payMethodText ?: null;
    }
}
