<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates the Employee Funding Allocation Import Excel Template
 *
 * This export creates a structured Excel template with:
 * - Funding allocation data columns with headers
 * - Validation rules row
 * - Sample data rows
 * - Instructions sheet
 */
class EmployeeFundingAllocationTemplateExport
{
    private Spreadsheet $spreadsheet;

    /**
     * Generate the template and return the file path
     */
    public function generate(): string
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->removeSheetByIndex(0);

        $this->createFundingAllocationDataSheet();
        $this->createInstructionsSheet();

        $this->spreadsheet->setActiveSheetIndex(0);

        return $this->saveToTempFile();
    }

    /**
     * Get the suggested filename for download
     */
    public function getFilename(): string
    {
        return 'employee_funding_allocation_template_'.date('Y-m-d_His').'.xlsx';
    }

    /**
     * Create the main Funding Allocation Data sheet
     */
    private function createFundingAllocationDataSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Funding Allocation Import');

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
            'grant_item_id',
            'fte',
            'allocated_amount',
            'start_date',
            'end_date',
            'notes',
        ];
    }

    /**
     * Get validation rules for each column
     */
    private function getValidationRules(): array
    {
        return [
            'String - NOT NULL - Employee staff ID (must exist in system)',
            'Integer - NOT NULL - Grant item ID (use Grant Items Reference file)',
            'Decimal (0-100) - NOT NULL - FTE percentage (e.g., 50 for 50%, 100 for 100%)',
            'Decimal(15,2) - NULLABLE - Pre-calculated allocated amount (auto-calculated if empty)',
            'Date (YYYY-MM-DD) - NOT NULL - Allocation start date',
            'Date (YYYY-MM-DD) - NULLABLE - Allocation end date (leave empty for ongoing)',
            'Text - NULLABLE - Additional notes or comments',
        ];
    }

    /**
     * Get column widths
     */
    private function getColumnWidths(): array
    {
        return [
            'A' => 15,  // staff_id
            'B' => 18,  // grant_item_id
            'C' => 12,  // fte
            'D' => 18,  // allocated_amount
            'E' => 15,  // start_date
            'F' => 15,  // end_date
            'G' => 35,  // notes
        ];
    }

    /**
     * Add sample data rows
     */
    private function addSampleData($sheet): void
    {
        $sampleData = [
            ['EMP001', '1', '100', '', '2025-01-01', '', 'Full-time allocation to Grant Item 1'],
            ['EMP002', '2', '60', '30000.00', '2025-01-15', '2025-12-31', 'Part-time 60% allocation'],
            ['EMP002', '3', '40', '20000.00', '2025-01-15', '2025-12-31', 'Split funding - remaining 40%'],
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
            } elseif (strpos($text, ':') !== false && ! str_starts_with(trim($text), '-') && ! str_starts_with(trim($text), 'EMP')) {
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
        $tempFile = tempnam(sys_get_temp_dir(), 'funding_allocation_template_');
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
            'Employee Funding Allocation Import Template - Instructions',
            '',
            'BEFORE YOU START:',
            '1. Download the "Grant Items Reference" file to get valid Grant Item IDs',
            '2. The Grant Items Reference contains all grants and their items with IDs',
            '3. You will need the Grant Item ID (Column E) from that file',
            '',
            'REQUIRED FIELDS (Cannot be empty):',
            '- staff_id: Employee staff ID (must exist in system)',
            '- grant_item_id: Grant item ID from Grant Items Reference file',
            '- fte: FTE percentage (0-100, e.g., 50 for 50%, 100 for 100%)',
            '- start_date: Allocation start date (YYYY-MM-DD format)',
            '',
            'OPTIONAL FIELDS:',
            '- allocated_amount: Leave empty for auto-calculation based on salary',
            '- end_date: Leave empty for ongoing allocation',
            '- notes: Any additional comments or information',
            '',
            'HOW IT WORKS:',
            '1. System uses staff_id to find the employee',
            '2. System automatically finds the active employment for that employee',
            '3. System creates funding allocation linking employee to grant item',
            '4. If allocated_amount is empty, system calculates it based on FTE and salary',
            '',
            'DATE FORMAT:',
            'All dates must be in YYYY-MM-DD format (e.g., 2025-01-15)',
            '',
            'FTE (Full-Time Equivalent):',
            '- Enter as percentage without % symbol',
            '- Examples: 100 = full-time, 50 = half-time, 25 = quarter-time',
            '- For split funding: Create multiple rows for the same employee',
            '- Example: Employee 60% on Grant A + 40% on Grant B = 2 rows',
            '',
            'GRANT ITEM ID:',
            '- Download "Grant Items Reference" file to see all available grant items',
            '- Each grant has multiple grant items (positions)',
            '- Copy the Grant Item ID from the reference file',
            '- One grant has many grant items - choose the correct item for the position',
            '',
            'VALIDATION RULES:',
            '- staff_id must exist in the system',
            '- grant_item_id must be valid (check Grant Items Reference)',
            '- FTE must be between 0 and 100',
            '- start_date is required',
            '- end_date is optional (leave empty for ongoing)',
            '- Employee must have an active employment record',
            '- Total FTE per employee should equal 100%',
            '',
            'EXAMPLE SCENARIOS:',
            '',
            'Single Funding (100%):',
            '  EMP001 | 5 | 100 | | 2025-01-01 | | Full-time on one grant',
            '',
            'Split Funding (60/40):',
            '  EMP002 | 10 | 60 | | 2025-01-01 | | 60% on Grant Item 10',
            '  EMP002 | 15 | 40 | | 2025-01-01 | | 40% on Grant Item 15',
            '',
            'Split Funding (50/30/20):',
            '  EMP003 | 20 | 50 | | 2025-01-01 | | Half-time on Grant Item 20',
            '  EMP003 | 25 | 30 | | 2025-01-01 | | 30% on Grant Item 25',
            '  EMP003 | 30 | 20 | | 2025-01-01 | | 20% on Grant Item 30',
            '',
            'BEST PRACTICES:',
            '- Always download the latest Grant Items Reference before importing',
            '- Verify staff_id exists in the system',
            '- Keep total FTE per employee = 100%',
            '- Use consistent date formats (YYYY-MM-DD)',
            '- Test with a small batch first (2-3 employees)',
            '- Review the Grant Items Reference to understand grant structure',
            '',
            'AFTER UPLOAD:',
            '- You will receive a notification when import completes',
            '- Check notification for success/error summary',
            '- Review created/updated allocations in the system',
            '- Verify that allocations are correctly linked to grant items',
        ];
    }
}
