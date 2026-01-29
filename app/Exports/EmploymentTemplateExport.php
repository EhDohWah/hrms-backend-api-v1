<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates the Employment Import Excel Template
 *
 * This export creates a structured Excel template with:
 * - Employment data columns with headers
 * - Validation rules row
 * - Sample data rows
 * - Dropdown validations for key fields
 * - Instructions sheet
 */
class EmploymentTemplateExport
{
    private Spreadsheet $spreadsheet;

    /**
     * Generate the template and return the file path
     */
    public function generate(): string
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->removeSheetByIndex(0);

        $this->createEmploymentDataSheet();
        $this->createInstructionsSheet();

        $this->spreadsheet->setActiveSheetIndex(0);

        return $this->saveToTempFile();
    }

    /**
     * Get the suggested filename for download
     */
    public function getFilename(): string
    {
        return 'employment_import_template_'.date('Y-m-d_His').'.xlsx';
    }

    /**
     * Create the main Employment Data sheet
     */
    private function createEmploymentDataSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Employment Import');

        $headers = $this->getHeaders();
        $validationRules = $this->getValidationRules();
        $columnWidths = $this->getColumnWidths();

        // Write headers (Row 1)
        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($col, 1);
            $cell->setValue($header);
            $cell->getStyle()->applyFromArray($this->getHeaderStyle());
            $col++;
        }

        // Write validation rules (Row 2)
        $col = 1;
        foreach ($validationRules as $rule) {
            $cell = $sheet->getCellByColumnAndRow($col, 2);
            $cell->setValue($rule);
            $cell->getStyle()->applyFromArray($this->getValidationStyle());
            $col++;
        }
        $sheet->getRowDimension(2)->setRowHeight(60);

        // Add sample data
        $this->addSampleData($sheet);

        // Set column widths
        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Add dropdown validations
        $this->addDropdownValidations($sheet);

        // Freeze header rows
        $sheet->freezePane('A3');
    }

    /**
     * Get column headers
     */
    private function getHeaders(): array
    {
        return [
            'staff_id',
            'start_date',
            'pass_probation_salary',
            'pass_probation_date',
            'probation_salary',
            'end_probation_date',
            'pay_method',
            'site_code',
            'department',
            'section_department_id',
            'position',
            'health_welfare',
            'pvd',
            'saving_fund',
            'status',
        ];
    }

    /**
     * Get validation rules for each column
     */
    private function getValidationRules(): array
    {
        return [
            'String - NOT NULL - Employee staff ID (must exist in system)',
            'Date (YYYY-MM-DD) - NOT NULL - Employment start date',
            'Decimal(10,2) - NOT NULL - Regular salary after probation',
            'Date (YYYY-MM-DD) - NULLABLE - Probation end date (default: 3 months after start)',
            'Decimal(10,2) - NULLABLE - Salary during probation period',
            'Date (YYYY-MM-DD) - NULLABLE - End date for employment termination',
            'String - NULLABLE - Values: Transferred to bank, Cash cheque',
            'String - NULLABLE - Site code (must exist in sites table, e.g., MRM, BHF)',
            'String - NULLABLE - Department name (must exist in departments table)',
            'Integer - NULLABLE - Section department ID (must exist in section_departments table)',
            'String - NULLABLE - Position title (must exist in positions table)',
            'Boolean (1/0) - NULLABLE - Health welfare benefit enabled (default: 0) - Percentages managed globally',
            'Boolean (1/0) - NULLABLE - Provident fund enabled (default: 0) - Percentages managed globally',
            'Boolean (1/0) - NULLABLE - Saving fund enabled (default: 0) - Percentages managed globally',
            'Boolean (1/0) - NULLABLE - Employment status: 1=Active, 0=Inactive (default: 1)',
        ];
    }

    /**
     * Get column widths
     */
    private function getColumnWidths(): array
    {
        return [
            'A' => 15,  // staff_id
            'B' => 15,  // start_date
            'C' => 20,  // pass_probation_salary
            'D' => 20,  // pass_probation_date
            'E' => 18,  // probation_salary
            'F' => 18,  // end_probation_date
            'G' => 20,  // pay_method
            'H' => 15,  // site_code
            'I' => 20,  // department
            'J' => 22,  // section_department_id
            'K' => 20,  // position
            'L' => 18,  // health_welfare
            'M' => 12,  // pvd
            'N' => 15,  // saving_fund
            'O' => 12,  // status
        ];
    }

    /**
     * Add sample data rows
     */
    private function addSampleData($sheet): void
    {
        $sampleData = [
            [
                'EMP001', '2025-01-15', '50000.00', '2025-04-15', '45000.00', '',
                'Monthly', 'MRM', 'Human Resources', '', 'HR Manager', '1', '1', '0', '1',
            ],
            [
                'EMP002', '2025-02-01', '30000.00', '2025-05-01', '', '',
                'Hourly', 'BHF', 'Finance', 'Accounting', 'Accountant', '0', '1', '1', '1',
            ],
            [
                'EMP003', '2025-03-01', '60000.00', '', '', '2025-12-31',
                'Bank Transfer', 'SMRU', 'IT', '', 'Software Developer', '1', '0', '0', '1',
            ],
        ];

        $row = 3;
        foreach ($sampleData as $data) {
            $col = 1;
            foreach ($data as $value) {
                $sheet->getCellByColumnAndRow($col, $row)->setValue($value);
                $col++;
            }
            $row++;
        }
    }

    /**
     * Add dropdown validations
     */
    private function addDropdownValidations($sheet): void
    {
        $maxRow = 1000;

        // Pay Method dropdown (Column G)
        $payMethodValidation = $sheet->getCell('G6')->getDataValidation();
        $payMethodValidation->setType(DataValidation::TYPE_LIST);
        $payMethodValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $payMethodValidation->setAllowBlank(true);
        $payMethodValidation->setShowInputMessage(true);
        $payMethodValidation->setFormula1('"Monthly,Weekly,Daily,Hourly,Bank Transfer,Cash,Cheque"');
        $payMethodValidation->setPromptTitle('Pay Method');
        $payMethodValidation->setPrompt('Select pay method');

        for ($i = 6; $i <= $maxRow; $i++) {
            $sheet->getCell("G{$i}")->setDataValidation(clone $payMethodValidation);
        }

        // Boolean fields (1/0) validation
        $booleanColumns = ['L', 'M', 'N', 'O']; // health_welfare, pvd, saving_fund, status
        foreach ($booleanColumns as $column) {
            $booleanValidation = $sheet->getCell("{$column}6")->getDataValidation();
            $booleanValidation->setType(DataValidation::TYPE_LIST);
            $booleanValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $booleanValidation->setAllowBlank(true);
            $booleanValidation->setFormula1('"1,0"');
            $booleanValidation->setPromptTitle('Boolean Value');
            $booleanValidation->setPrompt('Enter 1 for Yes/True or 0 for No/False');

            for ($i = 6; $i <= $maxRow; $i++) {
                $sheet->getCell("{$column}{$i}")->setDataValidation(clone $booleanValidation);
            }
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
            $text = is_array($instruction) ? $instruction[0] : $instruction;
            $sheet->setCellValue("A{$row}", $text);
            if ($row === 1) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
            } elseif (strpos($text, ':') !== false && strpos($text, '-') === 0) {
                // Skip styling for list items
            } elseif (strpos($text, ':') !== false) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
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
        $tempFile = tempnam(sys_get_temp_dir(), 'employment_template_');
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
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
    }

    /**
     * Get validation row style
     */
    private function getValidationStyle(): array
    {
        return [
            'font' => ['italic' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
        ];
    }

    /**
     * Get the instructions content
     */
    private function getInstructionsContent(): array
    {
        return [
            'Employment Import Template - Instructions',
            '',
            'IMPORTANT NOTES:',
            '1. Required Fields (Cannot be empty):',
            '   - staff_id: Employee staff ID (must exist in system)',
            '   - start_date: Employment start date (YYYY-MM-DD format)',
            '   - pass_probation_salary: Regular salary after probation',
            '',
            '2. Date Format: All dates must be in YYYY-MM-DD format (e.g., 2025-01-15)',
            '',
            '3. Boolean Fields: Use 1 for Yes/True, 0 for No/False',
            '   - health_welfare, pvd, saving_fund, status',
            '   - Note: Benefit percentages are managed globally in system settings',
            '',
            '4. Foreign Keys (Must exist in database):',
            '   - staff_id: Must match an existing employee',
            '   - site_code: Must match an existing site code (e.g., MRM, BHF, SMRU)',
            '   - department: Must match an existing department name',
            '   - section_department_id: Must be a valid section department ID',
            '   - position: Must match an existing position title',
            '',
            '5. Salary Fields:',
            '   - pass_probation_salary: The salary after passing probation (REQUIRED)',
            '   - probation_salary: The salary during probation period (OPTIONAL)',
            '',
            '6. Probation Period:',
            '   - If pass_probation_date is empty, it defaults to 3 months after start_date',
            '   - probation_salary is optional; if empty, pass_probation_salary is used',
            '',
            '7. Employment Termination:',
            '   - end_probation_date: Set this to terminate employment on a specific date',
            '   - Leave empty for ongoing employment',
            '',
            'SAMPLE DATA (Rows 3-5):',
            '- Row 3: Standard employment with probation period',
            '- Row 4: Employment with department section',
            '- Row 5: Employment with end date (contract position)',
            '',
            'TIPS:',
            '- Delete sample rows before importing your data',
            '- Start entering data from row 6',
            '- Test with a small batch first (2-3 records)',
            '- Verify all foreign keys exist in the system',
            '',
            'For more information, contact your system administrator.',
        ];
    }
}
