<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates the Employee Import Excel Template
 *
 * This export creates a structured Excel template with:
 * - Employee data columns with headers
 * - Validation rules row
 * - Sample data rows
 * - Dropdown validations for key fields
 * - Instructions sheet
 */
class EmployeeTemplateExport
{
    private Spreadsheet $spreadsheet;

    /**
     * Generate the template and return the file path
     */
    public function generate(): string
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->removeSheetByIndex(0);

        $this->createEmployeeDataSheet();
        $this->createInstructionsSheet();

        $this->spreadsheet->setActiveSheetIndex(0);

        return $this->saveToTempFile();
    }

    /**
     * Get the suggested filename for download
     */
    public function getFilename(): string
    {
        return 'employee_import_template_'.date('Y-m-d_His').'.xlsx';
    }

    /**
     * Create the main Employee Data sheet
     */
    private function createEmployeeDataSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Employee Data');

        $columns = $this->getColumnDefinitions();

        // Set column widths and headers
        foreach ($columns as $col => $data) {
            $sheet->getColumnDimension($col)->setWidth($data['width']);
            $sheet->setCellValue($col.'1', $data['header']);
            $sheet->setCellValue($col.'2', $data['validation']);
        }

        // Style header row
        $lastColumn = array_key_last($columns);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray($this->getHeaderStyle());
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Style validation row
        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray($this->getValidationStyle());
        $sheet->getRowDimension(2)->setRowHeight(30);

        // Add sample data
        $this->addSampleData($sheet, $columns);

        // Add dropdown validations
        $this->addDropdownValidations($sheet);

        // Freeze header rows
        $sheet->freezePane('A3');
    }

    /**
     * Get column definitions with headers, widths, and validation rules
     */
    private function getColumnDefinitions(): array
    {
        return [
            'A' => ['header' => 'Org', 'width' => 12, 'validation' => 'REQUIRED - Must be SMRU or BHF - Case insensitive'],
            'B' => ['header' => 'Staff ID', 'width' => 18, 'validation' => 'REQUIRED - Min 3 chars - Max 50 chars - Alphanumeric and dash only - Must be unique per organization'],
            'C' => ['header' => 'Initial', 'width' => 10, 'validation' => 'OPTIONAL - Max 10 chars'],
            'D' => ['header' => 'First Name', 'width' => 20, 'validation' => 'REQUIRED - Min 2 chars - Max 255 chars'],
            'E' => ['header' => 'Last Name', 'width' => 20, 'validation' => 'OPTIONAL - Max 255 chars'],
            'F' => ['header' => 'Initial (TH)', 'width' => 10, 'validation' => 'OPTIONAL - Max 10 chars'],
            'G' => ['header' => 'First Name (TH)', 'width' => 20, 'validation' => 'OPTIONAL - Max 255 chars'],
            'H' => ['header' => 'Last Name (TH)', 'width' => 20, 'validation' => 'OPTIONAL - Max 255 chars'],
            'I' => ['header' => 'Gender', 'width' => 10, 'validation' => 'REQUIRED - Must be M or F'],
            'J' => ['header' => 'Date of Birth', 'width' => 18, 'validation' => 'REQUIRED - Format YYYY-MM-DD - Age must be 18-84 years'],
            'K' => ['header' => 'Age', 'width' => 10, 'validation' => 'AUTO-CALCULATED - Formula'],
            'L' => ['header' => 'Status', 'width' => 22, 'validation' => 'REQUIRED - Must be: Expats (Local), Local ID Staff, or Local non ID Staff'],
            'M' => ['header' => 'Nationality', 'width' => 15, 'validation' => 'OPTIONAL - Max 100 chars'],
            'N' => ['header' => 'Religion', 'width' => 15, 'validation' => 'OPTIONAL - Max 100 chars'],
            'O' => ['header' => 'ID Type', 'width' => 18, 'validation' => 'OPTIONAL - Select from dropdown - 10 years ID, Burmese ID, CI, Borderpass, Thai ID, Passport, Other'],
            'P' => ['header' => 'ID Number', 'width' => 22, 'validation' => 'OPTIONAL - Required if ID type provided'],
            'Q' => ['header' => 'ID Issue Date', 'width' => 18, 'validation' => 'OPTIONAL - Format YYYY-MM-DD - Cannot be in the future'],
            'R' => ['header' => 'ID Expiry Date', 'width' => 18, 'validation' => 'OPTIONAL - Format YYYY-MM-DD - Must be after issue date'],
            'S' => ['header' => 'Social Security No', 'width' => 20, 'validation' => 'OPTIONAL - Max 50 chars'],
            'T' => ['header' => 'Tax No', 'width' => 20, 'validation' => 'OPTIONAL - Max 50 chars'],
            'U' => ['header' => 'Driver License', 'width' => 20, 'validation' => 'OPTIONAL - Max 100 chars'],
            'V' => ['header' => 'Bank Name', 'width' => 20, 'validation' => 'OPTIONAL - Max 100 chars'],
            'W' => ['header' => 'Bank Branch', 'width' => 20, 'validation' => 'OPTIONAL - Max 100 chars'],
            'X' => ['header' => 'Bank Account Name', 'width' => 20, 'validation' => 'OPTIONAL - Max 100 chars'],
            'Y' => ['header' => 'Bank Account No', 'width' => 20, 'validation' => 'OPTIONAL - Max 50 chars'],
            'Z' => ['header' => 'Mobile No', 'width' => 18, 'validation' => 'OPTIONAL - Max 20 chars - 10+ digits'],
            'AA' => ['header' => 'Current Address', 'width' => 30, 'validation' => 'OPTIONAL - Text'],
            'AB' => ['header' => 'Permanent Address', 'width' => 30, 'validation' => 'OPTIONAL - Text'],
            'AC' => ['header' => 'Marital Status', 'width' => 18, 'validation' => 'OPTIONAL - Single, Married, Divorced, or Widowed - Affects spouse information requirements'],
            'AD' => ['header' => 'Spouse Name', 'width' => 20, 'validation' => 'OPTIONAL - Required if marital status is Married'],
            'AE' => ['header' => 'Spouse Mobile No', 'width' => 18, 'validation' => 'OPTIONAL - Recommended if spouse name provided'],
            'AF' => ['header' => 'Emergency Contact Name', 'width' => 20, 'validation' => 'OPTIONAL - Max 100 chars'],
            'AG' => ['header' => 'Relationship', 'width' => 15, 'validation' => 'OPTIONAL - Max 100 chars'],
            'AH' => ['header' => 'Emergency Mobile No', 'width' => 18, 'validation' => 'OPTIONAL - Max 20 chars'],
            'AI' => ['header' => 'Father Name', 'width' => 20, 'validation' => 'OPTIONAL - Max 200 chars'],
            'AJ' => ['header' => 'Father Occupation', 'width' => 20, 'validation' => 'OPTIONAL - Max 200 chars'],
            'AK' => ['header' => 'Father Mobile No', 'width' => 18, 'validation' => 'OPTIONAL - Max 20 chars'],
            'AL' => ['header' => 'Mother Name', 'width' => 20, 'validation' => 'OPTIONAL - Max 200 chars'],
            'AM' => ['header' => 'Mother Occupation', 'width' => 20, 'validation' => 'OPTIONAL - Max 200 chars'],
            'AN' => ['header' => 'Mother Mobile No', 'width' => 18, 'validation' => 'OPTIONAL - Max 20 chars'],
            'AO' => ['header' => 'Kin 1 Name', 'width' => 20, 'validation' => 'OPTIONAL - Beneficiary 1 name'],
            'AP' => ['header' => 'Kin 1 Relationship', 'width' => 18, 'validation' => 'OPTIONAL - Required if beneficiary 1 name provided'],
            'AQ' => ['header' => 'Kin 1 Mobile', 'width' => 18, 'validation' => 'OPTIONAL - Max 20 chars'],
            'AR' => ['header' => 'Kin 2 Name', 'width' => 20, 'validation' => 'OPTIONAL - Beneficiary 2 name'],
            'AS' => ['header' => 'Kin 2 Relationship', 'width' => 18, 'validation' => 'OPTIONAL - Required if beneficiary 2 name provided'],
            'AT' => ['header' => 'Kin 2 Mobile', 'width' => 18, 'validation' => 'OPTIONAL - Max 20 chars'],
            'AU' => ['header' => 'Military Status', 'width' => 18, 'validation' => 'OPTIONAL - Yes or No'],
            'AV' => ['header' => 'Remark', 'width' => 30, 'validation' => 'OPTIONAL - Max 255 chars'],
        ];
    }

    /**
     * Add sample data rows
     */
    private function addSampleData($sheet, array $columns): void
    {
        $sampleData = [
            // Row 3: SMRU, Local ID Staff, 10 years ID, Single male
            [
                'SMRU', 'EMP001', 'Mr.', 'John', 'Doe', 'นาย', 'จอห์น', 'โด', 'M', '1990-01-15', '',
                'Local ID Staff', 'Thai', 'Buddhist', '10 years ID', '1234567890123', '2020-01-15', '',
                'SS123456', 'TAX123456', 'DL123456', 'Bangkok Bank', 'Headquarters', 'John Doe', '1234567890',
                '0812345678', '123 Main St, Bangkok', '456 Home St, Bangkok',
                'Single', '', '', 'Jane Doe', 'Sister', '0823456789',
                'Robert Doe', 'Engineer', '0834567890', 'Mary Doe', 'Teacher', '0845678901',
                'Jane Doe', 'Sister', '0823456789', '', '', '',
                'Yes', 'New employee',
            ],
            // Row 4: BHF, Expats (Local), Passport, Married female with spouse info
            [
                'BHF', 'EMP002', 'Ms.', 'Sarah', 'Smith', 'นางสาว', 'ซาร่าห์', 'สมิธ', 'F', '1985-05-20', '',
                'Expats (Local)', 'American', 'Christian', 'Passport', 'P1234567', '2022-06-01', '2032-06-01',
                'SS234567', 'TAX234567', '', 'Kasikorn Bank', 'Silom Branch', 'Sarah Smith', '0987654321',
                '0898765432', '789 Office Rd, Bangkok', '321 Apartment, Bangkok',
                'Married', 'Tom Smith', '0887654321', 'Emergency Contact', 'Friend', '0876543210',
                'David Smith', 'Doctor', '0865432109', 'Linda Smith', 'Nurse', '0854321098',
                'Tom Smith', 'Spouse', '0887654321', '', '', '',
                'No', 'Senior staff',
            ],
        ];

        foreach ($sampleData as $rowIndex => $rowData) {
            $rowNum = $rowIndex + 3;
            $colIndex = 0;
            foreach ($columns as $col => $colData) {
                if ($colIndex < count($rowData)) {
                    $value = $rowData[$colIndex];
                    $sheet->setCellValue($col.$rowNum, $value);

                    // Add age formula for column K
                    if ($col === 'K') {
                        $sheet->setCellValue($col.$rowNum, '=DATEDIF(J'.$rowNum.',TODAY(),"Y")');
                    }
                }
                $colIndex++;
            }
        }
    }

    /**
     * Add dropdown validations for key fields
     */
    private function addDropdownValidations($sheet): void
    {
        $maxRow = 1000;

        // Organization dropdown (Column A)
        $this->addDropdown($sheet, 'A', $maxRow, '"SMRU,BHF"', 'Organization', 'Select SMRU or BHF', false);

        // Gender dropdown (Column I)
        $this->addDropdown($sheet, 'I', $maxRow, '"M,F"', 'Gender', 'Select M (Male) or F (Female)', false);

        // Status dropdown (Column L)
        $this->addDropdown($sheet, 'L', $maxRow, '"Expats (Local),Local ID Staff,Local non ID Staff"', 'Status', 'Select employee status', false);

        // Identification Type dropdown (Column O)
        $this->addDropdown($sheet, 'O', $maxRow, '"10 years ID,Burmese ID,CI,Borderpass,Thai ID,Passport,Other"', 'Identification Type', 'Select identification type', true);

        // Marital Status dropdown (Column AC)
        $this->addDropdown($sheet, 'AC', $maxRow, '"Single,Married,Divorced,Widowed"', 'Marital Status', 'Select marital status', true);

        // Military Status dropdown (Column AU)
        $this->addDropdown($sheet, 'AU', $maxRow, '"Yes,No"', 'Military Status', 'Select Yes or No', true);
    }

    /**
     * Add dropdown validation to a column
     */
    private function addDropdown($sheet, string $column, int $maxRow, string $formula, string $title, string $prompt, bool $allowBlank): void
    {
        for ($row = 3; $row <= $maxRow; $row++) {
            $validation = $sheet->getCell($column.$row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle($allowBlank ? DataValidation::STYLE_INFORMATION : DataValidation::STYLE_STOP);
            $validation->setAllowBlank($allowBlank);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Invalid '.$title);
            $validation->setError('Please select a valid value from the dropdown');
            $validation->setPromptTitle($title);
            $validation->setPrompt($prompt);
            $validation->setFormula1($formula);
        }
    }

    /**
     * Create the Instructions sheet
     */
    private function createInstructionsSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Instructions');
        $sheet->getColumnDimension('A')->setWidth(100);

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
        $tempFile = tempnam(sys_get_temp_dir(), 'employee_template_');
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Get header row style
     */
    private function getHeaderStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
    }

    /**
     * Get validation row style
     */
    private function getValidationStyle(): array
    {
        return [
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '666666']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF9E6']],
            'alignment' => ['wrapText' => true],
        ];
    }

    /**
     * Get the instructions content
     */
    private function getInstructionsContent(): array
    {
        return [
            'EMPLOYEE IMPORT TEMPLATE - INSTRUCTIONS',
            '',
            'FILE STRUCTURE:',
            '- Row 1: Column headers (do not modify)',
            '- Row 2: Validation rules/instructions (do not modify)',
            '- Row 3+: Your employee data (delete sample rows and add your data)',
            '',
            'REQUIRED FIELDS:',
            '- Org: Organization (SMRU or BHF)',
            '- Staff ID: Unique identifier per organization',
            '- First Name: Employee first name',
            '- Gender: M (Male) or F (Female)',
            '- Date of Birth: Format YYYY-MM-DD, age must be 18-84',
            '- Status: Expats (Local), Local ID Staff, or Local non ID Staff',
            '',
            'IDENTIFICATION FIELDS:',
            '- ID Type: Select from dropdown (10 years ID, Burmese ID, CI, Borderpass, Thai ID, Passport, Other)',
            '- ID Number: Required if ID Type is provided',
            '- ID Issue Date: Optional, format YYYY-MM-DD',
            '- ID Expiry Date: Optional, format YYYY-MM-DD (must be after issue date)',
            '',
            'DROPDOWN FIELDS:',
            '- Organization (Column A): SMRU or BHF',
            '- Gender (Column I): M or F',
            '- Status (Column L): Expats (Local), Local ID Staff, Local non ID Staff',
            '- ID Type (Column O): Select from list',
            '- Marital Status (Column AC): Single, Married, Divorced, Widowed',
            '- Military Status (Column AU): Yes or No',
            '',
            'CONDITIONAL REQUIREMENTS:',
            '- If Marital Status is "Married", Spouse Name is recommended',
            '- If ID Type is provided, ID Number is required',
            '- If Kin 1 Name is provided, Kin 1 Relationship is required',
            '- If Kin 2 Name is provided, Kin 2 Relationship is required',
            '',
            'DATE FORMAT:',
            'All dates must be in YYYY-MM-DD format (e.g., 2025-01-15)',
            '',
            'TIPS:',
            '- Use dropdowns where available to avoid errors',
            '- Staff ID must be unique within the same organization',
            '- Test with a small batch first (2-3 employees)',
            '- Age formula in Column K is auto-calculated',
            '',
            'For more information, contact your system administrator.',
        ];
    }
}
