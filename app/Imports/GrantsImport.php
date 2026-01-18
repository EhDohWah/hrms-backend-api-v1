<?php

namespace App\Imports;

use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\User;
use App\Notifications\ImportedCompletedNotification;
use App\Services\NotificationService;
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
        // We don't know sheet names in advance, so we'll use a conditional import
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

    public function addSkippedGrant(string $grantCode)
    {
        $this->skippedGrants[] = $grantCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
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
     */
    public function processSheet($sheet)
    {
        try {
            $data = $sheet->toArray(null, true, true, true);
            $sheetName = $sheet->getTitle();

            Log::info("Processing sheet: {$sheetName}", ['import_id' => $this->parentImport->importId]);

            // Validate minimum rows
            if (count($data) < 5) {
                $this->parentImport->addError("Sheet '{$sheetName}' skipped: Insufficient data rows (minimum 5 required)");

                return;
            }

            // Process grant header (rows 1-6)
            $grant = $this->createGrant($data, $sheetName);

            if (! $grant) {
                return; // Error already recorded
            }

            // Check if grant already exists and wasn't just created
            if (! $grant->wasRecentlyCreated) {
                $this->parentImport->addError("Sheet '{$sheetName}': Grant '{$grant->code}' already exists - items skipped");
                $this->parentImport->addSkippedGrant($grant->code);

                return;
            }

            // Process grant items (starting from row 8)
            try {
                $itemsProcessed = $this->processGrantItems($data, $grant, $sheetName);
                if ($itemsProcessed > 0) {
                    $this->parentImport->addProcessedGrant();
                    $this->parentImport->addProcessedItems($itemsProcessed);
                }
            } catch (\Exception $e) {
                $this->parentImport->addError("Sheet '{$sheetName}': Error processing items - ".$e->getMessage());
            }

        } catch (\Exception $e) {
            $this->parentImport->addError("Sheet '{$sheetName}': Error processing sheet - ".$e->getMessage());
            Log::error('Grant sheet import failed', [
                'sheet' => $sheetName ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create grant from sheet header rows
     */
    private function createGrant(array $data, string $sheetName): ?Grant
    {
        try {
            // Extract grant information from header rows
            // Row 1: Grant name
            // Row 2: Grant code
            // Row 3: Organization (Subsidiary)
            // Row 4: End date (optional)
            // Row 5: Description (optional)

            $grantName = trim(str_replace('Grant name -', '', $data[1]['A'] ?? ''));
            $grantCode = trim(str_replace('Grant code -', '', $data[2]['A'] ?? ''));
            $organization = trim(str_replace('Subsidiary -', '', $data[3]['A'] ?? ''));
            $endDate = null;

            // Try to extract end date if available
            if (isset($data[4]['A'])) {
                $endDateStr = trim(str_replace('End date -', '', $data[4]['A'] ?? ''));
                if (! empty($endDateStr)) {
                    try {
                        $endDate = \Carbon\Carbon::parse($endDateStr)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $this->parentImport->addError("Sheet '{$sheetName}': Invalid end date format - ".$endDateStr);
                    }
                }
            }

            $description = trim(str_replace('Description -', '', $data[5]['A'] ?? ''));

            // Validate required fields
            if (empty($grantCode)) {
                $this->parentImport->addError("Sheet '{$sheetName}': Missing grant code");

                return null;
            }

            if (empty($grantName)) {
                $this->parentImport->addError("Sheet '{$sheetName}': Missing grant name");

                return null;
            }

            return Grant::firstOrCreate(
                ['code' => $grantCode],
                [
                    'name' => $grantName,
                    'end_date' => $endDate,
                    'organization' => $organization,
                    'description' => $description,
                    'created_by' => auth()->user()->name ?? 'system',
                    'updated_by' => auth()->user()->name ?? 'system',
                ]
            );
        } catch (\Exception $e) {
            $this->parentImport->addError("Sheet '{$sheetName}': Error creating grant - ".$e->getMessage());

            return null;
        }
    }

    /**
     * Process grant items from Excel data
     */
    private function processGrantItems(array $data, Grant $grant, string $sheetName): int
    {
        $itemsProcessed = 0;
        $createdGrantItems = [];

        try {
            // Skip header rows (1-6) and start processing from row 7
            $headerRowsCount = 6;

            for ($i = $headerRowsCount + 1; $i <= count($data); $i++) {
                $row = $data[$i];

                // Use column B as the first required field (grant_position)
                $grantPosition = trim($row['B'] ?? '');
                $bgLineCode = trim($row['A'] ?? '');

                // Skip empty rows or non-data rows
                if (empty($grantPosition)) {
                    continue;
                }

                // Budget Line Code can be empty for General Fund (hub grants)
                // Accept any format: 1.2.2.1, BL-001, A.B.C, etc.
                $bgLineCode = $bgLineCode !== '' ? $bgLineCode : null;

                // Create unique key - handle NULL budget line codes
                $itemKey = $grant->id.'|'.$grantPosition.'|'.($bgLineCode ?? 'NULL_'.uniqid());

                if (! isset($createdGrantItems[$itemKey])) {
                    // Check for duplicates ONLY if budget line code exists
                    // General Fund items (NULL budget line) can have duplicate positions
                    if ($bgLineCode !== null) {
                        $existingItem = GrantItem::where('grant_id', $grant->id)
                            ->where('grant_position', $grantPosition)
                            ->where('budgetline_code', $bgLineCode)
                            ->first();

                        if ($existingItem) {
                            $this->parentImport->addError("Sheet '{$sheetName}' row {$i}: Duplicate grant item - Position '{$grantPosition}' with budget line code '{$bgLineCode}' already exists for this grant");

                            continue;
                        }
                    }

                    $grantItem = GrantItem::create([
                        'grant_id' => $grant->id,
                        'grant_position' => $grantPosition,
                        'grant_salary' => isset($row['C']) && $row['C'] !== '' ? $this->toFloat($row['C']) : null,
                        'grant_benefit' => isset($row['D']) && $row['D'] !== '' ? $this->toFloat($row['D']) : null,
                        'grant_level_of_effort' => isset($row['E']) && $row['E'] !== '' ?
                            (float) trim(str_replace('%', '', $row['E'])) / 100 : null,
                        'grant_position_number' => isset($row['F']) && $row['F'] !== '' ? (int) $row['F'] : 1,
                        'budgetline_code' => $bgLineCode,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $createdGrantItems[$itemKey] = $grantItem;
                } else {
                    $grantItem = $createdGrantItems[$itemKey];
                }

                $itemsProcessed++;
            }
        } catch (\Exception $e) {
            $this->parentImport->addError("Sheet '{$sheetName}': Error processing items - ".$e->getMessage());
            throw $e;
        }

        return $itemsProcessed;
    }

    /**
     * Convert string value to float
     */
    private function toFloat($value): ?float
    {
        if (is_null($value)) {
            return null;
        }

        return floatval(preg_replace('/[^0-9.-]/', '', $value));
    }
}
