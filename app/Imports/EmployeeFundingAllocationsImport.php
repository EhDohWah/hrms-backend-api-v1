<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\GrantItem;
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

class EmployeeFundingAllocationsImport extends DefaultValueBinder implements ShouldQueue, SkipsEmptyRows, SkipsOnFailure, ToCollection, WithChunkReading, WithCustomValueBinder, WithEvents, WithHeadingRow
{
    use Importable, RegistersEventListeners;

    public $userId;

    public $importId;

    protected $existingStaffIds = [];

    protected $existingEmployments = [];

    protected $grantItemLookup = [];

    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;

        // Prefetch staff_ids from DB
        $this->existingStaffIds = Employee::pluck('id', 'staff_id')->toArray();

        // Prefetch active employments (staff_id -> employment_id)
        $this->existingEmployments = Employment::join('employees', 'employments.employee_id', '=', 'employees.id')
            ->where('employments.status', true)
            ->where(function ($query) {
                $query->whereNull('employments.end_date')
                    ->orWhere('employments.end_date', '>=', now());
            })
            ->pluck('employments.id', 'employees.staff_id')
            ->toArray();

        // Prefetch grant items lookup (id -> grant_item)
        $this->grantItemLookup = GrantItem::with('grant:id,name,code')
            ->get()
            ->keyBy('id')
            ->toArray();

        // Initialize cache keys for this import
        Cache::put("import_{$this->importId}_errors", [], 3600);
        Cache::put("import_{$this->importId}_validation_failures", [], 3600);
        Cache::put("import_{$this->importId}_processed_allocations", [], 3600);
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
        Log::info('EmployeeFundingAllocation import chunk started', [
            'rows_in_chunk' => $rows->count(),
            'import_id' => $this->importId,
        ]);

