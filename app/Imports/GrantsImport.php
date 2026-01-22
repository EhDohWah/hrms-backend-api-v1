<?php

namespace App\Imports;

use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\User;
use App\Notifications\ImportedCompletedNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GrantsImport implements WithMultipleSheets
{
    use Importable;

    public $userId;

    public $importId;

    protected $processedGrants = 0;

    protected $processedItems = 0;

    protected $errors = [];

    protected $skippedGrants = [];

    protected $warnings = [];

    /**
     * Valid organization values
     */
    public const VALID_ORGANIZATIONS = ['SMRU', 'BHF'];

    /**
     * Validation constraints
     */
    public const GRANT_NAME_MIN_LENGTH = 3;

    public const GRANT_NAME_MAX_LENGTH = 255;

    public const GRANT_CODE_MAX_LENGTH = 50;

    public const DESCRIPTION_MAX_LENGTH = 1000;

    public const BUDGETLINE_CODE_MAX_LENGTH = 50;

    public const POSITION_MIN_LENGTH = 2;

    public const POSITION_MAX_LENGTH = 255;

    public const SALARY_MIN = 0;

    public const SALARY_MAX = 99999999.99;

    public const BENEFIT_MIN = 0;

    public const BENEFIT_MAX = 99999999.99;

    public const LOE_MIN = 0;

    public const LOE_MAX = 100;

    public const POSITION_NUMBER_MIN = 1;

    public const POSITION_NUMBER_MAX = 1000;

    /**
     * Template row configuration
     * Using new structure: Column A = Labels, Column B = Values
     */
    public const HEADER_ROW_GRANT_NAME = 1;

    public const HEADER_ROW_GRANT_CODE = 2;

    public const HEADER_ROW_ORGANIZATION = 3;

    public const HEADER_ROW_END_DATE = 4;

    public const HEADER_ROW_DESCRIPTION = 5;

    public const SPACER_ROW = 6;

    public const COLUMN_HEADER_ROW = 7;

    public const VALIDATION_RULES_ROW = 8;

    public const DATA_START_ROW = 9;

    public const MINIMUM_ROWS = 8;

    public function __construct(string $importId, int $userId)
    {
        $this->importId = $importId;
        $this->userId = $userId;
    }

    /**
     * Return an array of sheet imports
     * Each sheet will be processed by GrantSheetImport
     */
    public function sheets(): array
    {
        return [
            new GrantSheetImport($this),
        ];
    }

    /**
     * This method will be called for each sheet that doesn't have a specific import
     */
    public function conditionalSheets(): array
    {
        return [];
    }

    public function addProcessedGrant()
    {
        $this->processedGrants++;
    }

    public function addProcessedItems(int $count)
    {
        $this->processedItems += $count;
    }

    public function addError(string $error)
    {
        $this->errors[] = $error;
    }

    public function addWarning(string $warning)
    {
        $this->warnings[] = $warning;
        Log::warning($warning, ['import_id' => $this->importId]);
    }

    public function addSkippedGrant(string $grantCode)
    {
        $this->skippedGrants[] = $grantCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getProcessedGrants(): int
    {
        return $this->processedGrants;
    }

    public function getProcessedItems(): int
    {
        return $this->processedItems;
    }

    public function getSkippedGrants(): array
    {
        return $this->skippedGrants;
    }

    /**
     * Send completion notification
     */
    public function sendCompletionNotification()
    {
        $message = "Grant import finished! Processed: {$this->processedGrants} grants, {$this->processedItems} grant items";
        if (count($this->errors) > 0) {
            $message .= ', Errors: '.count($this->errors);
        }
        if (count($this->skippedGrants) > 0) {
            $message .= ', Skipped: '.count($this->skippedGrants);
        }

        $user = User::find($this->userId);
        if ($user) {
            app(NotificationService::class)->notifyUser(
                $user,
                new ImportedCompletedNotification($message, 'import')
            );
        }
    }
}

/**
 * Import handler for individual grant sheets
 */
class GrantSheetImport
{
    protected $parentImport;

    public function __construct(GrantsImport $parentImport)
    {
        $this->parentImport = $parentImport;
    }

    public function collection($collection)
    {
        // This method won't be called when using raw sheet processing
    }

    /**
     * Process the sheet using PHPSpreadsheet directly
     * Wrapped in database transaction for data integrity
     */
    public function processSheet($sheet)
    {
        $sheetName = $sheet->getTitle();

        Log::info("Processing sheet: {$sheetName}", ['import_id' => $this->parentImport->importId]);

        try {
            $data = $sheet->toArray(null, true, true, true);

            // Use database transaction for atomic operation
            DB::transaction(function () use ($data, $sheetName) {
                // Step 1: Validate sheet structure
                $structureValidation = $this->validateSheetStructure($data, $sheetName);
                if (! $structureValidation['valid']) {
                    foreach ($structureValidation['errors'] as $error) {
                        $this->parentImport->addError($error);
                    }
                    throw new \Exception('Sheet structure validation failed');
                }

                // Step 2: Extract and validate grant header
                $headerData = $this->extractGrantHeader($data);
                $headerValidation = $this->validateGrantHeader($headerData, $sheetName);

                if (! $headerValidation['valid']) {
                    foreach ($headerValidation['errors'] as $error) {
                        $this->parentImport->addError($error);
                    }
                    throw new \Exception('Grant header validation failed');
                }

                // Step 3: Check if grant already exists
                $existingGrant = Grant::where('code', $headerValidation['grant_code'])->first();
                if ($existingGrant) {
                    $this->parentImport->addError("Sheet '{$sheetName}': Grant '{$headerValidation['grant_code']}' already exists - items skipped");
                    $this->parentImport->addSkippedGrant($headerValidation['grant_code']);
                    throw new \Exception('Grant already exists');
                }

                // Step 4: Create the grant
                $grant = $this->createGrant($headerValidation, $sheetName);
                if (! $grant) {
                    throw new \Exception('Failed to create grant');
                }

                // Step 5: Process grant items
                $itemsProcessed = $this->processGrantItems($data, $grant, $sheetName);

                if ($itemsProcessed > 0) {
                    $this->parentImport->addProcessedGrant();
                    $this->parentImport->addProcessedItems($itemsProcessed);
                    Log::info("Sheet '{$sheetName}': Inserted {$itemsProcessed} items for grant '{$grant->code}'", [
                        'import_id' => $this->parentImport->importId,
                        'grant_id' => $grant->id,
                    ]);
                }
            });

        } catch (\Exception $e) {
            // Transaction automatically rolled back
            Log::error('Grant sheet import failed', [
                'sheet' => $sheetName,
                'error' => $e->getMessage(),
                'import_id' => $this->parentImport->importId,
            ]);

            // Only add generic error if it's not a validation-related exception
            if (! in_array($e->getMessage(), [
                'Sheet structure validation failed',
                'Grant header validation failed',
                'Grant already exists',
                'Failed to create grant',
                'Grant items validation failed - no items created',
            ])) {
                $this->parentImport->addError("Sheet '{$sheetName}': Error processing sheet - ".$e->getMessage());
            }
        }
    }

    /**
     * Validate sheet has required structure
     *
     * @return array ['valid' => bool, 'errors' => array]
     */
    protected function validateSheetStructure(array $data, string $sheetName): array
    {
        $errors = [];

        // Check minimum rows
        if (count($data) < GrantsImport::MINIMUM_ROWS) {
            $errors[] = "Sheet '{$sheetName}': Insufficient data rows (minimum ".GrantsImport::MINIMUM_ROWS.' required, found '.count($data).')';
        }

        // Check column headers in row 7
        if (isset($data[GrantsImport::COLUMN_HEADER_ROW])) {
            $headerRow = $data[GrantsImport::COLUMN_HEADER_ROW];
            $requiredColumns = [
                'A' => 'Budget Line Code',
                'B' => 'Position',
                'C' => 'Salary',
                'D' => 'Benefit',
                'E' => 'Level of Effort',
                'F' => 'Position Number',
            ];

            foreach ($requiredColumns as $col => $expectedName) {
                $actualValue = trim($headerRow[$col] ?? '');
                // Allow flexible matching - just check if the column exists and has some value
                // This prevents strict matching issues with slight variations
                if (empty($actualValue) && $col !== 'A') {
                    // Column A can be empty for budget line code header
                    $errors[] = "Sheet '{$sheetName}': Missing column header '{$expectedName}' in row ".GrantsImport::COLUMN_HEADER_ROW." column {$col}";
                }
            }
        }

        // Check grant header rows exist (rows 1-5)
        for ($row = 1; $row <= 5; $row++) {
            if (! isset($data[$row])) {
                $errors[] = "Sheet '{$sheetName}': Missing grant header row {$row}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Extract grant header values from cells B1-B5
     * Template structure: Column A = Labels, Column B = Values
     */
    protected function extractGrantHeader(array $data): array
    {
        return [
            'grant_name' => trim($data[GrantsImport::HEADER_ROW_GRANT_NAME]['B'] ?? ''),
            'grant_code' => trim($data[GrantsImport::HEADER_ROW_GRANT_CODE]['B'] ?? ''),
            'organization' => trim($data[GrantsImport::HEADER_ROW_ORGANIZATION]['B'] ?? ''),
            'end_date_raw' => $data[GrantsImport::HEADER_ROW_END_DATE]['B'] ?? null,
            'description' => trim($data[GrantsImport::HEADER_ROW_DESCRIPTION]['B'] ?? ''),
        ];
    }

    /**
     * Validate all grant header fields
     *
     * @return array ['valid' => bool, 'errors' => array, 'grant_name' => string, ...]
     */
    protected function validateGrantHeader(array $headerData, string $sheetName): array
    {
        $errors = [];
        $result = [
            'valid' => false,
            'errors' => [],
        ];

        // Validate Grant Name (REQUIRED)
        $grantName = $headerData['grant_name'];
        if (empty($grantName)) {
            $errors[] = "Sheet '{$sheetName}': Grant name is required (Cell B1)";
        } elseif (mb_strlen($grantName) < GrantsImport::GRANT_NAME_MIN_LENGTH) {
            $errors[] = "Sheet '{$sheetName}': Grant name must be at least ".GrantsImport::GRANT_NAME_MIN_LENGTH.' characters (Cell B1)';
        } elseif (mb_strlen($grantName) > GrantsImport::GRANT_NAME_MAX_LENGTH) {
            $errors[] = "Sheet '{$sheetName}': Grant name exceeds ".GrantsImport::GRANT_NAME_MAX_LENGTH.' characters (Cell B1)';
        }

        // Validate Grant Code (REQUIRED)
        $grantCode = $headerData['grant_code'];
        if (empty($grantCode)) {
            $errors[] = "Sheet '{$sheetName}': Grant code is required (Cell B2)";
        } elseif (mb_strlen($grantCode) > GrantsImport::GRANT_CODE_MAX_LENGTH) {
            $errors[] = "Sheet '{$sheetName}': Grant code exceeds ".GrantsImport::GRANT_CODE_MAX_LENGTH.' characters (Cell B2)';
        } elseif (! preg_match('/^[a-zA-Z0-9._-]+$/', $grantCode)) {
            $errors[] = "Sheet '{$sheetName}': Grant code contains invalid characters. Only alphanumeric, dot, dash, and underscore allowed (Cell B2)";
        }

        // Validate Organization (REQUIRED with fuzzy matching)
        $organizationValidation = $this->validateOrganization($headerData['organization'], $sheetName);
        if (! $organizationValidation['valid']) {
            $errors[] = $organizationValidation['error'];
        }

        // Validate End Date (OPTIONAL)
        $endDate = null;
        $endDateRaw = $headerData['end_date_raw'];
        if (! empty($endDateRaw)) {
            $endDateValidation = $this->validateEndDate($endDateRaw, $sheetName);
            if (! $endDateValidation['valid']) {
                $errors[] = $endDateValidation['error'];
            } else {
                $endDate = $endDateValidation['date'];
                // Add warnings for edge cases
                if (isset($endDateValidation['warning'])) {
                    $this->parentImport->addWarning($endDateValidation['warning']);
                }
            }
        }

        // Validate Description (OPTIONAL)
        $description = $headerData['description'];
        if (! empty($description) && mb_strlen($description) > GrantsImport::DESCRIPTION_MAX_LENGTH) {
            $errors[] = "Sheet '{$sheetName}': Description exceeds ".GrantsImport::DESCRIPTION_MAX_LENGTH.' characters (Cell B5)';
        }

        if (empty($errors)) {
            $result = [
                'valid' => true,
                'errors' => [],
                'grant_name' => $grantName,
                'grant_code' => $grantCode,
                'organization' => $organizationValidation['organization'],
                'end_date' => $endDate,
                'description' => $description ?: null,
            ];
        } else {
            $result = [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        return $result;
    }

    /**
     * Validate organization with Levenshtein distance for typo detection
     *
     * @return array ['valid' => bool, 'organization' => string|null, 'error' => string|null]
     */
    protected function validateOrganization(string $organization, string $sheetName): array
    {
        // Check if empty
        if (empty($organization)) {
            return [
                'valid' => false,
                'organization' => null,
                'error' => "Sheet '{$sheetName}': Organization/Subsidiary is required (Cell B3)",
            ];
        }

        // Normalize to uppercase
        $normalizedOrg = strtoupper(trim($organization));

        // Check for exact match
        if (in_array($normalizedOrg, GrantsImport::VALID_ORGANIZATIONS)) {
            return [
                'valid' => true,
                'organization' => $normalizedOrg,
                'error' => null,
            ];
        }

        // Use Levenshtein distance to find closest match
        $closestMatch = null;
        $minDistance = PHP_INT_MAX;

        foreach (GrantsImport::VALID_ORGANIZATIONS as $validOrg) {
            $distance = levenshtein($normalizedOrg, $validOrg);
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
                'error' => "Sheet '{$sheetName}': Invalid organization: '{$organization}'. Did you mean '{$closestMatch}'? (Cell B3)",
            ];
        }

        // Completely invalid value
        $validOptions = implode(', ', GrantsImport::VALID_ORGANIZATIONS);

        return [
            'valid' => false,
            'organization' => null,
            'error' => "Sheet '{$sheetName}': Invalid organization: '{$organization}'. Must be one of: {$validOptions} (Cell B3)",
        ];
    }

    /**
     * Validate end date format and value
     *
     * @param  mixed  $endDateRaw
     * @return array ['valid' => bool, 'date' => string|null, 'error' => string|null, 'warning' => string|null]
     */
    protected function validateEndDate($endDateRaw, string $sheetName): array
    {
        $result = [
            'valid' => false,
            'date' => null,
            'error' => null,
            'warning' => null,
        ];

        if (empty($endDateRaw)) {
            $result['valid'] = true;

            return $result;
        }

        try {
            // Handle Excel numeric date format
            if (is_numeric($endDateRaw)) {
                $date = Carbon::createFromTimestamp(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($endDateRaw));
            } else {
                // Try to parse string date
                $date = Carbon::parse(trim($endDateRaw));
            }

            $result['valid'] = true;
            $result['date'] = $date->format('Y-m-d');

            // Check if date is in the past (warning only)
            if ($date->isPast()) {
                $result['warning'] = "Sheet '{$sheetName}': End date is in the past: {$result['date']} (Cell B4)";
            }

            // Check if date is more than 10 years in the future (warning only)
            if ($date->diffInYears(Carbon::now()) > 10) {
                $result['warning'] = "Sheet '{$sheetName}': End date is more than 10 years in the future (Cell B4)";
            }

        } catch (\Exception $e) {
            $displayValue = is_scalar($endDateRaw) ? $endDateRaw : 'invalid';
            $result['error'] = "Sheet '{$sheetName}': Invalid end date format: '{$displayValue}'. Expected: YYYY-MM-DD (Cell B4)";
        }

        return $result;
    }

    /**
     * Create grant from validated data
     */
    protected function createGrant(array $validatedData, string $sheetName): ?Grant
    {
        try {
            $grant = Grant::create([
                'code' => $validatedData['grant_code'],
                'name' => $validatedData['grant_name'],
                'organization' => $validatedData['organization'],
                'end_date' => $validatedData['end_date'],
                'description' => $validatedData['description'],
                'created_by' => auth()->user()->name ?? 'system',
                'updated_by' => auth()->user()->name ?? 'system',
            ]);

            Log::info("Created grant: {$grant->code}", [
                'import_id' => $this->parentImport->importId,
                'grant_id' => $grant->id,
                'sheet' => $sheetName,
            ]);

            return $grant;
        } catch (\Exception $e) {
            $this->parentImport->addError("Sheet '{$sheetName}': Error creating grant - ".$e->getMessage());
            Log::error('Failed to create grant', [
                'sheet' => $sheetName,
                'error' => $e->getMessage(),
                'import_id' => $this->parentImport->importId,
            ]);

            return null;
        }
    }

    /**
     * Process grant items from Excel data with validation
     * Uses two-pass approach: validate all first, then create if no errors
     */
    protected function processGrantItems(array $data, Grant $grant, string $sheetName): int
    {
        $validatedItems = [];
        $errors = [];
        $warnings = [];
        $validatedItemKeys = [];

        // PASS 1: Validate ALL rows first (collect all errors before creating anything)
        for ($rowNum = GrantsImport::DATA_START_ROW; $rowNum <= count($data); $rowNum++) {
            if (! isset($data[$rowNum])) {
                continue;
            }

            $row = $data[$rowNum];

            // Validate the row
            $validation = $this->validateGrantItemRow($row, $grant, $sheetName, $rowNum, $validatedItemKeys);

            if ($validation === null) {
                // Skip empty rows
                continue;
            }

            if (isset($validation['error'])) {
                $errors[] = $validation['error'];

                continue;
            }

            // Collect warnings
            if (isset($validation['warning'])) {
                $warnings[] = $validation['warning'];
            }

            // Track validated item key for duplicate detection within this import
            $validatedItemKeys[$validation['item_key']] = true;

            // Store validated item for creation
            $validatedItems[] = [
                'rowNum' => $rowNum,
                'data' => $validation,
            ];
        }

        // If ANY validation errors exist, add them all and throw exception to rollback
        if (! empty($errors)) {
            foreach ($errors as $error) {
                $this->parentImport->addError($error);
            }
            throw new \Exception('Grant items validation failed - no items created');
        }

        // PASS 2: Create all validated items (only reached if no validation errors)
        $itemsProcessed = 0;

        foreach ($validatedItems as $item) {
            $validation = $item['data'];
            $rowNum = $item['rowNum'];

            try {
                GrantItem::create([
                    'grant_id' => $grant->id,
                    'grant_position' => $validation['grant_position'],
                    'budgetline_code' => $validation['budgetline_code'],
                    'grant_salary' => $validation['grant_salary'],
                    'grant_benefit' => $validation['grant_benefit'],
                    'grant_level_of_effort' => $validation['grant_level_of_effort'],
                    'grant_position_number' => $validation['grant_position_number'],
                    'created_by' => auth()->user()->name ?? 'system',
                    'updated_by' => auth()->user()->name ?? 'system',
                ]);

                $itemsProcessed++;
            } catch (\Exception $e) {
                $this->parentImport->addError("Sheet '{$sheetName}' Row {$rowNum}: Error creating grant item - ".$e->getMessage());
                throw $e; // Re-throw to rollback transaction
            }
        }

        // Add warnings after successful creation
        foreach ($warnings as $warning) {
            $this->parentImport->addWarning($warning);
        }

        return $itemsProcessed;
    }

    /**
     * Validate a single grant item row
     *
     * @return array|null Returns null to skip row, array with 'error' key for errors, or validated data
     */
    protected function validateGrantItemRow(array $row, Grant $grant, string $sheetName, int $rowNum, array $createdItemKeys): ?array
    {
        // Column B: Position (REQUIRED)
        $position = trim($row['B'] ?? '');

        // Skip empty rows
        if (empty($position)) {
            return null;
        }

        // Validate position length
        if (mb_strlen($position) < GrantsImport::POSITION_MIN_LENGTH) {
            return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Grant position must be at least ".GrantsImport::POSITION_MIN_LENGTH.' characters'];
        }
        if (mb_strlen($position) > GrantsImport::POSITION_MAX_LENGTH) {
            return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Grant position exceeds ".GrantsImport::POSITION_MAX_LENGTH.' characters'];
        }

        // Column A: Budget Line Code (OPTIONAL)
        $budgetLineCode = trim($row['A'] ?? '');
        $budgetLineCode = $budgetLineCode !== '' ? $budgetLineCode : null;

        if ($budgetLineCode !== null && mb_strlen($budgetLineCode) > GrantsImport::BUDGETLINE_CODE_MAX_LENGTH) {
            return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Budget line code exceeds ".GrantsImport::BUDGETLINE_CODE_MAX_LENGTH.' characters'];
        }

        // Column C: Salary (OPTIONAL)
        $salary = null;
        $warning = null;
        if (isset($row['C']) && $row['C'] !== '') {
            $salaryValue = $this->toFloat($row['C']);
            if ($salaryValue === null && $row['C'] !== '') {
                return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Invalid grant salary format: '{$row['C']}'"];
            }
            if ($salaryValue !== null) {
                if ($salaryValue < GrantsImport::SALARY_MIN) {
                    return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Grant salary cannot be negative"];
                }
                if ($salaryValue > GrantsImport::SALARY_MAX) {
                    return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Grant salary exceeds maximum value (".number_format(GrantsImport::SALARY_MAX, 2).')'];
                }
                if ($salaryValue == 0) {
                    $warning = "Sheet '{$sheetName}' Row {$rowNum}: Grant salary is zero";
                }
                $salary = $salaryValue;
            }
        }

        // Column D: Benefit (OPTIONAL)
        if (isset($row['D']) && $row['D'] !== '') {
            $benefitValue = $this->toFloat($row['D']);
            if ($benefitValue === null && $row['D'] !== '') {
                return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Invalid grant benefit format: '{$row['D']}'"];
            }
            if ($benefitValue !== null) {
                if ($benefitValue < GrantsImport::BENEFIT_MIN) {
                    return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Grant benefit cannot be negative"];
                }
                if ($benefitValue > GrantsImport::BENEFIT_MAX) {
                    return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Grant benefit exceeds maximum value (".number_format(GrantsImport::BENEFIT_MAX, 2).')'];
                }
                if ($benefitValue == 0 && $warning === null) {
                    $warning = "Sheet '{$sheetName}' Row {$rowNum}: Grant benefit is zero";
                }
                $benefit = $benefitValue;
            }
        }
        $benefit = $benefit ?? null;

        // Column E: Level of Effort (OPTIONAL)
        $levelOfEffort = null;
        if (isset($row['E']) && $row['E'] !== '') {
            $loeValidation = $this->validateLevelOfEffort($row['E'], $sheetName, $rowNum);
            if (isset($loeValidation['error'])) {
                return ['error' => $loeValidation['error']];
            }
            $levelOfEffort = $loeValidation['value'];
            if ($levelOfEffort == 0 && $warning === null) {
                $warning = "Sheet '{$sheetName}' Row {$rowNum}: Level of effort is zero";
            }
        }

        // Column F: Position Number (OPTIONAL, default 1)
        $positionNumber = 1;
        if (isset($row['F']) && $row['F'] !== '') {
            $posNumValue = $row['F'];
            if (! is_numeric($posNumValue) || floor($posNumValue) != $posNumValue) {
                return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Position number must be an integer: '{$posNumValue}'"];
            }
            $positionNumber = (int) $posNumValue;
            if ($positionNumber < GrantsImport::POSITION_NUMBER_MIN) {
                return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Position number must be at least ".GrantsImport::POSITION_NUMBER_MIN];
            }
            if ($positionNumber > GrantsImport::POSITION_NUMBER_MAX) {
                return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Position number exceeds maximum ".GrantsImport::POSITION_NUMBER_MAX];
            }
        }

        // Duplicate detection (only for non-null budget line codes)
        $itemKey = $grant->id.'|'.$position.'|'.($budgetLineCode ?? 'NULL_'.uniqid());

        if ($budgetLineCode !== null) {
            // Check in already-created items this import
            $duplicateKey = $grant->id.'|'.$position.'|'.$budgetLineCode;
            if (isset($createdItemKeys[$duplicateKey])) {
                return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Duplicate - Position '{$position}' with budget line '{$budgetLineCode}' already exists"];
            }

            // Check in database
            $existingItem = GrantItem::where('grant_id', $grant->id)
                ->where('grant_position', $position)
                ->where('budgetline_code', $budgetLineCode)
                ->first();

            if ($existingItem) {
                return ['error' => "Sheet '{$sheetName}' Row {$rowNum}: Duplicate - Position '{$position}' with budget line '{$budgetLineCode}' already exists"];
            }

            $itemKey = $duplicateKey;
        }

        return [
            'grant_position' => $position,
            'budgetline_code' => $budgetLineCode,
            'grant_salary' => $salary,
            'grant_benefit' => $benefit,
            'grant_level_of_effort' => $levelOfEffort,
            'grant_position_number' => $positionNumber,
            'item_key' => $itemKey,
            'warning' => $warning,
        ];
    }

    /**
     * Validate level of effort value
     * Accepts: "75", "75%", "0.75" (all meaning 75%)
     *
     * @return array ['value' => float|null, 'error' => string|null]
     */
    protected function validateLevelOfEffort($value, string $sheetName, int $rowNum): array
    {
        $result = ['value' => null, 'error' => null];

        if (empty($value)) {
            return $result;
        }

        // Convert to string and clean
        $strValue = trim((string) $value);

        // Remove % symbol
        $strValue = str_replace('%', '', $strValue);

        // Check if numeric
        if (! is_numeric($strValue)) {
            $result['error'] = "Sheet '{$sheetName}' Row {$rowNum}: Invalid level of effort format: '{$value}'. Must be a number";

            return $result;
        }

        $numValue = (float) $strValue;

        // If value is between 0 and 1, it's already in decimal form (e.g., 0.75 = 75%)
        // If value is between 0 and 100, it's in percentage form
        if ($numValue > 1 && $numValue <= 100) {
            // Convert percentage to decimal
            $decimalValue = $numValue / 100;
        } elseif ($numValue >= 0 && $numValue <= 1) {
            $decimalValue = $numValue;
        } else {
            if ($numValue < 0) {
                $result['error'] = "Sheet '{$sheetName}' Row {$rowNum}: Level of effort cannot be negative";

                return $result;
            }
            if ($numValue > 100) {
                $result['error'] = "Sheet '{$sheetName}' Row {$rowNum}: Level of effort cannot exceed 100%: {$numValue}%";

                return $result;
            }
            $decimalValue = $numValue / 100;
        }

        $result['value'] = $decimalValue;

        return $result;
    }

    /**
     * Convert string value to float with improved handling
     *
     * @param  mixed  $value
     */
    protected function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Remove currency symbols, commas, spaces
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        // Validate cleaned value is numeric
        if (! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }
}
