<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\ImportedCompletedNotification;
use App\Notifications\ImportFailedNotification;
use App\Services\NotificationService;
// Note: ShouldQueue removed - using synchronous processing like GrantsImport
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Validators\Failure;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeesImport extends DefaultValueBinder implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithChunkReading, WithCustomValueBinder, WithEvents, WithHeadingRow, WithStartRow
{
    use Importable, RegistersEventListeners;

    /**
     * Valid organization values (SMRU and BHF only)
     */
    public const VALID_ORGANIZATIONS = ['SMRU', 'BHF'];

    /**
     * Valid employee status values
     */
    public const VALID_STATUSES = ['Expats (Local)', 'Local ID Staff', 'Local non ID Staff'];

    /**
     * Valid gender values
     */
    public const VALID_GENDERS = ['M', 'F'];

    /**
     * Valid marital status values
     */
    public const VALID_MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed'];

    /**
     * Valid identification types (display values shown to users)
     */
    public const VALID_IDENTIFICATION_TYPES_DISPLAY = [
        '10 years ID',
        'Burmese ID',
        'CI',
        'Borderpass',
        'Thai ID',
        'Passport',
        'Other',
    ];

    /**
     * Valid identification types (database values)
     */
    public const VALID_IDENTIFICATION_TYPES_DATABASE = [
        '10YearsID',
        'BurmeseID',
        'CI',
        'Borderpass',
        'ThaiID',
        'Passport',
        'Other',
    ];

    /**
     * Mapping from display values to database values for identification types
     */
    public const IDENTIFICATION_TYPE_MAPPING = [
        '10 years ID' => '10YearsID',
        'Burmese ID' => 'BurmeseID',
        'CI' => 'CI',
        'Borderpass' => 'Borderpass',
        'Thai ID' => 'ThaiID',
        'Passport' => 'Passport',
        'Other' => 'Other',
    ];

    /**
     * Validation constraints for staff ID
     */
    public const STAFF_ID_MIN_LENGTH = 3;

    public const STAFF_ID_MAX_LENGTH = 50;

    /**
     * Validation constraints for first name
     */
    public const FIRST_NAME_MIN_LENGTH = 2;

    public const FIRST_NAME_MAX_LENGTH = 255;

    /**
     * Date of birth validation constraints
     */
    public const DATE_OF_BIRTH_MIN_YEAR = 1940;

    public const DATE_OF_BIRTH_MIN_AGE = 18;

    public const DATE_OF_BIRTH_MAX_AGE = 84;

    public const RETIREMENT_AGE_WARNING = 65;

    /**
     * Template row configuration
     */
    public const TEMPLATE_HEADER_ROW = 1;

    public const TEMPLATE_VALIDATION_ROW = 2;

    public const TEMPLATE_DATA_START_ROW = 3;

    public $userId;

    public $importId;

    /**
     * Existing staff IDs grouped by organization for duplicate checking
     * Structure: ['SMRU' => ['EMP001', 'EMP002'], 'BHF' => ['EMP003']]
     */
    protected $existingStaffIdsByOrg = [];

    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;

        // Prefetch staff_ids grouped by organization for duplicate checking
        // Database has unique constraint on (staff_id, organization) combination
        $employees = Employee::select('staff_id', 'organization')->get();
        $this->existingStaffIdsByOrg = $employees->groupBy('organization')->map(function ($group) {
            return $group->pluck('staff_id')->map('strval')->toArray();
        })->toArray();

        // Initialize cache keys for this import (1 hour TTL)
        Cache::put("import_{$this->importId}_errors", [], 3600);
        Cache::put("import_{$this->importId}_validation_failures", [], 3600);
        Cache::put("import_{$this->importId}_warnings", [], 3600);
        Cache::put("import_{$this->importId}_processed_staff_ids", [], 3600);
        Cache::put("import_{$this->importId}_seen_staff_ids", [], 3600);
        Cache::put("import_{$this->importId}_processed_count", 0, 3600);
    }

    /**
     * Specify which row to start reading data from.
     * Row 1: Column headers
     * Row 2: Validation rules/instructions (SKIP)
     * Row 3+: Actual employee data
     */
    public function startRow(): int
    {
        return 3;
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

    /**
     * Process imported rows using two-pass validation.
     * Pass 1: Validate all rows without inserting anything
     * Pass 2: Insert all validated data if no errors (atomic operation)
     *
     * This ensures chunk either completely succeeds or completely fails.
     */
    public function collection(Collection $rows)
    {
        Log::info('Import chunk started', [
            'rows_in_chunk' => $rows->count(),
            'import_id' => $this->importId,
        ]);

        // =========================================================================
        // NORMALIZATION
        // Convert Excel dates to Y-m-d format, drop age formula column
        // =========================================================================
        $normalized = $rows->map(function ($r) {
            // Convert Excel numeric date to Y-m-d string
            if (! empty($r['date_of_birth']) && is_numeric($r['date_of_birth'])) {
                try {
                    $r['date_of_birth'] = ExcelDate::excelToDateTimeObject($r['date_of_birth'])
                        ->format('Y-m-d');
                } catch (\Exception $e) {
                    // Leave as-is, validation will catch invalid dates
                }
            }

            // Convert Excel numeric date for identification issue date
            if (! empty($r['id_issue_date']) && is_numeric($r['id_issue_date'])) {
                try {
                    $r['id_issue_date'] = ExcelDate::excelToDateTimeObject($r['id_issue_date'])
                        ->format('Y-m-d');
                } catch (\Exception $e) {
                    // Leave as-is, validation will catch invalid dates
                }
            }

            // Convert Excel numeric date for identification expiry date
            if (! empty($r['id_expiry_date']) && is_numeric($r['id_expiry_date'])) {
                try {
                    $r['id_expiry_date'] = ExcelDate::excelToDateTimeObject($r['id_expiry_date'])
                        ->format('Y-m-d');
                } catch (\Exception $e) {
                    // Leave as-is, validation will catch invalid dates
                }
            }

            // Drop age formula column (not needed for import)
            unset($r['age']);

            return $r;
        });

        // Capture first row debug snapshot (only once per import)
        if (! Cache::has("import_{$this->importId}_first_row_snapshot") && $rows->count() > 0) {
            $first = $rows->first()->toArray();
            Cache::put("import_{$this->importId}_first_row_snapshot", [
                'columns' => array_keys($first),
                'values' => $first,
            ], 3600);
            Log::debug('First row snapshot captured', ['columns' => array_keys($first), 'import_id' => $this->importId]);
        }

        DB::disableQueryLog();

        try {
            DB::transaction(function () use ($normalized) {
                // =====================================================================
                // PASS 1: VALIDATION
                // Validate ALL rows first, collecting all errors before any inserts
                // =====================================================================
                Log::info('Pass 1: Starting validation', [
                    'row_count' => $normalized->count(),
                    'import_id' => $this->importId,
                ]);

                $validatedEmployees = [];
                $validatedBeneficiaries = [];
                $errors = [];
                $warnings = [];

                // Seen staff IDs in this import, keyed by organization
                // Structure: ['SMRU' => ['EMP001', 'EMP002'], 'BHF' => ['EMP003']]
                $seenStaffIds = Cache::get("import_{$this->importId}_seen_staff_ids", []);

                foreach ($normalized as $index => $row) {
                    // Calculate actual Excel row number (accounting for header and validation rows)
                    $actualRow = $index + self::TEMPLATE_DATA_START_ROW;

                    // Skip completely empty rows
                    if (! $row->filter()->count()) {
                        Log::debug('Skipping empty row', ['row_index' => $index, 'actual_row' => $actualRow]);

                        continue;
                    }

                    $rowErrors = [];
                    $rowWarnings = [];

                    // --- Validate each field using helper methods ---
                    $orgValidation = $this->validateOrganization($row['org'] ?? '', $actualRow);
                    if (! $orgValidation['valid']) {
                        $rowErrors[] = $orgValidation['error'];
                    }

                    $staffIdValidation = $this->validateStaffId($row['staff_id'] ?? '', $actualRow);
                    if (! $staffIdValidation['valid']) {
                        $rowErrors[] = $staffIdValidation['error'];
                    }

                    $firstNameValidation = $this->validateFirstName($row['first_name'] ?? '', $actualRow);
                    if (! $firstNameValidation['valid']) {
                        $rowErrors[] = $firstNameValidation['error'];
                    }

                    $genderValidation = $this->validateGender($row['gender'] ?? '', $actualRow);
                    if (! $genderValidation['valid']) {
                        $rowErrors[] = $genderValidation['error'];
                    }

                    $dobValidation = $this->validateDateOfBirth($row['date_of_birth'] ?? '', $actualRow);
                    if (! $dobValidation['valid']) {
                        $rowErrors[] = $dobValidation['error'];
                    }
                    if (! empty($dobValidation['warnings'])) {
                        $rowWarnings = array_merge($rowWarnings, $dobValidation['warnings']);
                    }

                    $statusValidation = $this->validateStatus($row['status'] ?? '', $actualRow);
                    if (! $statusValidation['valid']) {
                        $rowErrors[] = $statusValidation['error'];
                    }

                    $maritalValidation = $this->validateMaritalStatus($row['marital_status'] ?? '', $actualRow);
                    if (! $maritalValidation['valid']) {
                        $rowErrors[] = $maritalValidation['error'];
                    }

                    $identificationTypeValidation = $this->validateIdentificationType($row['id_type'] ?? '', $actualRow);
                    if (! $identificationTypeValidation['valid']) {
                        $rowErrors[] = $identificationTypeValidation['error'];
                    }

                    // --- Duplicate checking (only if org and staff_id both valid) ---
                    if ($orgValidation['valid'] && $staffIdValidation['valid']) {
                        $dupValidation = $this->validateDuplicateStaffId(
                            $staffIdValidation['staffId'],
                            $orgValidation['organization'],
                            $actualRow,
                            $seenStaffIds
                        );
                        if (! $dupValidation['valid']) {
                            $rowErrors[] = $dupValidation['error'];
                        }
                    }

                    // --- Cross-field validation ---
                    $crossValidation = $this->validateCrossFieldRules($row->toArray(), $actualRow);
                    if (! empty($crossValidation['errors'])) {
                        $rowErrors = array_merge($rowErrors, $crossValidation['errors']);
                    }
                    if (! empty($crossValidation['warnings'])) {
                        $rowWarnings = array_merge($rowWarnings, $crossValidation['warnings']);
                    }

                    // Collect errors and warnings
                    $errors = array_merge($errors, $rowErrors);
                    $warnings = array_merge($warnings, $rowWarnings);

                    // --- If row passes all validation, prepare data for insertion ---
                    if (empty($rowErrors)) {
                        // Track this staff_id as seen for duplicate detection
                        $org = $orgValidation['organization'];
                        if (! isset($seenStaffIds[$org])) {
                            $seenStaffIds[$org] = [];
                        }
                        $seenStaffIds[$org][] = $staffIdValidation['staffId'];

                        // Build employee data using validated values
                        $validatedEmployees[] = [
                            'organization' => $orgValidation['organization'],
                            'staff_id' => $staffIdValidation['staffId'],
                            'first_name_en' => $firstNameValidation['firstName'],
                            'gender' => $genderValidation['gender'],
                            'date_of_birth' => $dobValidation['dateOfBirth'],
                            'status' => $statusValidation['status'],
                            'marital_status' => $maritalValidation['maritalStatus'],
                            'identification_type' => $identificationTypeValidation['identificationType'],
                            'identification_number' => $this->trimOrNull($row['id_number'] ?? null),
                            'identification_issue_date' => $this->parseDate($row['id_issue_date'] ?? null),
                            'identification_expiry_date' => $this->parseDate($row['id_expiry_date'] ?? null),
                            'military_status' => $this->convertMilitaryStatusToBoolean($row['military_status'] ?? null),
                            'initial_en' => $this->trimOrNull($row['initial'] ?? null),
                            'last_name_en' => $this->trimOrNull($row['last_name'] ?? null),
                            'initial_th' => $this->trimOrNull($row['initial_th'] ?? null),
                            'first_name_th' => $this->trimOrNull($row['first_name_th'] ?? null),
                            'last_name_th' => $this->trimOrNull($row['last_name_th'] ?? null),
                            'nationality' => $this->trimOrNull($row['nationality'] ?? null),
                            'religion' => $this->trimOrNull($row['religion'] ?? null),
                            'social_security_number' => $this->trimOrNull($row['social_security_no'] ?? null),
                            'tax_number' => $this->trimOrNull($row['tax_no'] ?? null),
                            'driver_license_number' => $this->trimOrNull($row['driver_license'] ?? null),
                            'bank_name' => $this->trimOrNull($row['bank_name'] ?? null),
                            'bank_branch' => $this->trimOrNull($row['bank_branch'] ?? null),
                            'bank_account_name' => $this->trimOrNull($row['bank_account_name'] ?? null),
                            'bank_account_number' => $this->trimOrNull($row['bank_account_no'] ?? null),
                            'mobile_phone' => $this->trimOrNull($row['mobile_no'] ?? null),
                            'current_address' => $this->trimOrNull($row['current_address'] ?? null),
                            'permanent_address' => $this->trimOrNull($row['permanent_address'] ?? null),
                            'spouse_name' => $this->trimOrNull($row['spouse_name'] ?? null),
                            'spouse_phone_number' => $this->trimOrNull($row['spouse_mobile_no'] ?? null),
                            'emergency_contact_person_name' => $this->trimOrNull($row['emergency_contact_name'] ?? null),
                            'emergency_contact_person_relationship' => $this->trimOrNull($row['relationship'] ?? null),
                            'emergency_contact_person_phone' => $this->trimOrNull($row['emergency_mobile_no'] ?? null),
                            'father_name' => $this->trimOrNull($row['father_name'] ?? null),
                            'father_occupation' => $this->trimOrNull($row['father_occupation'] ?? null),
                            'father_phone_number' => $this->trimOrNull($row['father_mobile_no'] ?? null),
                            'mother_name' => $this->trimOrNull($row['mother_name'] ?? null),
                            'mother_occupation' => $this->trimOrNull($row['mother_occupation'] ?? null),
                            'mother_phone_number' => $this->trimOrNull($row['mother_mobile_no'] ?? null),
                            'remark' => $this->trimOrNull($row['remark'] ?? null),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        // Prepare beneficiary data (linked later after employees inserted)
                        $kin1Name = $this->trimOrNull($row['kin_1_name'] ?? null);
                        if ($kin1Name) {
                            $validatedBeneficiaries[] = [
                                '_staff_id' => $staffIdValidation['staffId'], // Temporary key for linking
                                'beneficiary_name' => $kin1Name,
                                'beneficiary_relationship' => $this->trimOrNull($row['kin_1_relationship'] ?? null),
                                'phone_number' => $this->trimOrNull($row['kin_1_mobile'] ?? null),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        $kin2Name = $this->trimOrNull($row['kin_2_name'] ?? null);
                        if ($kin2Name) {
                            $validatedBeneficiaries[] = [
                                '_staff_id' => $staffIdValidation['staffId'], // Temporary key for linking
                                'beneficiary_name' => $kin2Name,
                                'beneficiary_relationship' => $this->trimOrNull($row['kin_2_relationship'] ?? null),
                                'phone_number' => $this->trimOrNull($row['kin_2_mobile'] ?? null),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                // Update seen staff IDs cache
                Cache::put("import_{$this->importId}_seen_staff_ids", $seenStaffIds, 3600);

                Log::info('Pass 1: Validation completed', [
                    'error_count' => count($errors),
                    'warning_count' => count($warnings),
                    'valid_employee_count' => count($validatedEmployees),
                    'import_id' => $this->importId,
                ]);

                // =====================================================================
                // BETWEEN PASSES: CHECK FOR ERRORS
                // If any errors exist, abort the entire chunk (atomic operation)
                // =====================================================================
                if (! empty($errors)) {
                    // Add errors to cache
                    $existingErrors = Cache::get("import_{$this->importId}_errors", []);
                    Cache::put("import_{$this->importId}_errors", array_merge($existingErrors, $errors), 3600);

                    // Add warnings to cache (for reporting)
                    if (! empty($warnings)) {
                        $existingWarnings = Cache::get("import_{$this->importId}_warnings", []);
                        Cache::put("import_{$this->importId}_warnings", array_merge($existingWarnings, $warnings), 3600);
                    }

                    // Log first few errors for debugging
                    Log::error('Validation failed - no records will be created', [
                        'total_errors' => count($errors),
                        'first_errors' => array_slice($errors, 0, 5),
                        'import_id' => $this->importId,
                    ]);

                    // Throw exception to rollback transaction
                    throw new \Exception('Validation failed for chunk - '.count($errors).' error(s) found. No records created.');
                }

                // =====================================================================
                // PASS 2: INSERTION
                // Only reached if Pass 1 had zero errors
                // =====================================================================
                Log::info('Pass 2: Starting insertion', [
                    'employee_count' => count($validatedEmployees),
                    'beneficiary_count' => count($validatedBeneficiaries),
                    'import_id' => $this->importId,
                ]);

                if (empty($validatedEmployees)) {
                    Log::info('No valid employees to insert', ['import_id' => $this->importId]);

                    return;
                }

                // Insert employees
                Employee::insert($validatedEmployees);
                Log::info('Inserted employees', ['count' => count($validatedEmployees), 'import_id' => $this->importId]);

                // Fetch inserted employee IDs
                $staffIds = array_column($validatedEmployees, 'staff_id');
                $employeeIdMap = Employee::whereIn('staff_id', $staffIds)
                    ->pluck('id', 'staff_id')
                    ->toArray();

                // Link beneficiaries to employees and insert
                if (! empty($validatedBeneficiaries)) {
                    $beneficiariesToInsert = [];
                    foreach ($validatedBeneficiaries as $bene) {
                        $staffId = $bene['_staff_id'];
                        unset($bene['_staff_id']); // Remove temporary key

                        if (isset($employeeIdMap[$staffId])) {
                            $bene['employee_id'] = $employeeIdMap[$staffId];
                            $beneficiariesToInsert[] = $bene;
                        }
                    }

                    if (! empty($beneficiariesToInsert)) {
                        DB::table('employee_beneficiaries')->insert($beneficiariesToInsert);
                        Log::info('Inserted beneficiaries', ['count' => count($beneficiariesToInsert), 'import_id' => $this->importId]);
                    }
                }

                // Update caches
                $currentCount = Cache::get("import_{$this->importId}_processed_count", 0);
                Cache::put("import_{$this->importId}_processed_count", $currentCount + count($validatedEmployees), 3600);

                $processedStaffIds = Cache::get("import_{$this->importId}_processed_staff_ids", []);
                Cache::put("import_{$this->importId}_processed_staff_ids", array_merge($processedStaffIds, $staffIds), 3600);

                // Store warnings (non-blocking)
                if (! empty($warnings)) {
                    $existingWarnings = Cache::get("import_{$this->importId}_warnings", []);
                    Cache::put("import_{$this->importId}_warnings", array_merge($existingWarnings, $warnings), 3600);
                    Log::warning('Import warnings', ['count' => count($warnings), 'warnings' => $warnings, 'import_id' => $this->importId]);
                }

                Log::info('Pass 2: Insertion completed successfully', ['import_id' => $this->importId]);
            });
        } catch (\Exception $e) {
            // Transaction automatically rolled back
            $errorMessage = 'Import chunk failed: '.$e->getMessage();
            $existingErrors = Cache::get("import_{$this->importId}_errors", []);

            // Only add system error if it's not a validation failure
            if (strpos($e->getMessage(), 'Validation failed for chunk') === false) {
                $existingErrors[] = $errorMessage;
                Cache::put("import_{$this->importId}_errors", $existingErrors, 3600);
            }

            Log::error('Employee import chunk failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'import_id' => $this->importId,
            ]);
        }

        Log::info('Finished processing chunk', ['import_id' => $this->importId]);
    }

    /**
     * Trim string and return null if empty.
     */
    protected function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Parse date value and return Y-m-d format or null.
     * Handles both Excel numeric dates and string dates.
     */
    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            }

            return \Carbon\Carbon::parse(trim($value))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
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
            '*.id_number' => 'nullable|string',
            '*.id_issue_date' => 'nullable|date',
            '*.id_expiry_date' => 'nullable|date',
            '*.social_security_no' => 'nullable|string|max:50',
            '*.tax_no' => 'nullable|string|max:50',
            '*.driver_license' => 'nullable|string|max:100',
            '*.bank_name' => 'nullable|string|max:100',
            '*.bank_branch' => 'nullable|string|max:100',
            '*.bank_account_name' => 'nullable|string|max:100',
            '*.bank_account_no' => 'nullable|string|max:50',
            '*.mobile_no' => 'nullable|string|max:50',
            '*.current_address' => 'nullable|string',
            '*.permanent_address' => 'nullable|string',
            '*.marital_status' => 'nullable|string|max:50',
            '*.spouse_name' => 'nullable|string|max:200',
            '*.spouse_mobile_no' => 'nullable|string|max:50',
            '*.emergency_contact_name' => 'nullable|string|max:100',
            '*.relationship' => 'nullable|string|max:100',
            '*.emergency_mobile_no' => 'nullable|string|max:50',
            '*.father_name' => 'nullable|string|max:200',
            '*.father_occupation' => 'nullable|string|max:200',
            '*.father_mobile_no' => 'nullable|string|max:50',
            '*.mother_name' => 'nullable|string|max:200',
            '*.mother_occupation' => 'nullable|string|max:200',
            '*.mother_mobile_no' => 'nullable|string|max:50',
            '*.kin_1_name' => 'nullable|string|max:255',
            '*.kin_1_relationship' => 'nullable|string|max:255',
            '*.kin_1_mobile' => 'nullable|string|max:50',
            '*.kin_2_name' => 'nullable|string|max:255',
            '*.kin_2_relationship' => 'nullable|string|max:255',
            '*.kin_2_mobile' => 'nullable|string|max:50',
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

    public function getWarnings(): array
    {
        return Cache::get("import_{$this->importId}_warnings", []);
    }

    // =========================================================================
    // VALIDATION HELPER METHODS
    // These methods provide field-level validation with Levenshtein fuzzy
    // matching for typo detection, following the Grant Import patterns.
    // =========================================================================

    /**
     * Validate organization field with Levenshtein fuzzy matching for typo suggestions.
     *
     * @param  string|null  $organization  The organization value to validate
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, organization: string|null, error: string|null}
     */
    protected function validateOrganization(?string $organization, int $rowNumber): array
    {
        // Normalize to uppercase and trim
        $normalized = strtoupper(trim($organization ?? ''));

        if (empty($normalized)) {
            return [
                'valid' => false,
                'organization' => null,
                'error' => "Row {$rowNumber} Column organization: Organization is required (Cell A{$rowNumber})",
            ];
        }

        // Check exact match (case-insensitive due to normalization)
        if (in_array($normalized, self::VALID_ORGANIZATIONS)) {
            return [
                'valid' => true,
                'organization' => $normalized,
                'error' => null,
            ];
        }

        // Use Levenshtein distance to find closest match for typo detection
        $closestMatch = null;
        $minDistance = PHP_INT_MAX;

        foreach (self::VALID_ORGANIZATIONS as $validOrg) {
            $distance = levenshtein($normalized, $validOrg);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestMatch = $validOrg;
            }
        }

        // If distance is 1 or 2, suggest correction (likely typo)
        if ($minDistance <= 2 && $closestMatch !== null) {
            return [
                'valid' => false,
                'organization' => null,
                'error' => "Row {$rowNumber} Column organization: Invalid organization '{$organization}'. Did you mean '{$closestMatch}'? (Cell A{$rowNumber})",
            ];
        }

        // Completely invalid value
        $validOptions = implode(', ', self::VALID_ORGANIZATIONS);

        return [
            'valid' => false,
            'organization' => null,
            'error' => "Row {$rowNumber} Column organization: Invalid organization '{$organization}'. Must be one of: {$validOptions} (Cell A{$rowNumber})",
        ];
    }

    /**
     * Validate employee status field with fuzzy matching.
     *
     * @param  string|null  $status  The status value to validate
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, status: string|null, error: string|null}
     */
    protected function validateStatus(?string $status, int $rowNumber): array
    {
        // Trim but preserve case since status values have specific casing
        $trimmed = trim($status ?? '');

        if (empty($trimmed)) {
            return [
                'valid' => false,
                'status' => null,
                'error' => "Row {$rowNumber} Column status: Status is required (Cell L{$rowNumber})",
            ];
        }

        // Check exact match (case-sensitive)
        if (in_array($trimmed, self::VALID_STATUSES)) {
            return [
                'valid' => true,
                'status' => $trimmed,
                'error' => null,
            ];
        }

        // Use Levenshtein distance for typo detection
        $closestMatch = null;
        $minDistance = PHP_INT_MAX;

        foreach (self::VALID_STATUSES as $validStatus) {
            $distance = levenshtein(strtolower($trimmed), strtolower($validStatus));
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestMatch = $validStatus;
            }
        }

        // If distance is 3 or less, suggest correction
        if ($minDistance <= 3 && $closestMatch !== null) {
            return [
                'valid' => false,
                'status' => null,
                'error' => "Row {$rowNumber} Column status: Invalid status '{$status}'. Did you mean '{$closestMatch}'? (Cell L{$rowNumber})",
            ];
        }

        $validOptions = implode(', ', self::VALID_STATUSES);

        return [
            'valid' => false,
            'status' => null,
            'error' => "Row {$rowNumber} Column status: Invalid status '{$status}'. Must be one of: {$validOptions} (Cell L{$rowNumber})",
        ];
    }

    /**
     * Validate gender field accepts only M or F.
     *
     * @param  string|null  $gender  The gender value to validate
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, gender: string|null, error: string|null}
     */
    protected function validateGender(?string $gender, int $rowNumber): array
    {
        // Normalize to uppercase and trim
        $normalized = strtoupper(trim($gender ?? ''));

        if (empty($normalized)) {
            return [
                'valid' => false,
                'gender' => null,
                'error' => "Row {$rowNumber} Column gender: Gender is required (Cell I{$rowNumber})",
            ];
        }

        if (in_array($normalized, self::VALID_GENDERS)) {
            return [
                'valid' => true,
                'gender' => $normalized,
                'error' => null,
            ];
        }

        return [
            'valid' => false,
            'gender' => null,
            'error' => "Row {$rowNumber} Column gender: Gender must be M or F, got '{$gender}' (Cell I{$rowNumber})",
        ];
    }

    /**
     * Validate marital status field with fuzzy matching (optional field).
     *
     * @param  string|null  $maritalStatus  The marital status value to validate
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, maritalStatus: string|null, error: string|null}
     */
    protected function validateMaritalStatus(?string $maritalStatus, int $rowNumber): array
    {
        $trimmed = trim($maritalStatus ?? '');

        // Field is optional - empty is valid
        if (empty($trimmed)) {
            return [
                'valid' => true,
                'maritalStatus' => null,
                'error' => null,
            ];
        }

        // Check exact match (case-insensitive)
        foreach (self::VALID_MARITAL_STATUSES as $validStatus) {
            if (strcasecmp($trimmed, $validStatus) === 0) {
                return [
                    'valid' => true,
                    'maritalStatus' => $validStatus, // Return with proper casing
                    'error' => null,
                ];
            }
        }

        // Use Levenshtein distance for typo detection
        $closestMatch = null;
        $minDistance = PHP_INT_MAX;

        foreach (self::VALID_MARITAL_STATUSES as $validStatus) {
            $distance = levenshtein(strtolower($trimmed), strtolower($validStatus));
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestMatch = $validStatus;
            }
        }

        if ($minDistance <= 2 && $closestMatch !== null) {
            return [
                'valid' => false,
                'maritalStatus' => null,
                'error' => "Row {$rowNumber} Column marital_status: Invalid marital status '{$maritalStatus}'. Did you mean '{$closestMatch}'? (Cell AA{$rowNumber})",
            ];
        }

        $validOptions = implode(', ', self::VALID_MARITAL_STATUSES);

        return [
            'valid' => false,
            'maritalStatus' => null,
            'error' => "Row {$rowNumber} Column marital_status: Invalid marital status '{$maritalStatus}'. Must be one of: {$validOptions} (Cell AA{$rowNumber})",
        ];
    }

    /**
     * Validate identification type field and convert display value to database value.
     *
     * @param  string|null  $identificationType  The identification type to validate
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, identificationType: string|null, error: string|null}
     */
    protected function validateIdentificationType(?string $identificationType, int $rowNumber): array
    {
        $trimmed = trim($identificationType ?? '');

        // Field is optional - empty is valid
        if (empty($trimmed)) {
            return [
                'valid' => true,
                'identificationType' => null,
                'error' => null,
            ];
        }

        // Check exact match against display values (case-insensitive)
        foreach (self::VALID_IDENTIFICATION_TYPES_DISPLAY as $displayValue) {
            if (strcasecmp($trimmed, $displayValue) === 0) {
                // Convert to database value using mapping
                $dbValue = self::IDENTIFICATION_TYPE_MAPPING[$displayValue] ?? 'Other';

                return [
                    'valid' => true,
                    'identificationType' => $dbValue,
                    'error' => null,
                ];
            }
        }

        // Use Levenshtein distance for typo detection
        $closestMatch = null;
        $minDistance = PHP_INT_MAX;

        foreach (self::VALID_IDENTIFICATION_TYPES_DISPLAY as $displayValue) {
            $distance = levenshtein(strtolower($trimmed), strtolower($displayValue));
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestMatch = $displayValue;
            }
        }

        if ($minDistance <= 3 && $closestMatch !== null) {
            return [
                'valid' => false,
                'identificationType' => null,
                'error' => "Row {$rowNumber} Column identification_type: Invalid identification type '{$identificationType}'. Did you mean '{$closestMatch}'? (Cell O{$rowNumber})",
            ];
        }

        $validOptions = implode(', ', self::VALID_IDENTIFICATION_TYPES_DISPLAY);

        return [
            'valid' => false,
            'identificationType' => null,
            'error' => "Row {$rowNumber} Column identification_type: Invalid identification type '{$identificationType}'. Must be one of: {$validOptions} (Cell O{$rowNumber})",
        ];
    }

    /**
     * Validate date of birth with age range checking.
     *
     * @param  mixed  $dateOfBirth  The date value (string or Excel numeric)
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, dateOfBirth: string|null, error: string|null, warnings: array}
     */
    protected function validateDateOfBirth($dateOfBirth, int $rowNumber): array
    {
        $warnings = [];

        if (empty($dateOfBirth)) {
            return [
                'valid' => false,
                'dateOfBirth' => null,
                'error' => "Row {$rowNumber} Column date_of_birth: Date of birth is required (Cell J{$rowNumber})",
                'warnings' => [],
            ];
        }

        try {
            // Handle Excel numeric date format
            if (is_numeric($dateOfBirth)) {
                $date = \Carbon\Carbon::instance(
                    ExcelDate::excelToDateTimeObject($dateOfBirth)
                );
            } else {
                // Parse string date
                $date = \Carbon\Carbon::parse(trim($dateOfBirth));
            }

            $formatted = $date->format('Y-m-d');

            // Check if year is too far in the past
            if ($date->year < self::DATE_OF_BIRTH_MIN_YEAR) {
                return [
                    'valid' => false,
                    'dateOfBirth' => null,
                    'error' => "Row {$rowNumber} Column date_of_birth: Date of birth '{$formatted}' is too far in past (Cell J{$rowNumber})",
                    'warnings' => [],
                ];
            }

            // Calculate age
            $age = $date->diffInYears(now());

            // Check minimum age (must be at least 18)
            if ($age < self::DATE_OF_BIRTH_MIN_AGE) {
                return [
                    'valid' => false,
                    'dateOfBirth' => null,
                    'error' => "Row {$rowNumber} Column date_of_birth: Date of birth '{$formatted}' indicates age under 18 (Cell J{$rowNumber})",
                    'warnings' => [],
                ];
            }

            // Check maximum age
            if ($age > self::DATE_OF_BIRTH_MAX_AGE) {
                return [
                    'valid' => false,
                    'dateOfBirth' => null,
                    'error' => "Row {$rowNumber} Column date_of_birth: Date of birth '{$formatted}' indicates age over 84 (Cell J{$rowNumber})",
                    'warnings' => [],
                ];
            }

            // Add warning for employees past typical retirement age
            if ($age >= self::RETIREMENT_AGE_WARNING) {
                $warnings[] = "Row {$rowNumber}: Employee age {$age} exceeds typical retirement age";
            }

            return [
                'valid' => true,
                'dateOfBirth' => $formatted,
                'error' => null,
                'warnings' => $warnings,
            ];
        } catch (\Exception $e) {
            $displayValue = is_scalar($dateOfBirth) ? $dateOfBirth : 'invalid';

            return [
                'valid' => false,
                'dateOfBirth' => null,
                'error' => "Row {$rowNumber} Column date_of_birth: Invalid date format '{$displayValue}' (Cell J{$rowNumber})",
                'warnings' => [],
            ];
        }
    }

    /**
     * Validate staff ID format and length.
     *
     * @param  string|null  $staffId  The staff ID to validate
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, staffId: string|null, error: string|null}
     */
    protected function validateStaffId(?string $staffId, int $rowNumber): array
    {
        $trimmed = trim($staffId ?? '');

        if (empty($trimmed)) {
            return [
                'valid' => false,
                'staffId' => null,
                'error' => "Row {$rowNumber} Column staff_id: Staff ID is required (Cell B{$rowNumber})",
            ];
        }

        $length = mb_strlen($trimmed);

        if ($length < self::STAFF_ID_MIN_LENGTH) {
            return [
                'valid' => false,
                'staffId' => null,
                'error' => "Row {$rowNumber} Column staff_id: Staff ID must be at least ".self::STAFF_ID_MIN_LENGTH." characters, got '{$trimmed}' (Cell B{$rowNumber})",
            ];
        }

        if ($length > self::STAFF_ID_MAX_LENGTH) {
            return [
                'valid' => false,
                'staffId' => null,
                'error' => "Row {$rowNumber} Column staff_id: Staff ID exceeds ".self::STAFF_ID_MAX_LENGTH.' characters (Cell B{$rowNumber})',
            ];
        }

        // Only allow alphanumeric characters and dash
        if (! preg_match('/^[A-Za-z0-9-]+$/', $trimmed)) {
            return [
                'valid' => false,
                'staffId' => null,
                'error' => "Row {$rowNumber} Column staff_id: Staff ID contains invalid characters. Only letters, numbers, dash allowed (Cell B{$rowNumber})",
            ];
        }

        return [
            'valid' => true,
            'staffId' => $trimmed,
            'error' => null,
        ];
    }

    /**
     * Validate first name length.
     *
     * @param  string|null  $firstName  The first name to validate
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, firstName: string|null, error: string|null}
     */
    protected function validateFirstName(?string $firstName, int $rowNumber): array
    {
        $trimmed = trim($firstName ?? '');

        if (empty($trimmed)) {
            return [
                'valid' => false,
                'firstName' => null,
                'error' => "Row {$rowNumber} Column first_name: First name is required (Cell D{$rowNumber})",
            ];
        }

        $length = mb_strlen($trimmed);

        if ($length < self::FIRST_NAME_MIN_LENGTH) {
            return [
                'valid' => false,
                'firstName' => null,
                'error' => "Row {$rowNumber} Column first_name: First name must be at least ".self::FIRST_NAME_MIN_LENGTH.' characters (Cell D{$rowNumber})',
            ];
        }

        if ($length > self::FIRST_NAME_MAX_LENGTH) {
            return [
                'valid' => false,
                'firstName' => null,
                'error' => "Row {$rowNumber} Column first_name: First name exceeds ".self::FIRST_NAME_MAX_LENGTH.' characters (Cell D{$rowNumber})',
            ];
        }

        return [
            'valid' => true,
            'firstName' => $trimmed,
            'error' => null,
        ];
    }

    /**
     * Check staff ID uniqueness within organization for both current import and database.
     * Database has unique constraint on (staff_id, organization) combination.
     *
     * @param  string  $staffId  The staff ID to check
     * @param  string  $organization  The organization to check within
     * @param  int  $rowNumber  Excel row number for error messages
     * @param  array  $seenInCurrentImport  Staff IDs seen so far in this import, keyed by organization
     * @return array{valid: bool, error: string|null}
     */
    protected function validateDuplicateStaffId(string $staffId, string $organization, int $rowNumber, array $seenInCurrentImport): array
    {
        // Check if staffId exists in current import for same organization
        if (isset($seenInCurrentImport[$organization]) && in_array($staffId, $seenInCurrentImport[$organization])) {
            return [
                'valid' => false,
                'error' => "Row {$rowNumber} Column staff_id: Duplicate staff_id '{$staffId}' found in import file for organization '{$organization}' (Cell B{$rowNumber})",
            ];
        }

        // Check if staffId exists in database for same organization
        if (isset($this->existingStaffIdsByOrg[$organization]) && in_array($staffId, $this->existingStaffIdsByOrg[$organization])) {
            return [
                'valid' => false,
                'error' => "Row {$rowNumber} Column staff_id: Staff ID '{$staffId}' already exists in database for organization '{$organization}' (Cell B{$rowNumber})",
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Validate cross-field business rules spanning multiple fields.
     *
     * @param  array  $row  The row data as array
     * @param  int  $rowNumber  Excel row number for error messages
     * @return array{valid: bool, errors: array, warnings: array}
     */
    protected function validateCrossFieldRules(array $row, int $rowNumber): array
    {
        $errors = [];
        $warnings = [];

        // === Spouse Information Cross-Validation ===
        $maritalStatus = trim($row['marital_status'] ?? '');
        $spouseName = trim($row['spouse_name'] ?? '');
        $spouseMobileNo = trim($row['spouse_mobile_no'] ?? '');

        if (strcasecmp($maritalStatus, 'Married') === 0 && empty($spouseName)) {
            $errors[] = "Row {$rowNumber}: Marital status is 'Married' but spouse name is missing (Cells AA{$rowNumber}, AB{$rowNumber})";
        }

        if (! empty($spouseName) && ! empty($maritalStatus) && strcasecmp($maritalStatus, 'Married') !== 0) {
            $errors[] = "Row {$rowNumber}: Spouse name provided but marital status is '{$maritalStatus}'. Change to 'Married' or remove spouse information (Cells AA{$rowNumber}, AB{$rowNumber})";
        }

        if (! empty($spouseName) && empty($spouseMobileNo)) {
            $warnings[] = "Row {$rowNumber}: Spouse name provided without spouse mobile number (Cells AB{$rowNumber}, AC{$rowNumber})";
        }

        // === Identification Type and Number Together ===
        $identificationType = trim($row['id_type'] ?? '');
        $identificationNumber = trim($row['id_number'] ?? '');

        if (! empty($identificationType) && empty($identificationNumber)) {
            $errors[] = "Row {$rowNumber}: Identification type '{$identificationType}' provided but identification number is missing (Cells O{$rowNumber}, P{$rowNumber})";
        }

        if (! empty($identificationNumber) && empty($identificationType)) {
            $errors[] = "Row {$rowNumber}: Identification number provided but identification type is missing (Cells O{$rowNumber}, P{$rowNumber})";
        }

        // === Identification Dates Validation ===
        $idIssueDate = trim($row['id_issue_date'] ?? '');
        $idExpiryDate = trim($row['id_expiry_date'] ?? '');

        if (! empty($idIssueDate) && ! empty($idExpiryDate)) {
            try {
                $issueDate = \Carbon\Carbon::parse($idIssueDate);
                $expiryDate = \Carbon\Carbon::parse($idExpiryDate);

                if ($expiryDate->lte($issueDate)) {
                    $errors[] = "Row {$rowNumber}: ID expiry date must be after issue date (Cells Q{$rowNumber}, R{$rowNumber})";
                }
            } catch (\Exception $e) {
                // Date parsing errors will be caught by other validation
            }
        }

        if (! empty($idIssueDate)) {
            try {
                $issueDate = \Carbon\Carbon::parse($idIssueDate);
                if ($issueDate->isFuture()) {
                    $errors[] = "Row {$rowNumber}: ID issue date cannot be in the future (Cell Q{$rowNumber})";
                }
            } catch (\Exception $e) {
                // Date parsing errors will be caught by other validation
            }
        }

        // === Beneficiary Information Complete ===
        $kin1Name = trim($row['kin_1_name'] ?? '');
        $kin1Relationship = trim($row['kin_1_relationship'] ?? '');
        $kin2Name = trim($row['kin_2_name'] ?? '');
        $kin2Relationship = trim($row['kin_2_relationship'] ?? '');

        if (! empty($kin1Name) && empty($kin1Relationship)) {
            $errors[] = "Row {$rowNumber}: Beneficiary 1 name provided but relationship is missing (Cells AM{$rowNumber}, AN{$rowNumber})";
        }

        if (! empty($kin2Name) && empty($kin2Relationship)) {
            $errors[] = "Row {$rowNumber}: Beneficiary 2 name provided but relationship is missing (Cells AP{$rowNumber}, AQ{$rowNumber})";
        }

        // === Phone Number Format Validation (warnings only) ===
        $phoneFields = [
            'mobile_no' => 'Mobile phone',
            'spouse_mobile_no' => 'Spouse phone',
            'emergency_mobile_no' => 'Emergency contact phone',
            'father_mobile_no' => 'Father phone',
            'mother_mobile_no' => 'Mother phone',
            'kin_1_mobile' => 'Beneficiary 1 phone',
            'kin_2_mobile' => 'Beneficiary 2 phone',
        ];

        foreach ($phoneFields as $field => $label) {
            $value = trim($row[$field] ?? '');
            if (! empty($value) && ! preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $value)) {
                $warnings[] = "Row {$rowNumber} Column {$label}: Phone number format unusual '{$value}'";
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Convert military status string to boolean value.
     * "Completed" or "Exempt" => true, "NotApplicable" or "N/A" => false, empty => null
     *
     * @param  string|null  $value  The military status string value
     */
    protected function convertMilitaryStatusToBoolean(?string $value): ?bool
    {
        if (empty($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['completed', 'exempt', 'yes', 'true', '1'])) {
            return true;
        }

        if (in_array($normalized, ['notapplicable', 'n/a', 'not applicable', 'no', 'false', '0'])) {
            return false;
        }

        // Default to null for unrecognized values
        return null;
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
                $warnings = Cache::get("import_{$this->importId}_warnings", []);

                // Store final result summary for controller to read (especially for sync queue)
                Cache::put("import_result_{$this->importId}", [
                    'processed' => $processedCount,
                    'errors' => array_merge($errors, $validationFailures),
                    'warnings' => $warnings,
                    'skipped' => count($errors) + count($validationFailures),
                ], 300); // 5 minutes TTL for sync processing

                $message = "Employee import finished! Processed: {$processedCount} employees";
                if (count($errors) > 0) {
                    $message .= ', Errors: '.count($errors);
                }
                if (count($validationFailures) > 0) {
                    $message .= ', Validation failures: '.count($validationFailures);
                }

                $user = User::find($this->userId);
                if ($user) {
                    app(NotificationService::class)->notifyUser(
                        $user,
                        new ImportedCompletedNotification($message, 'import')
                    );
                }

                // Clean up intermediate cache keys (keep result summary)
                Cache::forget("import_{$this->importId}_errors");
                Cache::forget("import_{$this->importId}_validation_failures");
                Cache::forget("import_{$this->importId}_processed_staff_ids");
                Cache::forget("import_{$this->importId}_seen_staff_ids");
                Cache::forget("import_{$this->importId}_processed_count");
                Cache::forget("import_{$this->importId}_warnings");
                Cache::forget("import_{$this->importId}_first_row_snapshot");
            },
        ];
    }

    /**
     * Log an error message to the Laravel log and add to cache.
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge(['import_id' => $this->importId], $context));

        $errors = Cache::get("import_{$this->importId}_errors", []);
        $errors[] = $message;
        Cache::put("import_{$this->importId}_errors", $errors, 3600);
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
            app(NotificationService::class)->notifyUser(
                $user,
                new ImportFailedNotification($errorMessage, $errorDetails, $this->importId, 'import')
            );
        }
    }
}
