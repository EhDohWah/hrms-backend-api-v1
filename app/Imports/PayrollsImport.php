<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Payroll;
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

class PayrollsImport extends DefaultValueBinder implements ShouldQueue, SkipsEmptyRows, SkipsOnFailure, ToCollection, WithChunkReading, WithCustomValueBinder, WithEvents, WithHeadingRow
{
    use Importable, RegistersEventListeners;

    public $userId;

    public $importId;

    protected $existingStaffIds = [];

    protected $existingEmployments = [];

    protected $fundingAllocations = [];

    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;

        // Prefetch staff_ids from DB
        $this->existingStaffIds = Employee::pluck('id', 'staff_id')->toArray();

        // Prefetch active employments (staff_id -> employment_id)
        $this->existingEmployments = Employment::join('employees', 'employments.employee_id', '=', 'employees.id')
            ->where('employments.status', true)
            ->pluck('employments.id', 'employees.staff_id')
            ->toArray();

        // Prefetch funding allocations lookup
        $this->fundingAllocations = EmployeeFundingAllocation::pluck('id')->toArray();

        // Initialize cache keys for this import
        Cache::put("import_{$this->importId}_errors", [], 3600);
        Cache::put("import_{$this->importId}_validation_failures", [], 3600);
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
        Log::info('Payroll import chunk started', [
            'rows_in_chunk' => $rows->count(),
            'import_id' => $this->importId,
        ]);

