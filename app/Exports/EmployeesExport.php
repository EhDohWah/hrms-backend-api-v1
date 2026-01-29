<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use RegistersEventListeners;

    /**
     * Reverse mapping from database values to display values for identification type
     */
    const IDENTIFICATION_TYPE_REVERSE_MAPPING = [
        '10YearsID' => '10 years ID',
        'BurmeseID' => 'Burmese ID',
        'ThaiID' => 'Thai ID',
        'CI' => 'CI',
        'Borderpass' => 'Borderpass',
        'Passport' => 'Passport',
        'Other' => 'Other',
    ];

    protected $organization;

    protected $status;

    public function __construct(?string $organization = null, ?string $status = null)
    {
        $this->organization = $organization;
        $this->status = $status;
    }

    public function query()
    {
        $query = Employee::query();

        if ($this->organization) {
            $query->where('organization', $this->organization);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->with('employeeBeneficiaries');
    }

    public function headings(): array
    {
        return [
            'Org',
            'Staff ID',
            'Initial',
            'First Name',
            'Last Name',
            'Initial (TH)',
            'First Name (TH)',
            'Last Name (TH)',
            'Gender',
            'Date of Birth',
            'Age',
            'Status',
            'Nationality',
            'Religion',
            'ID Type',
            'ID Number',
            'ID Issue Date',
            'ID Expiry Date',
            'Social Security No',
            'Tax No',
            'Driver License',
            'Bank Name',
            'Bank Branch',
            'Bank Account Name',
            'Bank Account No',
            'Mobile No',
            'Current Address',
            'Permanent Address',
            'Marital Status',
            'Spouse Name',
            'Spouse Mobile No',
            'Emergency Contact Name',
            'Relationship',
            'Emergency Mobile No',
            'Father Name',
            'Father Occupation',
            'Father Mobile No',
            'Mother Name',
            'Mother Occupation',
            'Mother Mobile No',
            'Kin 1 Name',
            'Kin 1 Relationship',
            'Kin 1 Mobile',
            'Kin 2 Name',
            'Kin 2 Relationship',
            'Kin 2 Mobile',
            'Military Status',
            'Remark',
        ];
    }

    public function map($employee): array
    {
        // Get beneficiaries
        $kin1 = $employee->employeeBeneficiaries->get(0);
        $kin2 = $employee->employeeBeneficiaries->get(1);

        // Reverse map identification type from database value to display value
        $identificationTypeDisplay = null;
        if ($employee->identification_type) {
            $identificationTypeDisplay = self::IDENTIFICATION_TYPE_REVERSE_MAPPING[$employee->identification_type] ?? $employee->identification_type;
        }

        // Convert military_status Boolean to Yes/No for export
        $militaryStatusDisplay = '';
        if ($employee->military_status === true) {
            $militaryStatusDisplay = 'Yes';
        } elseif ($employee->military_status === false) {
            $militaryStatusDisplay = 'No';
        }

        return [
            $employee->organization,
            $employee->staff_id,
            $employee->initial_en,
            $employee->first_name_en,
            $employee->last_name_en,
            $employee->initial_th,
            $employee->first_name_th,
            $employee->last_name_th,
            $employee->gender,
            $employee->date_of_birth,
            '', // age placeholder - formula will be added in AfterSheet event
            $employee->status,
            $employee->nationality,
            $employee->religion,
            $identificationTypeDisplay, // Reverse mapped display value
            $employee->identification_number, // Direct column now
            $employee->identification_issue_date,
            $employee->identification_expiry_date,
            $employee->social_security_number,
            $employee->tax_number,
            $employee->driver_license_number,
            $employee->bank_name,
            $employee->bank_branch,
            $employee->bank_account_name,
            $employee->bank_account_number,
            $employee->mobile_phone,
            $employee->current_address,
            $employee->permanent_address,
            $employee->marital_status,
            $employee->spouse_name,
            $employee->spouse_phone_number,
            $employee->emergency_contact_person_name,
            $employee->emergency_contact_person_relationship,
            $employee->emergency_contact_person_phone,
            $employee->father_name,
            $employee->father_occupation,
            $employee->father_phone_number,
            $employee->mother_name,
            $employee->mother_occupation,
            $employee->mother_phone_number,
            $kin1 ? $kin1->beneficiary_name : null,
            $kin1 ? $kin1->beneficiary_relationship : null,
            $kin1 ? $kin1->phone_number : null,
            $kin2 ? $kin2->beneficiary_name : null,
            $kin2 ? $kin2->beneficiary_relationship : null,
            $kin2 ? $kin2->phone_number : null,
            $militaryStatusDisplay,
            $employee->remark,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'J' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // date_of_birth column
            'Q' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // identification_issue_date column
            'R' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // identification_expiry_date column
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Insert validation rules row at row 2
                $sheet->insertNewRowBefore(2, 1);
                $this->addValidationRulesRow($sheet);

                // Add age formulas starting from row 3 (data starts row 3 after insertion)
                $this->addAgeFormulas($sheet, $highestRow + 1);

                // Add Excel dropdowns for data validation
                $this->addDropdownValidations($sheet, $highestRow + 1);

                // Style validation row
                $sheet->getStyle('1:2')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(2)->setRowHeight(30);
                $sheet->getStyle('2:2')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '666666']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF9E6']],
                ]);

                // Freeze panes at row 3
                $sheet->freezePane('A3');
            },
        ];
    }

    protected function addValidationRulesRow($sheet)
    {
        $validationRules = [
            'REQUIRED - Must be SMRU or BHF',
            'REQUIRED - Min 3 chars - Max 50 chars - Unique per org',
            'OPTIONAL - Max 10 chars',
            'REQUIRED - Min 2 chars - Max 255 chars',
            'OPTIONAL - Max 255 chars',
            'OPTIONAL - Max 10 chars',
            'OPTIONAL - Max 255 chars',
            'OPTIONAL - Max 255 chars',
            'REQUIRED - M or F',
            'REQUIRED - Format YYYY-MM-DD - Age 18-84',
            'AUTO-CALCULATED - Formula',
            'REQUIRED - Expats (Local), Local ID Staff, or Local non ID Staff',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Select from dropdown',
            'OPTIONAL - Required if identification type provided',
            'OPTIONAL - Format YYYY-MM-DD',
            'OPTIONAL - Format YYYY-MM-DD - Must be after issue date',
            'OPTIONAL - Max 50 chars',
            'OPTIONAL - Max 50 chars',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Max 50 chars',
            'OPTIONAL - Max 20 chars - 10+ digits',
            'OPTIONAL - Text',
            'OPTIONAL - Text',
            'OPTIONAL - Single, Married, Divorced, Widowed',
            'OPTIONAL - Required if married',
            'OPTIONAL - Max 20 chars',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Max 100 chars',
            'OPTIONAL - Max 20 chars',
            'OPTIONAL - Max 200 chars',
            'OPTIONAL - Max 200 chars',
            'OPTIONAL - Max 20 chars',
            'OPTIONAL - Max 200 chars',
            'OPTIONAL - Max 200 chars',
            'OPTIONAL - Max 20 chars',
            'OPTIONAL - Max 255 chars',
            'OPTIONAL - Max 255 chars - Required if name provided',
            'OPTIONAL - Max 20 chars',
            'OPTIONAL - Max 255 chars',
            'OPTIONAL - Max 255 chars - Required if name provided',
            'OPTIONAL - Max 20 chars',
            'OPTIONAL - Yes or No',
            'OPTIONAL - Max 255 chars',
        ];

        $column = 'A';
        foreach ($validationRules as $rule) {
            $sheet->setCellValue($column.'2', $rule);
            $column++;
        }
    }

    protected function addAgeFormulas($sheet, $maxRow)
    {
        for ($row = 3; $row <= $maxRow; $row++) {
            $sheet->setCellValue('K'.$row, '=DATEDIF(J'.$row.',TODAY(),"Y")');
        }
    }

    protected function addDropdownValidations($sheet, $maxRow)
    {
        // Organization dropdown (Column A)
        $this->addDropdown($sheet, 'A3:A'.$maxRow, 'SMRU,BHF', 'Organization', 'Select SMRU or BHF');

        // Gender dropdown (Column I)
        $this->addDropdown($sheet, 'I3:I'.$maxRow, 'M,F', 'Gender', 'Select M or F');

        // Status dropdown (Column L)
        $this->addDropdown($sheet, 'L3:L'.$maxRow, '"Expats (Local)","Local ID Staff","Local non ID Staff"', 'Status', 'Select employee status');

        // Identification Type dropdown (Column O)
        $this->addDropdown($sheet, 'O3:O'.$maxRow, '"10 years ID","Burmese ID",CI,Borderpass,"Thai ID",Passport,Other', 'Identification Type', 'Select identification type');

        // Marital Status dropdown (Column AC - shifted by 2 due to new ID date columns)
        $this->addDropdown($sheet, 'AC3:AC'.$maxRow, 'Single,Married,Divorced,Widowed', 'Marital Status', 'Select marital status');

        // Military Status dropdown (Column AU - shifted by 2 due to new ID date columns)
        $this->addDropdown($sheet, 'AU3:AU'.$maxRow, 'Yes,No', 'Military Status', 'Select Yes or No');
    }

    protected function addDropdown($sheet, $range, $options, $title, $prompt)
    {
        $validation = $sheet->getCell(explode(':', $range)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid '.$title);
        $validation->setError('Please select a valid value from the dropdown');
        $validation->setPromptTitle($title);
        $validation->setPrompt($prompt);
        $validation->setFormula1($options);

        // Apply to entire range
        $startCell = explode(':', $range)[0];
        $endCell = explode(':', $range)[1];
        $colLetter = preg_replace('/[0-9]/', '', $startCell);
        $startRow = (int) preg_replace('/[A-Z]/', '', $startCell);
        $endRow = (int) preg_replace('/[A-Z]/', '', $endCell);

        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getCell($colLetter.$row)->setDataValidation(clone $validation);
        }
    }
}
