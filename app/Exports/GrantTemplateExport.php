<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates the Grant Import Excel Template
 *
 * This export creates a structured Excel template with:
 * - Grant header fields (Name, Code, Subsidiary, End Date, Description)
 * - Grant items section with column headers
 * - Dropdown validation for Subsidiary field
 * - Cell protection for labels
 * - Detailed instructions sheet
 */
class GrantTemplateExport
{
    private Spreadsheet $spreadsheet;

    /**
     * Generate the template and return the file path
     */
    public function generate(): string
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->removeSheetByIndex(0);

        $this->createGrantTemplateSheet();
        $this->createInstructionsSheet();

        $this->spreadsheet->setActiveSheetIndex(0);

        return $this->saveToTempFile();
    }

    /**
     * Get the suggested filename for download
     */
    public function getFilename(): string
    {
        return 'grant_import_template_'.date('Y-m-d_His').'.xlsx';
    }

    /**
     * Create the main Grant Template sheet
     */
    private function createGrantTemplateSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Grant Template');

        $this->setColumnWidths($sheet);
        $this->createGrantHeaderSection($sheet);
        $this->createGrantItemsSection($sheet);
        $this->applySheetProtection($sheet);
    }

    /**
     * Set column widths for better readability
     */
    private function setColumnWidths($sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(10);
    }

    /**
     * Create the grant header section (rows 1-6)
     */
    private function createGrantHeaderSection($sheet): void
    {
        $labelStyle = $this->getLabelStyle();
        $valueStyle = $this->getValueStyle();
        $validationStyle = $this->getValidationStyle();

        // Row 1: Grant Name
        $this->createHeaderRow($sheet, 1, 'Grant Name', 'REQUIRED - String - Min 3 chars, Max 255 chars', $labelStyle, $valueStyle, $validationStyle);

        // Row 2: Grant Code
        $this->createHeaderRow($sheet, 2, 'Grant Code', 'REQUIRED - String - Max 50 chars - Must be unique - Alphanumeric, dots, dashes, underscores only', $labelStyle, $valueStyle, $validationStyle);

        // Row 3: Subsidiary with dropdown
        $this->createHeaderRow($sheet, 3, 'Subsidiary', 'REQUIRED - Must be SMRU or BHF (use dropdown)', $labelStyle, $valueStyle, $validationStyle);
        $this->addSubsidiaryDropdown($sheet);

        // Row 4: End Date
        $this->createHeaderRow($sheet, 4, 'End Date', 'OPTIONAL - Format: YYYY-MM-DD', $labelStyle, $valueStyle, $validationStyle);

        // Row 5: Description
        $this->createHeaderRow($sheet, 5, 'Description', 'OPTIONAL - Max 1000 chars', $labelStyle, $valueStyle, $validationStyle);

        // Row 6: Spacer
        $sheet->getRowDimension(6)->setRowHeight(8);
        $sheet->getStyle('A6:H6')->applyFromArray($this->getSpacerStyle());
    }

    /**
     * Create a single header row with label, value, and validation columns
     */
    private function createHeaderRow($sheet, int $row, string $label, string $validation, array $labelStyle, array $valueStyle, array $validationStyle): void
    {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", $validation);
        $sheet->mergeCells("C{$row}:H{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray($labelStyle);
        $sheet->getStyle("B{$row}")->applyFromArray($valueStyle);
        $sheet->getStyle("C{$row}:H{$row}")->applyFromArray($validationStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
    }

    /**
     * Add dropdown validation to the Subsidiary field (B3)
     */
    private function addSubsidiaryDropdown($sheet): void
    {
        $validation = $sheet->getCell('B3')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid Organization');
        $validation->setError('Please select SMRU or BHF from the dropdown list.');
        $validation->setPromptTitle('Select Organization');
        $validation->setPrompt('Choose the organization: SMRU or BHF');
        $validation->setFormula1('"SMRU,BHF"');
    }

    /**
     * Create the grant items section (rows 7-8)
     */
    private function createGrantItemsSection($sheet): void
    {
        $columnHeaderStyle = $this->getColumnHeaderStyle();
        $itemValidationStyle = $this->getItemValidationStyle();

        // Row 7: Column headers
        $headers = [
            'A7' => 'Budget Line Code',
            'B7' => 'Position',
            'C7' => 'Salary',
            'D7' => 'Benefit',
            'E7' => 'Level of Effort (%)',
            'F7' => 'Position Number',
        ];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->getStyle('A7:F7')->applyFromArray($columnHeaderStyle);
        $sheet->getRowDimension(7)->setRowHeight(25);

        // Row 8: Validation rules reference
        $validationRules = [
            'A8' => 'OPTIONAL (Max 50 chars)',
            'B8' => 'REQUIRED (Min 2, Max 255 chars)',
            'C8' => 'OPTIONAL (0 - 99,999,999.99)',
            'D8' => 'OPTIONAL (0 - 99,999,999.99)',
            'E8' => 'OPTIONAL (0-100 or 0-1)',
            'F8' => 'OPTIONAL (Default: 1, Range: 1-1000)',
        ];
        foreach ($validationRules as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->getStyle('A8:F8')->applyFromArray($itemValidationStyle);
        $sheet->getRowDimension(8)->setRowHeight(35);

        // Add borders to header area
        $sheet->getStyle('A7:F8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Apply sheet protection with unlocked editable cells
     */
    private function applySheetProtection($sheet): void
    {
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('grant_template');

        // Unlock value cells (B1-B5) for editing
        for ($row = 1; $row <= 5; $row++) {
            $sheet->getStyle("B{$row}")->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        // Unlock grant items area (rows 7+) for editing
        $sheet->getStyle('A7:F1000')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
    }

    /**
     * Create the Instructions sheet
     */
    private function createInstructionsSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Instructions');
        $sheet->getColumnDimension('A')->setWidth(80);

        $instructions = $this->getInstructionsContent();

        $row = 1;
        foreach ($instructions as $instruction) {
            $sheet->setCellValue("A{$row}", $instruction);
            if ($row === 1) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
            }
            $row++;
        }

        $sheet->getStyle("A1:A{$row}")->getAlignment()->setWrapText(true);
    }

    /**
     * Save spreadsheet to a temporary file and return the path
     */
    private function saveToTempFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'grant_template_');
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Style definitions - Label cells (Column A)
     */
    private function getLabelStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D6EAF8'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ];
    }

    /**
     * Style definitions - Value cells (Column B)
     */
    private function getValueStyle(): array
    {
        return [
            'font' => [
                'size' => 11,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '333333'],
                ],
            ],
        ];
    }

    /**
     * Style definitions - Validation instruction cells (Columns C-H)
     */
    private function getValidationStyle(): array
    {
        return [
            'font' => [
                'italic' => true,
                'size' => 9,
                'color' => ['rgb' => '666666'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF9E6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
    }

    /**
     * Style definitions - Column headers for grant items
     */
    private function getColumnHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
    }

    /**
     * Style definitions - Item validation reference row
     */
    private function getItemValidationStyle(): array
    {
        return [
            'font' => [
                'italic' => true,
                'size' => 8,
                'color' => ['rgb' => '888888'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F5F5F5'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
    }

    /**
     * Style definitions - Spacer row
     */
    private function getSpacerStyle(): array
    {
        return [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ],
        ];
    }

    /**
     * Get the instructions sheet content
     */
    private function getInstructionsContent(): array
    {
        return [
            'GRANT IMPORT TEMPLATE - INSTRUCTIONS',
            '',
            'IMPORTANT: Each sheet represents ONE grant with its grant items.',
            '',
            'NEW TEMPLATE STRUCTURE:',
            'This template uses a separate column layout for easier data entry:',
            '- Column A: Field labels (read-only)',
            '- Column B: Your input values',
            '- Column C+: Validation instructions',
            '',
            'FILE STRUCTURE:',
            '1. You can have multiple sheets in one Excel file',
            '2. Each sheet will create a separate grant',
            '3. Sheet name can be anything (it will not be imported)',
            '',
            'SHEET STRUCTURE (Rows 1-6: Grant Information):',
            'Row 1: Column A shows "Grant Name", Column B is where you enter the grant name',
            'Row 2: Column A shows "Grant Code", Column B is where you enter the unique code',
            'Row 3: Column A shows "Subsidiary", Column B has a DROPDOWN - select SMRU or BHF',
            'Row 4: Column A shows "End Date", Column B is for date in YYYY-MM-DD format (optional)',
            'Row 5: Column A shows "Description", Column B is for grant description (optional)',
            'Row 6: Spacer row (do not modify)',
            '',
            'SHEET STRUCTURE (Row 7+: Grant Items):',
            'Row 7: Column headers for grant items',
            'Row 8: Validation rules (reference row - you can keep or delete)',
            'Row 9+: Your grant item data - enter data starting from row 9',
            '',
            'GRANT HEADER FIELD DETAILS:',
            '',
            'Grant Name (Cell B1):',
            '- REQUIRED - Cannot be empty',
            '- Minimum 3 characters, Maximum 255 characters',
            '',
            'Grant Code (Cell B2):',
            '- REQUIRED - Cannot be empty',
            '- Maximum 50 characters',
            '- Must be unique across all grants',
            '- Only alphanumeric characters, dots (.), dashes (-), underscores (_) allowed',
            '',
            'Subsidiary/Organization (Cell B3):',
            '- REQUIRED - Must select from dropdown',
            '- Valid values: SMRU or BHF only',
            '- The system will suggest corrections for typos (e.g., "SMEU" will suggest "SMRU")',
            '',
            'End Date (Cell B4):',
            '- OPTIONAL - Can be left empty',
            '- Format: YYYY-MM-DD (e.g., 2024-12-31)',
            '',
            'Description (Cell B5):',
            '- OPTIONAL - Can be left empty',
            '- Maximum 1000 characters',
            '',
            'GRANT ITEM COLUMN DETAILS:',
            '',
            'A. Budget Line Code - OPTIONAL',
            '   - Can be empty for General Fund/Hub grants',
            '   - Maximum 50 characters',
            '   - Examples: 1.2.2.1, BL-001, A.B.C, CODE_123',
            '',
            'B. Position - REQUIRED',
            '   - Minimum 2 characters, Maximum 255 characters',
            '   - Examples: Project Manager, Senior Researcher, Field Officer',
            '',
            'C. Salary - OPTIONAL',
            '   - Numeric value, range: 0 to 99,999,999.99',
            '   - Examples: 75000, 75000.50',
            '',
            'D. Benefit - OPTIONAL',
            '   - Numeric value, range: 0 to 99,999,999.99',
            '   - Examples: 15000, 15000.00',
            '',
            'E. Level of Effort - OPTIONAL',
            '   - Accepts multiple formats: 75, 75%, or 0.75 (all mean 75%)',
            '   - Range: 0 to 100 (or 0 to 1 in decimal)',
            '',
            'F. Position Number - OPTIONAL',
            '   - Default value: 1',
            '   - Range: 1 to 1000',
            '   - Must be a whole number',
            '',
            'VALIDATION RULES:',
            '1. Grant Name must be at least 3 characters',
            '2. Grant Code must be unique and use only alphanumeric, dots, dashes, underscores',
            '3. Organization MUST be SMRU or BHF (typos will be detected with suggestions)',
            '4. For Project Grants: Position + Budget Line Code must be unique within each grant',
            '5. For General Fund: Budget Line Code can be empty, duplicate positions allowed',
            '6. Position field is always required for grant items',
            '',
            'ERROR MESSAGES:',
            'The import system provides detailed error messages with cell references:',
            '- "Grant name is required (Cell B1)" - Missing grant name',
            '- "Invalid organization: \'SMEU\'. Did you mean \'SMRU\'? (Cell B3)" - Typo detected',
            '- "Sheet \'Grant ABC\' Row 9: Grant salary cannot be negative" - Invalid item data',
            '',
            'DUPLICATE HANDLING:',
            '- If grant code exists: entire sheet is skipped, no data imported',
            '- If grant item exists (same position + budget code): item is skipped with error',
            '- General Fund items (empty budget code) can have duplicate positions',
            '',
            'TRANSACTION SAFETY:',
            '- Each sheet is processed as an atomic transaction',
            '- If ANY validation fails, NO data from that sheet is saved',
            '- This ensures data integrity - no partial imports',
            '',
            'EXAMPLE - Project Grant (using new template):',
            'Cell A1: "Grant Name"     Cell B1: "Health Initiative Grant"',
            'Cell A2: "Grant Code"     Cell B2: "GR-2024-001"',
            'Cell A3: "Subsidiary"     Cell B3: "SMRU" (selected from dropdown)',
            'Cell A4: "End Date"       Cell B4: "2024-12-31"',
            'Cell A5: "Description"    Cell B5: "Funding for health initiatives"',
            'Row 7: Budget Line Code | Position | Salary | Benefit | LOE | Position Number',
            'Row 9: 1.2.2.1 | Project Manager | 75000 | 15000 | 75 | 2',
            'Row 10: 1.2.1.2 | Senior Researcher | 60000 | 12000 | 100 | 3',
            '',
            'EXAMPLE - General Fund (using new template):',
            'Cell A1: "Grant Name"     Cell B1: "General Fund"',
            'Cell A2: "Grant Code"     Cell B2: "S22001"',
            'Cell A3: "Subsidiary"     Cell B3: "BHF" (selected from dropdown)',
            'Cell A4: "End Date"       Cell B4: (empty)',
            'Cell A5: "Description"    Cell B5: "BHF hub grant"',
            'Row 7: Budget Line Code | Position | Salary | Benefit | LOE | Position Number',
            'Row 9: (empty) | Manager | 75000 | 15000 | 100 | 2',
            'Row 10: (empty) | Field Officer | 45000 | 9000 | 100 | 3',
            'Row 11: (empty) | Manager | 60000 | 12000 | 75 | 1  (duplicate position OK!)',
            '',
            'TIPS:',
            '- Use the dropdown in Cell B3 to avoid organization typos',
            '- Data entry starts from Row 9 (Row 8 contains validation reference)',
            '- Test with a small file first (1-2 grants)',
            '- Keep a backup of your original data',
            '- Check the import notification for detailed results',
            '- Review any error messages carefully - they include cell/row references',
            '',
            'FILE REQUIREMENTS:',
            '- File format: .xlsx, .xls, or .csv',
            '- Maximum file size: 10MB',
            '- Each sheet must have at least 7 rows',
            '',
            'For more information, refer to the API documentation or contact your system administrator.',
        ];
    }
}
