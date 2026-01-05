<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Employment;
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

        // Prefetch site lookup (name -> id)
        $this->siteLookup = Site::pluck('id', 'name')->toArray();

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
            foreach (['start_date_bhf', 'start_date_smru', 'pass_prob_date', 'end_of_prob_date'] as $dateField) {
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

                    $staffId = trim($row['idno'] ?? '');
                    if (! $staffId) {
                        $errors[] = "Row {$index}: Missing staff ID (ID.no.)";

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

                    // Determine employment type based on status
                    $statusText = trim($row['status'] ?? '');
                    $employmentType = $this->mapEmploymentType($statusText);

                    // Parse dates
                    $startDate = $this->parseStartDate($row);
                    $passProbDate = $this->parseDate($row['pass_prob_date'] ?? null);
                    $endDate = null; // Not in the Excel

                    if (! $startDate) {
                        $errors[] = "Row {$index}: Missing start date (Start date BHF or Start date SMRU)";

                        continue;
                    }

                    // Parse salary
                    $salary = $this->parseNumeric($row['salary_2025'] ?? null);
                    if (! $salary) {
                        $errors[] = "Row {$index}: Missing or invalid salary";

                        continue;
                    }

                    // Determine probation salary (if probation date is in future)
                    $probationSalary = null;
                    if ($passProbDate && \Carbon\Carbon::parse($passProbDate)->isFuture()) {
                        // Could be same as salary or different based on your business rules
                        $probationSalary = $salary; // Adjust if needed
                    }

                    // Parse site
                    $siteName = trim($row['site'] ?? '');
                    $siteId = $this->siteLookup[$siteName] ?? null;

                    // Parse pay method
                    $payMethod = $this->mapPayMethod($row['pay_method'] ?? '');

                    // Parse PVD/Saving Fund
                    $pvdSavingText = trim($row['pvdsaving'] ?? '');
                    $isPVD = stripos($pvdSavingText, 'pvd') !== false;
                    $isSavingFund = stripos($pvdSavingText, 'saving') !== false;

                    // Parse benefit percentages
                    $pvdPercentage = $isPVD ? 7.5 : null;
                    $savingFundPercentage = $isSavingFund ? 7.5 : null;
                    $healthWelfarePercentage = $this->parseNumeric($row['health_welfare_employer'] ?? null);

                    // Determine if health welfare is enabled
                    $healthWelfare = ! empty($healthWelfarePercentage) && $healthWelfarePercentage > 0;

                    // Prepare employment data
                    $employmentData = [
                        'employee_id' => $employeeId,
                        'employment_type' => $employmentType,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'pass_probation_date' => $passProbDate,
                        'pay_method' => $payMethod,
                        'site_id' => $siteId,
                        'pass_probation_salary' => $salary,
                        'probation_salary' => $probationSalary,
                        'health_welfare' => $healthWelfare,
                        'health_welfare_percentage' => $healthWelfarePercentage,
                        'pvd' => $isPVD,
                        'pvd_percentage' => $pvdPercentage,
                        'saving_fund' => $isSavingFund,
                        'saving_fund_percentage' => $savingFundPercentage,
                        'status' => true, // Active by default
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
            '*.idno' => 'required|string',
            '*.salary_2025' => 'required|numeric',
            '*.pay_method' => 'nullable|string',
            '*.status' => 'nullable|string',
            '*.site' => 'nullable|string',
            '*.pvdsaving' => 'nullable|string',
            '*.start_date_bhf' => 'nullable|date',
            '*.start_date_smru' => 'nullable|date',
            '*.pass_prob_date' => 'nullable|date',
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
     * Parse start date from BHF or SMRU columns
     */
    private function parseStartDate(Collection $row): ?string
    {
        $bhfDate = $this->parseDate($row['start_date_bhf'] ?? null);
        $smruDate = $this->parseDate($row['start_date_smru'] ?? null);

        return $bhfDate ?? $smruDate;
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
     * Map status text to employment type
     */
    private function mapEmploymentType(string $statusText): string
    {
        $statusLower = strtolower($statusText);

        if (stripos($statusLower, 'full') !== false || stripos($statusLower, 'local') !== false) {
            return 'Full-time';
        }

        if (stripos($statusLower, 'part') !== false) {
            return 'Part-time';
        }

        if (stripos($statusLower, 'contract') !== false) {
            return 'Contract';
        }

        if (stripos($statusLower, 'temp') !== false) {
            return 'Temporary';
        }

        return 'Full-time'; // Default
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
