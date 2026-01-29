<?php

namespace App\Exports;

use App\Models\Grant;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates the Grant Items Reference Excel file
 *
 * This export creates a reference file with:
 * - All grants and their grant items with IDs
 * - Highlighted Grant Item ID column (most important for imports)
 * - Instructions sheet
 */
class GrantItemsReferenceExport
{
    private Spreadsheet $spreadsheet;

    /**
     * Generate the reference file and return the file path
     */
    public function generate(): string
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->removeSheetByIndex(0);

        $this->createGrantItemsSheet();
        $this->createInstructionsSheet();

        $this->spreadsheet->setActiveSheetIndex(0);

        return $this->saveToTempFile();
    }

    /**
     * Get the suggested filename for download
     */
    public function getFilename(): string
    {
        return 'grant_items_reference_'.date('Y-m-d_His').'.xlsx';
    }

    /**
     * Create the main Grant Items Reference sheet
     */
    private function createGrantItemsSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Grant Items Reference');

        // Add important notice at the top
        $this->addNoticeRow($sheet);

        // Add headers (Row 2)
        $this->addHeaders($sheet);

        // Add data (Row 3+)
        $this->addGrantItemsData($sheet);

        // Set column widths
        $this->setColumnWidths($sheet);

        // Freeze header rows
        $sheet->freezePane('A3');
    }

    /**
     * Add notice row at the top
     */
    private function addNoticeRow($sheet): void
    {
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'IMPORTANT: Copy the "Grant Item ID" (Column E - Green) to your Funding Allocation Import Template');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FF6B6B');
        $sheet->getStyle('A1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);
    }

    /**
     * Add header row
     */
    private function addHeaders($sheet): void
    {
        $headers = [
            'Grant ID',
            'Grant Code',
            'Grant Name',
            'Grant Organization',
            'Grant Item ID',
            'Grant Position',
            'Budget Line Code',
            'Grant Salary',
            'Grant Benefit',
            'Level of Effort (%)',
            'Position Number',
            'Grant Status',
        ];

        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($col, 2);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true)->setSize(11);

            // Highlight Grant Item ID column (column E - the most important one)
            if ($header === 'Grant Item ID') {
                $cell->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('28A745');
                $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                $cell->getStyle()->getFont()->setSize(12)->setBold(true);
            } else {
                $cell->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('4472C4');
                $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
            }

            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
    }

    /**
     * Add grant items data from database
     */
    private function addGrantItemsData($sheet): void
    {
        $grants = Grant::with('grantItems')->orderBy('code')->get();

        $row = 3;
        foreach ($grants as $grant) {
            foreach ($grant->grantItems as $item) {
                $sheet->setCellValue("A{$row}", $grant->id);
                $sheet->setCellValue("B{$row}", $grant->code);
                $sheet->setCellValue("C{$row}", $grant->name);
                $sheet->setCellValue("D{$row}", $grant->organization);

                // Highlight Grant Item ID cell (Column E)
                $sheet->setCellValue("E{$row}", $item->id);
                $sheet->getStyle("E{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D4EDDA');
                $sheet->getStyle("E{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('155724');
                $sheet->getStyle("E{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$row}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('28A745');

                $sheet->setCellValue("F{$row}", $item->grant_position);
                $sheet->setCellValue("G{$row}", $item->budgetline_code);
                $sheet->setCellValue("H{$row}", $item->grant_salary);
                $sheet->setCellValue("I{$row}", $item->grant_benefit);
                $sheet->setCellValue("J{$row}", $item->grant_level_of_effort);
                $sheet->setCellValue("K{$row}", $item->grant_position_number);
                $sheet->setCellValue("L{$row}", $grant->status);
                $row++;
            }
        }
    }

    /**
     * Set column widths
     */
    private function setColumnWidths($sheet): void
    {
        $columnWidths = [
            'A' => 12, 'B' => 15, 'C' => 30, 'D' => 18,
            'E' => 15, 'F' => 25, 'G' => 18, 'H' => 15,
            'I' => 15, 'J' => 18, 'K' => 15, 'L' => 15,
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
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
            } elseif (in_array($row, [3, 7, 11, 15, 19, 28])) {
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
        $tempFile = tempnam(sys_get_temp_dir(), 'grant_items_ref_');
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Get the instructions content
     */
    private function getInstructionsContent(): array
    {
        return [
            'Grant Items Reference - How to Use',
            '',
            'QUICK START:',
            'Look for the GREEN column (Column E) - that\'s the "Grant Item ID" you need!',
            'Copy this ID to your funding allocation import template.',
            '',
            'PURPOSE:',
            'This file contains all available grants and their grant items with IDs.',
            'Use this reference when filling out the Employee Funding Allocation import template.',
            '',
            'HOW TO USE:',
            '1. Find the grant you want to allocate funding from',
            '2. Locate the specific grant item (position) within that grant',
            '3. Copy the "Grant Item ID" from Column E (GREEN HIGHLIGHTED) to your import file',
            '',
            'COLOR CODING:',
            'GREEN COLUMN (E) = Grant Item ID - THIS IS WHAT YOU NEED!',
            'BLUE COLUMNS = Reference information to help you find the right grant item',
            '',
            'IMPORTANT NOTES:',
            '- Grant Item ID is required for funding allocation imports',
            '- Each grant item represents a specific position/funding source',
            '- Position Number shows how many employees can be allocated to this grant item',
            '- Grant Status shows if the grant is Active, Expired, or Ending Soon',
            '',
            'COLUMNS EXPLAINED:',
            '- Grant ID: Unique identifier for the grant',
            '- Grant Code: Short code for the grant',
            '- Grant Name: Full name of the grant',
            '- Grant Organization: Organization managing the grant (SMRU/BHF)',
            '- Grant Item ID: ID to use in funding allocation imports (REQUIRED)',
            '- Grant Position: Position title for this grant item',
            '- Budget Line Code: Budget line code for accounting',
            '- Grant Salary: Budgeted salary for this position',
            '- Grant Benefit: Budgeted benefits for this position',
            '- Level of Effort: Expected effort percentage',
            '- Position Number: Maximum number of employees for this position',
            '- Grant Status: Current status of the grant',
        ];
    }
}