        // Normalize data
        $normalized = $rows->map(function ($r) {
            // Normalize date fields
            foreach (['start_date', 'end_date'] as $dateField) {
                if (! empty($r[$dateField]) && is_numeric($r[$dateField])) {
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

        Log::debug('Rows after normalization', [
            'normalized_count' => $normalized->count(),
            'import_id' => $this->importId,
        ]);

        // Validate per-row fields
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
            Log::info('Starting employee funding allocation import process', ['rows_count' => $rows->count()]);

            DB::transaction(function () use ($normalized) {
                $allocationBatch = [];
                $allocationUpdates = [];
                $errors = Cache::get("import_{$this->importId}_errors", []);

                foreach ($normalized as $index => $row) {
                    if (! $row->filter()->count()) {
                        Log::debug('Skipping empty row', [
                            'row_index' => $index,
                            'import_id' => $this->importId,
                        ]);

                        continue;
                    }

                    $staffId = trim($row['staff_id'] ?? '');
                    if (! $staffId) {
                        $errors[] = "Row {$index}: Missing staff_id";

                        continue;
                    }

                    // Check if employee exists
                    if (! isset($this->existingStaffIds[$staffId])) {
                        $errors[] = "Row {$index}: Employee with staff_id '{$staffId}' not found in database";

                        continue;
                    }

                    $employeeId = $this->existingStaffIds[$staffId];

                    // Get employment_id (from row or auto-detect active employment)
                    $employmentId = null;
                    if (! empty($row['employment_id'])) {
                        $employmentId = (int) $row['employment_id'];
                        // Verify this employment exists and belongs to this employee
                        $employment = Employment::where('id', $employmentId)
                            ->where('employee_id', $employeeId)
                            ->first();
                        if (! $employment) {
                            $errors[] = "Row {$index}: Employment ID '{$employmentId}' not found or doesn't belong to employee '{$staffId}'";

                            continue;
                        }
                    } else {
                        // Auto-detect active employment
                        if (! isset($this->existingEmployments[$staffId])) {
                            $errors[] = "Row {$index}: No active employment found for staff_id '{$staffId}' and no employment_id provided";

                            continue;
                        }
                        $employmentId = $this->existingEmployments[$staffId];
                    }

                    // Validate grant_item_id
                    $grantItemId = (int) ($row['grant_item_id'] ?? 0);
                    if (! $grantItemId || ! isset($this->grantItemLookup[$grantItemId])) {
                        $errors[] = "Row {$index}: Invalid grant_item_id '{$grantItemId}'";

                        continue;
                    }

                    // Parse FTE (convert to decimal if percentage)
                    $fte = $this->parseNumeric($row['fte'] ?? null);
                    if ($fte === null || $fte < 0 || $fte > 100) {
                        $errors[] = "Row {$index}: Invalid FTE value (must be between 0-100)";

                        continue;
                    }
                    $fteDecimal = $fte / 100;

                    // Parse dates
                    $startDate = $this->parseDate($row['start_date'] ?? null);
                    $endDate = $this->parseDate($row['end_date'] ?? null);

                    if (! $startDate) {
                        $errors[] = "Row {$index}: Missing or invalid start_date";

                        continue;
                    }

                    // Get employment to calculate allocated_amount (if not provided)
                    $employment = Employment::find($employmentId);
                    if (! $employment) {
                        $errors[] = "Row {$index}: Employment record not found";

                        continue;
                    }

                    // Get allocation_type from row or default to 'grant'
                    $allocationType = strtolower(trim($row['allocation_type'] ?? 'grant'));
                    if (! in_array($allocationType, ['grant', 'org_funded'])) {
                        $errors[] = "Row {$index}: Invalid allocation_type '{$allocationType}' (must be: grant, org_funded)";

                        continue;
                    }

                    // Get allocated_amount from row or calculate it
                    $allocatedAmount = null;
                    if (! empty($row['allocated_amount'])) {
                        $allocatedAmount = $this->parseNumeric($row['allocated_amount']);
                    } else {
                        // Auto-calculate based on salary and FTE
                        $salaryToUse = $employment->pass_probation_salary ?? 0;
                        if ($employment->isOnProbation() && $employment->probation_salary) {
                            $salaryToUse = $employment->probation_salary;
                        }
                        $allocatedAmount = round($salaryToUse * $fteDecimal, 2);
                    }

                    // Get salary_type from row or auto-detect
                    $salaryType = null;
                    if (! empty($row['salary_type'])) {
                        $salaryType = strtolower(trim($row['salary_type']));
                        if (! in_array($salaryType, ['probation_salary', 'pass_probation_salary'])) {
                            $errors[] = "Row {$index}: Invalid salary_type '{$salaryType}' (must be: probation_salary, pass_probation_salary)";

                            continue;
                        }
                    } else {
                        // Auto-detect
                        $salaryType = ($employment->isOnProbation() && $employment->probation_salary)
                            ? 'probation_salary'
                            : 'pass_probation_salary';
                    }

                    // Get status from row or default to 'active'
                    $status = strtolower(trim($row['status'] ?? 'active'));
                    if (! in_array($status, ['active', 'historical', 'terminated'])) {
                        $errors[] = "Row {$index}: Invalid status '{$status}' (must be: active, historical, terminated)";

                        continue;
                    }

                    // Prepare allocation data
                    $allocationData = [
                        'employee_id' => $employeeId,
                        'employment_id' => $employmentId,
                        'grant_item_id' => $grantItemId,
                        'fte' => $fteDecimal,
                        'allocation_type' => $allocationType,
                        'allocated_amount' => $allocatedAmount,
                        'salary_type' => $salaryType,
                        'status' => $status,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Check if allocation already exists (match on employee, employment, grant_item, and allocation_type)
                    $existingAllocation = EmployeeFundingAllocation::where([
                        'employee_id' => $employeeId,
                        'employment_id' => $employmentId,
                        'grant_item_id' => $grantItemId,
                        'allocation_type' => $allocationType,
                    ])
                        ->first();

                    if ($existingAllocation) {
                        // Update existing allocation
                        $allocationUpdates[$existingAllocation->id] = $allocationData;
                    } else {
                        // Create new allocation
                        $allocationBatch[] = $allocationData;
                    }
                }

                // Update cache
                Cache::put("import_{$this->importId}_errors", $errors, 3600);

                // Insert new allocations
                if (count($allocationBatch)) {
                    EmployeeFundingAllocation::insert($allocationBatch);

                    $currentCount = Cache::get("import_{$this->importId}_processed_count", 0);
                    Cache::put("import_{$this->importId}_processed_count", $currentCount + count($allocationBatch), 3600);

                    Log::info('Inserted allocation batch', [
                        'count' => count($allocationBatch),
                        'import_id' => $this->importId,
                    ]);
                }

                // Update existing allocations
                if (count($allocationUpdates)) {
                    foreach ($allocationUpdates as $allocationId => $data) {
                        unset($data['created_at']); // Don't update created_at
                        EmployeeFundingAllocation::where('id', $allocationId)->update($data);
                    }

                    $currentUpdatedCount = Cache::get("import_{$this->importId}_updated_count", 0);
                    Cache::put("import_{$this->importId}_updated_count", $currentUpdatedCount + count($allocationUpdates), 3600);

                    Log::info('Updated allocation batch', [
                        'count' => count($allocationUpdates),
                        'import_id' => $this->importId,
                    ]);
                }
            });
        } catch (\Exception $e) {
            $errorMessage = 'Error in '.__METHOD__.' at line '.$e->getLine().': '.$e->getMessage();
            $errors = Cache::get("import_{$this->importId}_errors", []);
            $errors[] = $errorMessage;
            Cache::put("import_{$this->importId}_errors", $errors, 3600);

            Log::error('Employee funding allocation import failed', [
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
            '*.employment_id' => 'nullable|integer',
            '*.grant_item_id' => 'required|integer',
            '*.fte' => 'required|numeric|min:0|max:100',
            '*.allocation_type' => 'nullable|string|in:grant,org_funded',
            '*.allocated_amount' => 'nullable|numeric|min:0',
            '*.salary_type' => 'nullable|string|in:probation_salary,pass_probation_salary',
            '*.status' => 'nullable|string|in:active,historical,terminated',
            '*.start_date' => 'required|date',
            '*.end_date' => 'nullable|date',
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

                $message = "Employee funding allocation import finished! Created: {$processedCount}, Updated: {$updatedCount}";
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
                Cache::forget("import_{$this->importId}_processed_allocations");
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
}