        // Normalize data
        $normalized = $rows->map(function ($r) {
            // Normalize date field
            if (! empty($r['pay_period_date']) && is_numeric($r['pay_period_date'])) {
                try {
                    $r['pay_period_date'] = ExcelDate::excelToDateTimeObject($r['pay_period_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    Log::warning('Failed to parse pay_period_date', [
                        'value' => $r['pay_period_date'],
                        'error' => $e->getMessage(),
                    ]);
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
            Log::info('Starting payroll import process', ['rows_count' => $rows->count()]);

            DB::transaction(function () use ($normalized) {
                $payrollBatch = [];
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

                    // Check if employment exists
                    if (! isset($this->existingEmployments[$staffId])) {
                        $errors[] = "Row {$index}: No active employment found for staff_id '{$staffId}'";

                        continue;
                    }

                    $employmentId = $this->existingEmployments[$staffId];

                    // Validate employee_funding_allocation_id
                    $fundingAllocationId = (int) ($row['employee_funding_allocation_id'] ?? 0);
                    if (! $fundingAllocationId || ! in_array($fundingAllocationId, $this->fundingAllocations)) {
                        $errors[] = "Row {$index}: Invalid employee_funding_allocation_id '{$fundingAllocationId}'";

                        continue;
                    }

                    // Parse pay_period_date
                    $payPeriodDate = $this->parseDate($row['pay_period_date'] ?? null);
                    if (! $payPeriodDate) {
                        $errors[] = "Row {$index}: Missing or invalid pay_period_date";

                        continue;
                    }

                    // Prepare payroll data (all values provided by user, no auto-calculation)
                    $payrollData = [
                        'employment_id' => $employmentId,
                        'employee_funding_allocation_id' => $fundingAllocationId,
                        'pay_period_date' => $payPeriodDate,
                        'gross_salary' => $this->parseNumeric($row['gross_salary'] ?? 0),
                        'gross_salary_by_FTE' => $this->parseNumeric($row['gross_salary_by_fte'] ?? 0),
                        'compensation_refund' => $this->parseNumeric($row['compensation_refund'] ?? 0),
                        'thirteen_month_salary' => $this->parseNumeric($row['thirteen_month_salary'] ?? 0),
                        'thirteen_month_salary_accured' => $this->parseNumeric($row['thirteen_month_salary_accured'] ?? 0),
                        'pvd' => $this->parseNumeric($row['pvd'] ?? null),
                        'saving_fund' => $this->parseNumeric($row['saving_fund'] ?? null),
                        'employer_social_security' => $this->parseNumeric($row['employer_social_security'] ?? 0),
                        'employee_social_security' => $this->parseNumeric($row['employee_social_security'] ?? 0),
                        'employer_health_welfare' => $this->parseNumeric($row['employer_health_welfare'] ?? 0),
                        'employee_health_welfare' => $this->parseNumeric($row['employee_health_welfare'] ?? 0),
                        'tax' => $this->parseNumeric($row['tax'] ?? 0),
                        'net_salary' => $this->parseNumeric($row['net_salary'] ?? 0),
                        'total_salary' => $this->parseNumeric($row['total_salary'] ?? 0),
                        'total_pvd' => $this->parseNumeric($row['total_pvd'] ?? 0),
                        'total_saving_fund' => $this->parseNumeric($row['total_saving_fund'] ?? 0),
                        'salary_bonus' => $this->parseNumeric($row['salary_bonus'] ?? null),
                        'total_income' => $this->parseNumeric($row['total_income'] ?? 0),
                        'employer_contribution' => $this->parseNumeric($row['employer_contribution'] ?? 0),
                        'total_deduction' => $this->parseNumeric($row['total_deduction'] ?? 0),
                        'notes' => $row['notes'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // No duplicate detection - each row creates a new record
                    $payrollBatch[] = $payrollData;
                }

                // Update cache
                Cache::put("import_{$this->importId}_errors", $errors, 3600);

                // Insert new payroll records
                if (count($payrollBatch)) {
                    Payroll::insert($payrollBatch);

                    $currentCount = Cache::get("import_{$this->importId}_processed_count", 0);
                    Cache::put("import_{$this->importId}_processed_count", $currentCount + count($payrollBatch), 3600);

                    Log::info('Inserted payroll batch', [
                        'count' => count($payrollBatch),
                        'import_id' => $this->importId,
                    ]);
                }
            });
        } catch (\Exception $e) {
            $errorMessage = 'Error in '.__METHOD__.' at line '.$e->getLine().': '.$e->getMessage();
            $errors = Cache::get("import_{$this->importId}_errors", []);
            $errors[] = $errorMessage;
            Cache::put("import_{$this->importId}_errors", $errors, 3600);

            Log::error('Payroll import failed', [
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
            '*.employee_funding_allocation_id' => 'required|integer',
            '*.pay_period_date' => 'required|date',
            '*.gross_salary' => 'required|numeric|min:0',
            '*.gross_salary_by_fte' => 'required|numeric|min:0',
            '*.compensation_refund' => 'nullable|numeric|min:0',
            '*.thirteen_month_salary' => 'nullable|numeric|min:0',
            '*.thirteen_month_salary_accured' => 'nullable|numeric|min:0',
            '*.pvd' => 'nullable|numeric|min:0',
            '*.saving_fund' => 'nullable|numeric|min:0',
            '*.employer_social_security' => 'nullable|numeric|min:0',
            '*.employee_social_security' => 'nullable|numeric|min:0',
            '*.employer_health_welfare' => 'nullable|numeric|min:0',
            '*.employee_health_welfare' => 'nullable|numeric|min:0',
            '*.tax' => 'nullable|numeric|min:0',
            '*.net_salary' => 'required|numeric|min:0',
            '*.total_salary' => 'nullable|numeric|min:0',
            '*.total_pvd' => 'nullable|numeric|min:0',
            '*.total_saving_fund' => 'nullable|numeric|min:0',
            '*.salary_bonus' => 'nullable|numeric|min:0',
            '*.total_income' => 'nullable|numeric|min:0',
            '*.employer_contribution' => 'nullable|numeric|min:0',
            '*.total_deduction' => 'nullable|numeric|min:0',
            '*.notes' => 'nullable|string',
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
                $validationFailures = Cache::get("import_{$this->importId}_validation_failures", []);

                $message = "Payroll import finished! Created: {$processedCount}";
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

        Log::error($errorMessage, [
            'error' => $errorDetails,
            'trace' => $trace,
            'import_id' => $this->importId,
        ]);

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new ImportedCompletedNotification($errorMessage.': '.$errorDetails));
        }
    }

    protected function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }

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
        if ($value === null || $value === '') {
            return null;
        }

        return floatval(str_replace(',', '', $value));
    }
}
