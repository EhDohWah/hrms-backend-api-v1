<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\{
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithCustomValueBinder,
    SkipsEmptyRows
};
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

class DevEmployeesImport extends DefaultValueBinder implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithCustomValueBinder,
    SkipsEmptyRows,
    SkipsOnFailure
{
    use Importable;

    /** @var array Rows successfully prepared for insert */
    protected $processedEmployees = [];

    /** @var array String errors you log (missing keys, parse errors…) */
    protected $errors = [];

    /** @var array Captured validation failures (row, attribute, errors, values) */
    protected $validationFailures = [];

    /** @var array Snapshot of the very first incoming row for debugging */
    protected $firstRowSnapshot = [];

    /**
     * Force every incoming cell to be treated as text.
     */
    public function bindValue(Cell $cell, $value)
    {
        // cast to string and bind explicitly as string
        $cell->setValueExplicit((string)$value, DataType::TYPE_STRING);
        return true;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->validationFailures[] = [
                'row'       => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors'    => $failure->errors(),
                'values'    => $failure->values(),
            ];
            $msg = "Row {$failure->row()} [{$failure->attribute()}]: "
                 . implode(', ', $failure->errors());
            Log::warning($msg, ['values' => $failure->values()]);
            $this->errors[] = $msg;
        }
    }

    /**
     * Process each chunk of rows.
     */
    public function collection(Collection $rows)
    {
        // 1) Normalize all the row values *before* you run the Validator
        $normalized = $rows->map(function($r) {
            // 1) Excel serial → Y‑m‑d
            if (!empty($r['date_of_birth']) && is_numeric($r['date_of_birth'])) {
                try {
                    $r['date_of_birth'] =
                        ExcelDate::excelToDateTimeObject($r['date_of_birth'])
                                ->format('Y-m-d');
                } catch (\Exception $e) {}
            }

            // 2) Normalize id_type
            $map = [
                '10 years ID' => '10YearsID',
                'Burmese ID'  => 'BurmeseID',
                'CI'          => 'CI',
                'Borderpass'  => 'Borderpass',
                'Thai ID'     => 'ThaiID',
                'Passport'    => 'Passport',
                'Other'       => 'Other',
            ];
            if (!empty($r['id_type'])) {
                $r['id_type'] = $map[$r['id_type']] ?? 'Other';
            }

            // 3) Cast Excel's =YEARFRAC(K2,TODAY()) result into an integer age
        $rawAge = trim((string)($r['age'] ?? ''));
        $r['age'] = is_numeric($rawAge)
            ? (int) floor($rawAge)
            : null;

        return $r;
        });


        // 3) now run your validator *against* $normalized
        $validator = Validator::make(
            $normalized->toArray(),
            $this->rules(),       // with age/id_type rules fixed
            $this->messages()     // optional custom messages
        );
        if ($validator->fails()) {
            // feed those failures into your onFailure()
            foreach ($validator->errors()->all() as $error) {
                $this->onFailure(new Failure(0, '', [$error], []));
            }
            return;
        }

        // Capture a snapshot of the detected columns & first row
        if (empty($this->firstRowSnapshot) && $rows->count() > 0) {
            $first = $rows->first()->toArray();
            $this->firstRowSnapshot = [
                'columns' => array_keys($first),
                'values'  => $first,
            ];
            Log::debug('First row snapshot for import debug', $this->firstRowSnapshot);
        }

        // Speed & memory tweaks
        DB::disableQueryLog();

        try {
            // Log the start of import process
            Log::info('Starting employee import process', ['rows_count' => $rows->count()]);

            DB::transaction(function() use ($normalized) {
                $employeeBatch = [];
                $identBatch = [];
                $beneBatch = [];
                $allStaffIds = [];

                // 1) Build the employee batch & collect staff_ids
                foreach ($normalized as $index => $row) {
                    if (!$row->filter()->count()) {
                        Log::info('Skipping blank row', ['row_index' => $index]);
                        continue; // skip blank rows
                    }

                    // Debug: Check for array values in any cell
                    foreach ($row as $key => $value) {
                        if (is_array($value)) {
                            $debugMessage = "Found array value in column: {$key}";
                            Log::debug($debugMessage, [
                                'value' => $value,
                                'row' => $row->toArray()
                            ]);
                            $this->errors[] = $debugMessage;
                        }
                    }

                    // check required staff_id key
                    if (!array_key_exists('staff_id', $row->toArray())) {
                        Log::warning('Missing staff_id in row', ['row_index' => $index, 'row_data' => $row->toArray()]);
                        $this->errors[] = "Row {$index}: Missing column key 'staff_id'";
                        continue;
                    }

                    $staffId = trim($row['staff_id']);
                    $allStaffIds[] = $staffId;

                    // Debug date parsing
                    $dateOfBirth = null;
                    try {
                        if (isset($row['date_of_birth']) && !empty($row['date_of_birth'])) {
                            $dateOfBirth = now()->parse($row['date_of_birth'])->format('Y-m-d');
                            Log::debug("Parsed date of birth", [
                                'original' => $row['date_of_birth'],
                                'parsed' => $dateOfBirth
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to parse date of birth", [
                            'staff_id' => $staffId,
                            'value' => $row['date_of_birth'] ?? 'null',
                            'error' => $e->getMessage()
                        ]);
                        $dateOfBirth = null;
                    }

                    // debug date of birth thai (Buddhist calendar)
                    $raw = trim((string)($row['date_of_birth_th'] ?? ''));

                    // default to null
                    $dateOfBirthTh = null;

                    // only parse when there's something other than empty or "-"
                    if ($raw !== '' && $raw !== '-') {
                        if (ctype_digit($raw)) {
                            // Excel serial number
                            $dt = ExcelDate::excelToDateTimeObject($raw);
                        } else {
                            // split into [day,month,year] or [month,year]
                            $parts = explode('/', $raw);
                            $day   = count($parts) === 3 ? intval($parts[0]) : 1;
                            $month = count($parts) === 3 ? intval($parts[1]) : intval($parts[0]);
                            $year  = intval($parts[count($parts) - 1]) - 543;  // Buddhist→Gregorian

                            $dt = Carbon::create($year, $month, $day);
                        }

                        $dateOfBirthTh = $dt->format('Y-m-d');
                    }



                    $employeeBatch[] = [
                        'staff_id'                  => $staffId,
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
                        'created_at'                => now(),
                        'updated_at'                => now(),
                    ];
                }

                // 2) Bulk‐insert employees
                if (count($employeeBatch)) {
                    Log::info('Inserting employee batch', ['count' => count($employeeBatch)]);
                    try {
                        Employee::insert($employeeBatch);
                        $this->processedEmployees = array_merge(
                            $this->processedEmployees,
                            $employeeBatch
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to insert employee batch', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        throw $e;
                    }
                } else {
                    Log::warning('No employees to insert');
                    $this->errors[] = "No valid employee records found in the import file";
                }

                // 3) Fetch back the new employee IDs by staff_id
                $employeeMap = Employee::whereIn('staff_id', $allStaffIds)
                    ->pluck('id', 'staff_id')
                    ->toArray();

                Log::info('Retrieved employee IDs', ['count' => count($employeeMap)]);

                // 4) Build identifications & beneficiaries batches
                foreach ($normalized as $index => $row) {
                    if (!isset($row['staff_id'])) {
                        continue;
                    }

                    $staffId = trim($row['staff_id']);
                    if (!isset($employeeMap[$staffId])) {
                        $this->errors[] = "Staff ID {$staffId} not found in employee map after insert";
                        Log::warning("Staff ID not found in employee map", ['staff_id' => $staffId]);
                        continue;
                    }
                    $empId = $employeeMap[$staffId];

                    // Identification (columns: ID type / ID no)
                    if (!empty($row['id_type']) && !empty($row['id_no'])) {
                        $identBatch[] = [
                            'employee_id'     => $empId,
                            'id_type'         => $row['id_type'],
                            'document_number' => $row['id_no'],
                            'issue_date'      => null,
                            'expiry_date'     => null,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                    }

                    // Beneficiary 1 (columns: kin1_name, kin1_relationship, kin1_mobile)
                    if (!empty($row['kin1_name'])) {
                        $beneBatch[] = [
                            'employee_id'                => $empId,
                            'beneficiary_name'           => $row['kin1_name'],
                            'beneficiary_relationship'   => $row['kin1_relationship'] ?? null,
                            'phone_number'               => $row['kin1_mobile'] ?? null,
                            'created_at'                 => now(),
                            'updated_at'                 => now(),
                        ];
                    }
                    // Beneficiary 2 (kin2_name…)
                    if (!empty($row['kin2_name'])) {
                        $beneBatch[] = [
                            'employee_id'                => $empId,
                            'beneficiary_name'           => $row['kin2_name'],
                            'beneficiary_relationship'   => $row['kin2_relationship'] ?? null,
                            'phone_number'               => $row['kin2_mobile'] ?? null,
                            'created_at'                 => now(),
                            'updated_at'                 => now(),
                        ];
                    }
                }

                // 5) Bulk‐insert identifications & beneficiaries
                if (count($identBatch)) {
                    Log::info('Inserting identification batch', ['count' => count($identBatch)]);
                    try {
                        DB::table('employee_identifications')->insert($identBatch);
                    } catch (\Exception $e) {
                        Log::error('Failed to insert identifications', ['error' => $e->getMessage()]);
                        $this->errors[] = "Failed to insert identifications: " . $e->getMessage();
                    }
                }

                if (count($beneBatch)) {
                    Log::info('Inserting beneficiary batch', ['count' => count($beneBatch)]);
                    try {
                        DB::table('employee_beneficiaries')->insert($beneBatch);
                    } catch (\Exception $e) {
                        Log::error('Failed to insert beneficiaries', ['error' => $e->getMessage()]);
                        $this->errors[] = "Failed to insert beneficiaries: " . $e->getMessage();
                    }
                }

                Log::info('Employee import completed successfully', [
                    'employees_processed' => count($this->processedEmployees),
                    'identifications_added' => count($identBatch),
                    'beneficiaries_added' => count($beneBatch)
                ]);
            });
        } catch (\Exception $e) {
            $errorMessage = 'Error in ' . __METHOD__ . ' at line ' . $e->getLine() . ': ' . $e->getMessage();
            $this->errors[] = $errorMessage;
            Log::error('Employee import failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Row‐level validation rules.
     */
    public function rules(): array
    {
        return [
            '*.org'           => 'nullable|string|max:5',
            '*.staff_id'      => 'required|string|unique:employees,staff_id',
            '*.initial'       => 'nullable|string|max:5',
            '*.first_name'    => 'required|string|max:255',
            '*.last_name'     => 'nullable|string|max:255',
            '*.initial_th'    => 'nullable|string|max:5',
            '*.first_name_th' => 'nullable|string|max:255',
            '*.last_name_th'  => 'nullable|string|max:255',
            '*.gender'        => 'required|string|in:M,F',
            '*.date_of_birth' => 'required|date',
            '*.date_of_birth_th' => 'nullable|string|max:10',
            '*.status'        => 'nullable|string|max:20',
            '*.nationality'   => 'nullable|string|max:100',
            '*.religion'      => 'nullable|string|max:100',
            '*.id_type'       => ['nullable', Rule::in(['ThaiID','10YearsID','Passport','CI','Borderpass','BurmeseID','Other'])],
            '*.id_no'         => 'nullable|string',
            '*.social_security_no' => 'nullable|string|max:50',
            '*.tax_no'        => 'nullable|string|max:50',
            '*.driver_license' => 'nullable|string|max:100',
            '*.bank_name'     => 'nullable|string|max:100',
            '*.bank_branch'   => 'nullable|string|max:100',
            '*.bank_acc_name'  => 'nullable|string|max:100',
            '*.bank_acc_no'    => 'nullable|string|max:50',
            '*.mobile_no'     => 'nullable|string|max:10',
            '*.current_address' => 'nullable|string',
            '*.permanent_address' => 'nullable|string',
            '*.marital_status' => 'nullable|string|max:50',
            '*.spouse_name'   => 'nullable|string|max:200',
            '*.spouse_mobile_no' => 'nullable|string|max:10',
            '*.emergency_name' => 'nullable|string|max:100',
            '*.relationship'  => 'nullable|string|max:100',
            '*.emergency_mobile_no' => 'nullable|string|max:10',
            '*.father_name'   => 'nullable|string|max:200',
            '*.father_occupation' => 'nullable|string|max:200',
            '*.father_mobile_no' => 'nullable|string|max:10',
            '*.mother_name'   => 'nullable|string|max:200',
            '*.mother_occupation' => 'nullable|string|max:200',
            '*.mother_mobile_no' => 'nullable|string|max:10',
            '*.kin1_name'     => 'nullable|string|max:255',
            '*.kin1_relationship' => 'nullable|string|max:255',
            '*.kin1_mobile'   => 'nullable|string|max:10',
            '*.kin2_name'     => 'nullable|string|max:255',
            '*.kin2_relationship' => 'nullable|string|max:255',
            '*.kin2_mobile'   => 'nullable|string|max:10',
            '*.military_status' => 'nullable|string|max:50',
            '*.remark'        => 'nullable|string|max:255',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Chunk size: adjust to your memory/server
     */
    public function chunkSize(): int
    {
        return 50;
    }

    /**
     * Return the batch of successfully inserted employees.
     */
    public function getProcessedEmployees(): array
    {
        return $this->processedEmployees;
    }

    /**
     * Return any custom‐logged errors (exceptions, date‐parse, etc).
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Expose the validation failures list as simple arrays.
     */
    public function getValidationFailures(): array
    {
        return $this->validationFailures;
    }

    /**
     * Return the first row snapshot for debugging.
     */
    public function getFirstRowSnapshot(): array
    {
        return $this->firstRowSnapshot;
    }
}