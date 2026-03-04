<?php

namespace App\Services;

use App\Enums\FundingAllocationStatus;
use App\Exceptions\Payroll\BudgetHistoryDateRangeException;
use App\Http\Resources\PayrollCalculationResource;
use App\Models\BenefitSetting;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\InterOrganizationAdvance;
use App\Models\Payroll;
use App\Models\PayrollPolicySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollService
{
    protected TaxCalculationService $taxService;

    public function __construct(?int $taxYear = null)
    {
        $this->taxService = new TaxCalculationService($taxYear ?? date('Y'));
    }

    // =========================================================================
    // CONTROLLER-LEVEL CRUD METHODS
    // =========================================================================

    /**
     * List payrolls with filtering, sorting, and pagination.
     */
    public function list(array $params): array
    {
        $perPage = $params['per_page'] ?? 10;
        $page = $params['page'] ?? 1;

        $query = Payroll::forPagination()->withOptimizedRelations();

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->whereHas('employment.employee', function ($q) use ($searchTerm) {
                $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
            });
        }

        if (! empty($params['filter_organization'])) {
            $query->byOrganization($params['filter_organization']);
        }

        if (! empty($params['filter_department'])) {
            $query->byDepartment($params['filter_department']);
        }

        if (! empty($params['filter_position'])) {
            $positions = array_map('trim', explode(',', $params['filter_position']));
            $query->whereHas('employment.position', function ($q) use ($positions) {
                $q->whereIn('title', $positions);
            });
        }

        if (! empty($params['filter_date_range'])) {
            $query->byPayPeriodDate($params['filter_date_range']);
        } elseif (! empty($params['filter_payslip_date'])) {
            $query->byPayPeriodDate($params['filter_payslip_date']);
        }

        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'last_7_days':
                $query->where('pay_period_date', '>=', now()->subDays(7))->orderBy('pay_period_date', 'desc');
                break;
            case 'last_month':
                $query->where('pay_period_date', '>=', now()->subMonth())->orderBy('pay_period_date', 'desc');
                break;
            case 'recently_added':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderByField($sortBy, $sortOrder);
                break;
        }

        $payrolls = $query->paginate($perPage, ['*'], 'page', $page);

        $appliedFilters = [];
        if (! empty($params['search'])) {
            $appliedFilters['search'] = $params['search'];
        }
        if (! empty($params['filter_organization'])) {
            $appliedFilters['organization'] = explode(',', $params['filter_organization']);
        }
        if (! empty($params['filter_department'])) {
            $appliedFilters['department'] = explode(',', $params['filter_department']);
        }
        if (! empty($params['filter_position'])) {
            $appliedFilters['position'] = explode(',', $params['filter_position']);
        }
        if (! empty($params['filter_date_range'])) {
            $appliedFilters['date_range'] = $params['filter_date_range'];
        } elseif (! empty($params['filter_payslip_date'])) {
            $appliedFilters['payslip_date'] = $params['filter_payslip_date'];
        }

        return [
            'paginator' => $payrolls,
            'applied_filters' => $appliedFilters,
        ];
    }

    /**
     * Search payroll records by staff ID or name.
     */
    public function search(array $params): ?array
    {
        $perPage = $params['per_page'] ?? 10;
        $page = $params['page'] ?? 1;
        $searchTerm = $params['search'] ?? $params['staff_id'] ?? null;

        $query = Payroll::forPagination()->withOptimizedRelations();

        if ($searchTerm) {
            $query->whereHas('employment.employee', function ($q) use ($searchTerm) {
                $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
            });
        }

        $query->orderBy('pay_period_date', 'desc');
        $payrolls = $query->paginate($perPage, ['*'], 'page', $page);

        if ($payrolls->isEmpty()) {
            return null;
        }

        $firstPayroll = $payrolls->items()[0] ?? null;
        $employeeInfo = null;

        if ($firstPayroll && $firstPayroll->employment && $firstPayroll->employment->employee) {
            $employee = $firstPayroll->employment->employee;
            $employeeInfo = [
                'id' => $employee->id,
                'staff_id' => $employee->staff_id,
                'first_name_en' => $employee->first_name_en,
                'last_name_en' => $employee->last_name_en,
                'organization' => $employee->organization,
                'employment' => [
                    'id' => $firstPayroll->employment->id,
                    'department' => [
                        'id' => $firstPayroll->employment->department->id ?? null,
                        'name' => $firstPayroll->employment->department->name ?? null,
                    ],
                    'position' => [
                        'id' => $firstPayroll->employment->position->id ?? null,
                        'title' => $firstPayroll->employment->position->title ?? null,
                    ],
                ],
            ];
        }

        return [
            'search_term' => $searchTerm,
            'employee_info' => $employeeInfo,
            'paginator' => $payrolls,
        ];
    }

    /**
     * Update an existing payroll record.
     */
    public function update(Payroll $payroll, array $data): Payroll
    {
        $payroll->update($data);

        return $payroll->fresh();
    }

    /**
     * Soft-delete a payroll record.
     */
    public function destroy(Payroll $payroll): void
    {
        $payroll->delete();
    }

    /**
     * Get tax summary for a payroll record.
     */
    public function taxSummary(Payroll $payroll): array
    {
        $payroll->load('employment.employee');
        $employee = $payroll->employment->employee;

        $taxYear = date('Y', strtotime($payroll->pay_period_date));
        $taxService = new TaxCalculationService($taxYear);

        $calculation = $taxService->calculatePayroll(
            $employee->id,
            floatval($payroll->gross_salary),
            [],
            []
        );

        return [
            'payroll_id' => $payroll->id,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->first_name_en.' '.$employee->last_name_en,
                'staff_id' => $employee->staff_id,
            ],
            'pay_period' => $payroll->pay_period_date,
            'tax_calculation' => new PayrollCalculationResource($calculation),
        ];
    }

    /**
     * Get budget history grouped by employee and grant allocation.
     */
    public function budgetHistory(array $params): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $params['start_date'])->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $params['end_date'])->endOfMonth();

        $startMonth = Carbon::createFromFormat('Y-m', $params['start_date'])->startOfMonth();
        $endMonth = Carbon::createFromFormat('Y-m', $params['end_date'])->startOfMonth();
        $monthsDiff = $startMonth->diffInMonths($endMonth) + 1;

        if ($monthsDiff > 12) {
            throw new BudgetHistoryDateRangeException;
        }

        $query = Payroll::query()
            ->select(['id', 'employment_id', 'employee_funding_allocation_id', 'gross_salary', 'net_salary', 'pay_period_date'])
            ->with([
                'employment.employee:id,staff_id,first_name_en,last_name_en,organization',
                'employment.department:id,name',
                'employeeFundingAllocation:id,fte,grant_item_id',
                'employeeFundingAllocation.grantItem:id,grant_id',
                'employeeFundingAllocation.grantItem.grant:id,name,code',
            ])
            ->whereBetween('pay_period_date', [$startDate, $endDate]);

        if (! empty($params['organization'])) {
            $query->whereHas('employment.employee', fn ($q) => $q->where('organization', $params['organization']));
        }

        if (! empty($params['department'])) {
            $query->whereHas('employment.department', fn ($q) => $q->where('name', $params['department']));
        }

        $payrolls = $query->get();
        $grouped = [];

        foreach ($payrolls as $payroll) {
            $employmentId = $payroll->employment_id;
            $allocationId = $payroll->employee_funding_allocation_id;
            $key = "{$employmentId}_{$allocationId}";

            if (! isset($grouped[$key])) {
                $grantName = $payroll->employeeFundingAllocation?->grantItem?->grant?->name ?? 'N/A';

                $grouped[$key] = [
                    'employment_id' => $employmentId,
                    'employee_funding_allocation_id' => $allocationId,
                    'employee_name' => $this->getEmployeeNameFromPayroll($payroll),
                    'staff_id' => $payroll->employment->employee->staff_id ?? 'N/A',
                    'organization' => $payroll->employment->employee->organization ?? 'N/A',
                    'department' => $payroll->employment->department->name ?? 'N/A',
                    'grant_name' => $grantName,
                    'fte' => $payroll->employeeFundingAllocation->fte ?? 0,
                    'monthly_data' => [],
                ];
            }

            $monthKey = Carbon::parse($payroll->pay_period_date)->format('Y-m');
            $grouped[$key]['monthly_data'][$monthKey] = [
                'gross_salary' => $payroll->gross_salary,
                'net_salary' => $payroll->net_salary,
            ];
        }

        $data = array_values($grouped);
        $perPage = $params['per_page'] ?? 50;
        $page = $params['page'] ?? 1;
        $total = count($data);
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($data, $offset, $perPage);

        return [
            'data' => $paginatedData,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
            'date_range' => [
                'start_date' => $startDate->format('Y-m'),
                'end_date' => $endDate->format('Y-m'),
                'months' => $this->generateMonthsList($startDate, $endDate),
            ],
        ];
    }

    /**
     * Generate payslip PDF for a payroll record.
     */
    public function generatePayslip(Payroll $payroll)
    {
        $payroll->load([
            'employment.employee',
            'employment.department:id,name',
            'employment.position:id,title',
            'employment.site:id,name',
            'employeeFundingAllocation.grantItem.grant',
            'grantAllocations',
        ]);

        $data = $this->buildPayslipData($payroll);
        $employee = $data['employee'];

        // Select template based on employee's organization (SMRU or BHF)
        $view = $employee?->organization === 'BHF' ? 'pdf.bhf-payslip' : 'pdf.smru-payslip';
        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper('a5', 'landscape');

        $staffId = $employee?->staff_id ?? 'unknown';
        $grantCode = $data['grantCode'];
        $period = Carbon::parse($payroll->pay_period_date)->format('Y-m');
        $filename = "payslip-{$staffId}-{$grantCode}-{$period}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Generate a combined PDF of all payslips for an organisation and pay month.
     * One A5-landscape page per payroll record, ordered by employment_id.
     *
     * @param  string  $organization   'SMRU' or 'BHF'
     * @param  string  $payPeriodDate  Pay period in 'YYYY-MM' format (e.g. '2025-02')
     */
    public function generateBulkPayslips(string $organization, string $payPeriodDate)
    {
        // Extend execution time — dompdf is CPU-intensive for large batches
        set_time_limit(300);

        $periodDate = Carbon::createFromFormat('Y-m', $payPeriodDate);

        $payrolls = Payroll::query()
            ->whereHas('employment.employee', fn ($q) => $q->where('organization', $organization))
            ->whereYear('pay_period_date', $periodDate->year)
            ->whereMonth('pay_period_date', $periodDate->month)
            ->with([
                'employment.employee',
                'employment.department:id,name',
                'employment.position:id,title',
                'employment.site:id,name',
                'employeeFundingAllocation.grantItem.grant',
                'grantAllocations',
            ])
            ->orderBy('employment_id')
            ->get();

        if ($payrolls->isEmpty()) {
            abort(404, "No payroll records found for {$organization} in {$periodDate->format('F Y')}.");
        }

        // Build the per-page data array for every payroll record
        $payslips = $payrolls->map(fn ($payroll) => $this->buildPayslipData($payroll))->all();

        $pdf = Pdf::loadView('pdf.bulk-payslip', [
            'payslips'     => $payslips,
            'organization' => $organization,
            'period'       => $periodDate->format('F Y'),
        ]);
        $pdf->setPaper('a5', 'landscape');

        $filename = "payslips-{$organization}-{$periodDate->format('Y-m')}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Build the view data array for a single payroll record.
     * Shared by generatePayslip() and generateBulkPayslips().
     * Relationships must be loaded on $payroll before calling this.
     */
    private function buildPayslipData(Payroll $payroll): array
    {
        $employee = $payroll->employment?->employee;
        $employment = $payroll->employment;

        // Two-tier fallback: historical snapshot → live funding chain → 'N/A'
        $grantAllocation = $payroll->grantAllocations->first();
        $fundingAllocation = $payroll->employeeFundingAllocation;

        $grantCode = $grantAllocation?->grant_code ?? $fundingAllocation?->grantItem?->grant?->code ?? 'N/A';
        $grantName = $grantAllocation?->grant_name ?? $fundingAllocation?->grantItem?->grant?->name ?? 'N/A';
        $budgetLineCode = $grantAllocation?->budget_line_code ?? $fundingAllocation?->grantItem?->budgetline_code ?? 'N/A';
        $grantPosition = $grantAllocation?->grant_position ?? $fundingAllocation?->grantItem?->grant_position ?? 'N/A';
        $fte = $grantAllocation?->fte ?? $fundingAllocation?->fte ?? 0;

        return [
            'payroll'        => $payroll,
            'employee'       => $employee,
            'employment'     => $employment,
            'department'     => $employment?->department?->name ?? 'N/A',
            'position'       => $employment?->position?->title ?? 'N/A',
            'site'           => $employment?->site?->name ?? 'N/A',
            'grantCode'      => $grantCode,
            'grantName'      => $grantName,
            'budgetLineCode' => $budgetLineCode,
            'grantPosition'  => $grantPosition,
            'fte'            => $fte,
            'ftePercentage'  => round((float) $fte * 100, 2),
            'payPeriod'      => Carbon::parse($payroll->pay_period_date)->format('F Y'),
        ];
    }

    /**
     * Upload payroll data from Excel file.
     */
    public function upload($file, int $userId): string
    {
        $importId = 'payroll_'.str()->uuid();

        (new \App\Imports\PayrollsImport($importId, $userId))->queue($file);

        return $importId;
    }

    /**
     * Download payroll import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Import');

        $headers = [
            'staff_id', 'employee_funding_allocation_id', 'pay_period_date',
            'gross_salary', 'gross_salary_by_FTE', 'retroactive_adjustment',
            'thirteen_month_salary', 'thirteen_month_salary_accured', 'pvd',
            'saving_fund', 'employer_social_security', 'employee_social_security',
            'employer_health_welfare', 'employee_health_welfare', 'tax',
            'net_salary', 'total_salary', 'total_pvd', 'total_saving_fund',
            'salary_bonus', 'total_income', 'employer_contribution', 'total_deduction', 'notes',
        ];

        $validationRules = [
            'String - NOT NULL - Employee staff ID (must exist in system)',
            'Integer - NOT NULL - Employee funding allocation ID (must exist)',
            'Date (YYYY-MM-DD) - NOT NULL - Pay period date',
            'Decimal(15,2) - NOT NULL - Gross salary amount',
            'Decimal(15,2) - NOT NULL - Gross salary by FTE',
            'Decimal(15,2) - NULLABLE - Compensation refund',
            'Decimal(15,2) - NULLABLE - 13th month salary',
            'Decimal(15,2) - NULLABLE - 13th month salary accrued',
            'Decimal(15,2) - NULLABLE - Provident fund (PVD)',
            'Decimal(15,2) - NULLABLE - Saving fund',
            'Decimal(15,2) - NULLABLE - Employer social security',
            'Decimal(15,2) - NULLABLE - Employee social security',
            'Decimal(15,2) - NULLABLE - Employer health welfare',
            'Decimal(15,2) - NULLABLE - Employee health welfare',
            'Decimal(15,2) - NULLABLE - Tax amount',
            'Decimal(15,2) - NOT NULL - Net salary',
            'Decimal(15,2) - NULLABLE - Total salary',
            'Decimal(15,2) - NULLABLE - Total PVD',
            'Decimal(15,2) - NULLABLE - Total saving fund',
            'Decimal(15,2) - NULLABLE - Salary bonus',
            'Decimal(15,2) - NULLABLE - Total income',
            'Decimal(15,2) - NULLABLE - Employer contribution',
            'Decimal(15,2) - NULLABLE - Total deduction',
            'String - NULLABLE - Notes for payslip',
        ];

        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($col, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true)->setSize(11);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
            $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        $col = 1;
        foreach ($validationRules as $rule) {
            $cell = $sheet->getCellByColumnAndRow($col, 2);
            $cell->setValue($rule);
            $cell->getStyle()->getFont()->setItalic(true)->setSize(9);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E7E6E6');
            $cell->getStyle()->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            $col++;
        }

        $sheet->getRowDimension(2)->setRowHeight(60);

        $sampleData = [
            ['EMP001', '1', '2025-01-01', '50000.00', '50000.00', '0.00', '0.00', '4166.67', '3750.00', '0.00', '750.00', '750.00', '0.00', '0.00', '5000.00', '41250.00', '50000.00', '3750.00', '0.00', '0.00', '50000.00', '4500.00', '9500.00', 'Regular monthly salary'],
            ['EMP002', '2', '2025-01-01', '60000.00', '36000.00', '0.00', '0.00', '5000.00', '4500.00', '0.00', '900.00', '900.00', '0.00', '0.00', '6500.00', '49000.00', '60000.00', '4500.00', '0.00', '0.00', '60000.00', '5400.00', '11900.00', '60% FTE allocation'],
            ['EMP003', '3', '2025-01-01', '45000.00', '45000.00', '0.00', '0.00', '3750.00', '3375.00', '0.00', '675.00', '675.00', '0.00', '0.00', '4000.00', '37625.00', '45000.00', '3375.00', '0.00', '0.00', '45000.00', '4050.00', '8425.00', 'Probation period'],
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

        $columnWidths = [
            'A' => 15, 'B' => 22, 'C' => 18, 'D' => 15, 'E' => 18, 'F' => 22,
            'G' => 20, 'H' => 25, 'I' => 12, 'J' => 15, 'K' => 22, 'L' => 22,
            'M' => 22, 'N' => 22, 'O' => 12, 'P' => 15, 'Q' => 15, 'R' => 15,
            'S' => 18, 'T' => 15, 'U' => 15, 'V' => 20, 'W' => 18, 'X' => 30,
        ];
        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $instructionsSheet = $spreadsheet->createSheet();
        $instructionsSheet->setTitle('Instructions');

        $instructions = [
            ['Payroll Import Template - Instructions'], [''],
            ['IMPORTANT NOTES:'],
            ['1. Each row creates a NEW payroll record (no duplicate detection)'],
            ['2. One employee can have multiple payroll records per month (one per funding allocation)'],
            ['3. All monetary values will be encrypted automatically for security'],
            ['4. Provide all calculated values - no auto-calculations performed'],
            [''], ['REQUIRED FIELDS:'],
            ['- staff_id: Employee staff ID (must exist in system)'],
            ['- employee_funding_allocation_id: Funding allocation ID'],
            ['- pay_period_date: Pay period date (YYYY-MM-DD format)'],
            ['- gross_salary: Gross salary amount'],
            ['- gross_salary_by_FTE: Gross salary adjusted by FTE'],
            ['- net_salary: Net salary after deductions'],
            [''], ['OPTIONAL FIELDS:'],
            ['- All other salary components and calculations'],
            ['- notes: Additional notes for the payslip'],
            [''], ['DATE FORMAT:'],
            ['- Use YYYY-MM-DD format (e.g., 2025-01-01)'],
            ['- Excel may auto-format dates - ensure they are correct'],
            [''], ['NUMERIC VALUES:'],
            ['- Use decimal format (e.g., 50000.00)'],
            ['- Do not use currency symbols'],
            ['- Commas are optional (will be removed automatically)'],
            [''], ['MULTIPLE ALLOCATIONS:'],
            ['- If employee has 2 funding allocations, create 2 rows'],
            ['- Each row should have different employee_funding_allocation_id'],
            ['- Both rows can have same pay_period_date'],
        ];

        $row = 1;
        foreach ($instructions as $instruction) {
            $instructionsSheet->setCellValue('A'.$row, $instruction[0]);
            if ($row === 1) {
                $instructionsSheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
            } elseif (in_array($row, [3, 9, 17, 21, 24, 27])) {
                $instructionsSheet->getStyle('A'.$row)->getFont()->setBold(true);
            }
            $row++;
        }

        $instructionsSheet->getColumnDimension('A')->setWidth(80);
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'payroll_import_template_'.date('Y-m-d_His').'.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'payroll_template_');
        $writer->save($tempFile);

        $downloadHeaders = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ];

        return response()->download($tempFile, $filename, $downloadHeaders)->deleteFileAfterSend(true);
    }

    /**
     * Download employee funding allocations reference file.
     */
    public function downloadAllocationsReference(): BinaryFileResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Funding Allocations Ref');

        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'IMPORTANT: Copy the "Funding Allocation ID" (Column A - Green) to your Payroll Import Template');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FF6B6B');
        $sheet->getStyle('A1')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $refHeaders = [
            'Funding Allocation ID', 'Staff ID', 'Employee Name', 'Grant Code',
            'Grant Name', 'Grant Position', 'FTE (%)', 'Allocated Amount',
            'Status', 'Organization',
        ];

        $col = 1;
        foreach ($refHeaders as $header) {
            $cell = $sheet->getCellByColumnAndRow($col, 2);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true)->setSize(11);

            if ($header === 'Funding Allocation ID') {
                $cell->getStyle()->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('28A745');
                $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                $cell->getStyle()->getFont()->setSize(12)->setBold(true);
            } else {
                $cell->getStyle()->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('4472C4');
                $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
            }

            $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        $allocations = EmployeeFundingAllocation::with([
            'employee:id,staff_id,first_name_en,last_name_en,organization',
            'grantItem.grant:id,name,code',
        ])
            ->where('status', FundingAllocationStatus::Active)
            ->orderBy('employee_id')
            ->get();

        $row = 3;
        foreach ($allocations as $allocation) {
            $sheet->setCellValue("A{$row}", $allocation->id);
            $sheet->getStyle("A{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D4EDDA');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('155724');
            $sheet->getStyle("A{$row}")->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
                ->getColor()->setRGB('28A745');

            $sheet->setCellValue("B{$row}", $allocation->employee->staff_id ?? 'N/A');
            $sheet->setCellValue("C{$row}", ($allocation->employee->first_name_en ?? '').' '.($allocation->employee->last_name_en ?? ''));
            $sheet->setCellValue("D{$row}", $allocation->grantItem->grant->code ?? 'N/A');
            $sheet->setCellValue("E{$row}", $allocation->grantItem->grant->name ?? 'N/A');
            $sheet->setCellValue("F{$row}", $allocation->grantItem->grant_position ?? 'N/A');
            $sheet->setCellValue("G{$row}", round($allocation->fte * 100, 2));
            $sheet->setCellValue("H{$row}", $allocation->allocated_amount);
            $sheet->setCellValue("I{$row}", ucfirst($allocation->status));
            $sheet->setCellValue("J{$row}", $allocation->employee->organization ?? 'N/A');

            $row++;
        }

        $refColumnWidths = [
            'A' => 20, 'B' => 15, 'C' => 25, 'D' => 15, 'E' => 30, 'F' => 25,
            'G' => 12, 'H' => 18, 'I' => 12, 'J' => 15,
        ];
        foreach ($refColumnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $instructionsSheet = $spreadsheet->createSheet();
        $instructionsSheet->setTitle('Instructions');
        $instructions = [
            ['Employee Funding Allocations Reference - How to Use'], [''],
            ['QUICK START:'],
            ['Look for the GREEN column (Column A) - that\'s the "Funding Allocation ID" you need!'],
            ['Copy this ID to your payroll import template.'],
            [''], ['PURPOSE:'],
            ['This file contains all active employee funding allocations with their IDs.'],
            ['Use this reference when filling out the Payroll import template.'],
            ['Each employee may have multiple funding allocations (split funding).'],
            [''], ['HOW TO USE:'],
            ['1. Find the employee you want to create payroll for (by Staff ID or Name)'],
            ['2. Identify which funding allocation to use (check Grant Code and Position)'],
            ['3. Copy the "Funding Allocation ID" from Column A (GREEN HIGHLIGHTED) to your payroll import'],
            ['4. If an employee has multiple allocations, create separate payroll rows for each'],
        ];
        $row = 1;
        foreach ($instructions as $instruction) {
            $instructionsSheet->setCellValue("A{$row}", $instruction[0]);
            if ($row === 1) {
                $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
            } elseif (in_array($row, [3, 7, 11])) {
                $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            }
            $row++;
        }
        $instructionsSheet->getColumnDimension('A')->setWidth(100);
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'employee_funding_allocations_reference_'.date('Y-m-d_His').'.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'funding_alloc_ref_');
        $writer->save($tempFile);

        $downloadHeaders = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ];

        return response()->download($tempFile, $filename, $downloadHeaders)->deleteFileAfterSend(true);
    }

    /**
     * Get employee name from payroll record.
     */
    private function getEmployeeNameFromPayroll($payroll): string
    {
        if ($payroll->employment && $payroll->employment->employee) {
            $firstName = $payroll->employment->employee->first_name_en ?? '';
            $lastName = $payroll->employment->employee->last_name_en ?? '';

            return trim("{$firstName} {$lastName}") ?: 'N/A';
        }

        return 'N/A';
    }

    /**
     * Generate list of months between start and end date.
     */
    private function generateMonthsList(Carbon $startDate, Carbon $endDate): array
    {
        $months = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->format('M Y'),
            ];
            $current->addMonth();
        }

        return $months;
    }

    /**
     * Calculate payroll for a specific funding allocation (public method for controller)
     */
    public function calculateAllocationPayrollForController(
        Employee $employee,
        EmployeeFundingAllocation $allocation,
        Carbon $payPeriodDate,
        bool $isTaxAllocation = true
    ): array {
        return $this->calculateAllocationPayroll($employee, $allocation, $payPeriodDate, $isTaxAllocation);
    }

    /**
     * Determine which allocation bears the tax for an employee.
     * Business rule: tax is deducted from ONE grant only — the allocation with the highest FTE.
     * Tie-breaker: lowest allocation ID (oldest).
     *
     * @param  \Illuminate\Support\Collection  $allocations  Active funding allocations
     * @return int|null  The allocation ID that should bear all tax, or null if empty
     */
    public function determineTaxAllocationId($allocations): ?int
    {
        if ($allocations->isEmpty()) {
            return null;
        }

        // Highest FTE first, then lowest ID as tie-breaker
        return $allocations
            ->sortBy('id')
            ->sortByDesc('fte')
            ->first()
            ->id;
    }

    /**
     * Calculate 13th-month-only payroll for a historical (inactive/closed) allocation.
     *
     * In December, allocations that were active earlier in the year but are now
     * inactive/closed still need their own 13th month salary record. This method
     * queries all payroll records for the allocation in the year and computes
     * only the 13th month portion — all other salary fields are zero.
     *
     * Returns the same array structure as calculateAllocationPayroll() so
     * preparePayrollRecord() can consume it without changes, or null if
     * no 13th month is owed (no YTD payrolls, amount is 0, or already paid).
     *
     * @param  Employee  $employee  Employee model
     * @param  EmployeeFundingAllocation  $allocation  Historical allocation
     * @param  Carbon  $payPeriodDate  Pay period date (must be December)
     * @return array|null Payroll data array or null if nothing to pay
     */
    public function calculateHistoricalAllocation13thMonth(
        Employee $employee,
        EmployeeFundingAllocation $allocation,
        Carbon $payPeriodDate
    ): ?array {
        $employment = $employee->employment;

        // Only applies in December
        if ($payPeriodDate->month !== 12) {
            return null;
        }

        // Check policy
        $policy = PayrollPolicySetting::getActivePolicy();
        $enabled = $policy?->thirteenth_month_enabled ?? true;
        if (! $enabled) {
            return null;
        }

        // 13th month is eligible from start_date — no probation gate.

        // Duplicate check: skip if a December payroll already exists for this allocation
        $existingDecember = Payroll::where('employment_id', $employment->id)
            ->where('employee_funding_allocation_id', $allocation->id)
            ->whereYear('pay_period_date', $payPeriodDate->year)
            ->whereMonth('pay_period_date', 12)
            ->exists();

        if ($existingDecember) {
            return null;
        }

        // Query ALL payrolls for this allocation in the year (no "current month" exclusion)
        $yearPayrolls = Payroll::where('employment_id', $employment->id)
            ->where('employee_funding_allocation_id', $allocation->id)
            ->whereYear('pay_period_date', $payPeriodDate->year)
            ->get();

        if ($yearPayrolls->isEmpty()) {
            return null;
        }

        // Sum gross_salary_by_FTE in PHP (encrypted columns)
        $totalYearGrossByFTE = 0.0;
        foreach ($yearPayrolls as $p) {
            $totalYearGrossByFTE += (float) $p->gross_salary_by_FTE;
        }

        if ($totalYearGrossByFTE <= 0) {
            return null;
        }

        $divisor = $policy?->thirteenth_month_divisor ?? 12;
        $thirteenthMonthAmount = round($totalYearGrossByFTE / $divisor);

        if ($thirteenthMonthAmount <= 0) {
            return null;
        }

        // Return same structure as calculateAllocationPayroll() with all salary fields zeroed
        // except 13th month related fields
        return [
            'allocation_id' => $allocation->id,
            'staff_id' => $employee->staff_id,
            'employee_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
            'department' => $employment->department->name ?? 'N/A',
            'position' => $employment->position->title ?? 'N/A',
            'fte_percentage' => ($allocation->fte ?? 1.0) * 100,
            'funding_source' => $this->getFundingSourceName($allocation),
            'calculations' => [
                'gross_salary' => 0,
                'gross_salary_by_fte' => 0,
                'gross_salary_by_FTE' => 0,
                'salary_increase_1_percent' => 0,
                'retroactive_adjustment' => 0,
                'thirteen_month_salary' => $thirteenthMonthAmount,
                'thirteen_month_salary_accured' => $thirteenthMonthAmount,
                'pvd_saving_fund_employee' => 0,
                'employer_social_security' => 0,
                'employee_social_security' => 0,
                'employer_health_welfare' => 0,
                'employee_health_welfare' => 0,
                'income_tax' => 0,
                'salary_bonus' => 0,
                'net_salary' => $thirteenthMonthAmount,
                'total_salary' => $thirteenthMonthAmount,
                'total_pvd_saving_fund' => 0,
                'pvd' => 0,
                'saving_fund' => 0,
                'pvd_employer' => 0,
                'saving_fund_employer' => 0,
                'tax' => 0,
                'total_income' => $thirteenthMonthAmount,
                'total_deduction' => 0,
                'employer_contribution' => 0,
                'total_pvd' => 0,
                'total_saving_fund' => 0,
            ],
            'calculation_breakdown' => [
                'type' => 'historical_allocation_13th_month',
                'allocation_id' => $allocation->id,
                'allocation_status' => $allocation->status,
                'ytd_payroll_count' => $yearPayrolls->count(),
                'ytd_gross_by_fte_total' => $totalYearGrossByFTE,
                'divisor' => $divisor,
                'thirteenth_month_amount' => $thirteenthMonthAmount,
            ],
        ];
    }

    /**
     * Calculate payroll for a specific funding allocation
     */
    private function calculateAllocationPayroll(
        Employee $employee,
        EmployeeFundingAllocation $allocation,
        Carbon $payPeriodDate,
        bool $isTaxAllocation = true
    ): array {
        $employment = $employee->employment;

        // Calculate pro-rated salary for probation transition
        $salaryCalculation = $this->calculateProRatedSalaryForProbation($employment, $payPeriodDate);

        // Calculate annual salary increase
        $annualIncrease = $this->calculateAnnualSalaryIncrease($employee, $employment, $payPeriodDate);
        $adjustedGrossSalary = $salaryCalculation['gross_salary'] + $annualIncrease;

        // ===== CALCULATE ALL 13 PAYROLL ITEMS USING DEDICATED METHODS =====

        // 1. Gross Salary
        $grossSalary = $this->calculateGrossSalary($employment, $payPeriodDate);

        // 2. Gross Salary of Current Year by FTE (includes pro-rating and LOE)
        // Uses 30-day standardized month, mid-month start rule (day >= 16 → deferred)
        $grossSalaryCurrentYearByFTE = $this->calculateGrossSalaryCurrentYearByFTE($employment, $allocation, $payPeriodDate, $adjustedGrossSalary);

        // 3. Retroactive Adjustment (deferred salary from previous month)
        // e.g., employee started on 17th last month → 13 days carried to this month
        // Positive = under-paid (add to payroll), Negative = over-paid (deduct from payroll)
        $retroactiveAdjustment = $this->calculateRetroactiveAdjustment($employment, $allocation, $payPeriodDate, $adjustedGrossSalary);

        // 4. 13th Month Salary (December only, post-probation, YTD-based per allocation)
        $thirteenthMonthSalary = $this->calculateThirteenthMonthSalaryAmount($employee, $employment, $payPeriodDate, $grossSalaryCurrentYearByFTE, $allocation);

        // 5. PVD/Saving Fund (7.5%)
        $pvdSavingCalculations = $this->calculatePVDSavingFund($employee, $grossSalaryCurrentYearByFTE, $employment, $payPeriodDate);
        $pvdSavingEmployee = $pvdSavingCalculations['pvd_employee'] + $pvdSavingCalculations['saving_fund'];

        // 6. Employer Social Security (5%, capped at 875, FTE-proportional)
        // Uses FULL salary for calculation, then allocates by FTE
        $employerSocialSecurity = $this->calculateEmployerSocialSecurity($employee, $adjustedGrossSalary, $allocation->fte);

        // 7. Employee Social Security (5%, capped at 875, FTE-proportional)
        $employeeSocialSecurity = $this->calculateEmployeeSocialSecurity($employee, $adjustedGrossSalary, $allocation->fte);

        // 8. Health Welfare Employer (Non-Thai only, FTE-proportional)
        // Uses FULL salary for tier determination, FTE for splitting
        $healthWelfareEmployer = $this->calculateHealthWelfareEmployer($employee, $adjustedGrossSalary, $allocation->fte);

        // 9. Health Welfare Employee (Thai vs Non-Thai tiers, FTE-proportional)
        $healthWelfareEmployee = $this->calculateHealthWelfareEmployee($employee, $adjustedGrossSalary, $allocation->fte);

        // 10. Income Tax (ACM: uses YTD payroll history across all allocations)
        // Tax goes to ONE allocation only (highest FTE). Non-tax allocations get tax=0.
        // Pass actual SSF and PVD/SF so tax calculation uses real deductions, not theoretical.
        $incomeTax = $this->calculateIncomeTax(
            $employee, $adjustedGrossSalary, $allocation->fte, $employment, $payPeriodDate, $allocation,
            $employeeSocialSecurity, $pvdSavingEmployee,
            $isTaxAllocation
        );

        // Salary bonus (currently 0 — set manually by HR or via import)
        $salaryBonus = 0.0;

        // 11. Net Salary (includes salary_bonus in total income)
        $netSalary = $this->calculateNetSalary(
            $grossSalaryCurrentYearByFTE,
            $retroactiveAdjustment,
            $thirteenthMonthSalary,
            $salaryBonus,
            $pvdSavingEmployee,
            $employeeSocialSecurity,
            $healthWelfareEmployee,
            $incomeTax
        );

        // 12. Total Salary (Total Cost to Company — includes salary_bonus)
        $totalSalary = $this->calculateTotalSalary(
            $grossSalaryCurrentYearByFTE,
            $retroactiveAdjustment,
            $thirteenthMonthSalary,
            $salaryBonus,
            $employerSocialSecurity,
            $healthWelfareEmployer
        );

        // 13. Total PVD/Saving Fund (Employee + Employer combined)
        $totalPVDSaving = $this->calculateTotalPVDSaving($pvdSavingEmployee);

        // 14. Employer PVD/Saving Fund portion
        $pvdEmployerPortion = $totalPVDSaving - $pvdSavingEmployee;

        // ===== DERIVED TOTALS =====
        $totalIncome = $grossSalaryCurrentYearByFTE + $retroactiveAdjustment + $thirteenthMonthSalary + $salaryBonus;
        $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax;
        $employerContributions = $employerSocialSecurity + $healthWelfareEmployer + $pvdEmployerPortion;

        // 13th month accrued projection — disabled for now
        // TODO: Re-enable when accrual projection is needed
        // $policy = PayrollPolicySetting::getActivePolicy();
        // $thirteenthMonthEnabled = $policy?->thirteenth_month_enabled ?? true;
        // $divisor = $policy?->thirteenth_month_divisor ?? 12;
        // if ($thirteenthMonthEnabled) {
        //     $ytdGrossByFTE = $this->getYtdGrossSalaryByFTE($employment, $allocation, $payPeriodDate);
        //     $thirteenthMonthAccrued = round(($ytdGrossByFTE + $grossSalaryCurrentYearByFTE) / $divisor);
        // } else {
        //     $thirteenthMonthAccrued = 0;
        // }
        $thirteenthMonthAccrued = 0;

        // ===== BUILD CALCULATION BREAKDOWN FOR DEBUGGING =====
        $startDate = Carbon::parse($employment->start_date);
        $endDate = $employment->end_date ? Carbon::parse($employment->end_date) : null;
        $probationPassDate = $employment->pass_probation_date ? Carbon::parse($employment->pass_probation_date) : null;
        $isStartMonth = ($startDate->year == $payPeriodDate->year && $startDate->month == $payPeriodDate->month);
        $isResignMonth = $endDate && ($endDate->year == $payPeriodDate->year && $endDate->month == $payPeriodDate->month);

        $calculationBreakdown = [
            'inputs' => [
                'employee_status' => $employee->status,
                'organization' => $employee->organization,
                'employment_start_date' => $startDate->format('Y-m-d'),
                'employment_end_date' => $endDate?->format('Y-m-d'),
                'probation_salary' => $employment->probation_salary,
                'pass_probation_salary' => $employment->pass_probation_salary,
                'probation_pass_date' => $probationPassDate?->format('Y-m-d'),
                'has_passed_probation' => $probationPassDate && $payPeriodDate->gte($probationPassDate),
                'pay_period_date' => $payPeriodDate->format('Y-m-d'),
                'fte' => $allocation->fte,
            ],
            'step_1_salary_determination' => [
                'pro_rated_salary_result' => $salaryCalculation,
                'annual_increase_rate' => $annualIncrease,
                'adjusted_gross_salary' => $adjustedGrossSalary,
                'salary_from_getSalaryAmountForDate' => $grossSalary,
            ],
            'step_2_gross_by_fte' => [
                'adjusted_gross_salary' => $adjustedGrossSalary,
                'daily_rate' => round($adjustedGrossSalary / 30),
                'fte' => $allocation->fte,
                'is_start_month' => $isStartMonth,
                'start_day' => $isStartMonth ? $startDate->day : null,
                'is_resign_month' => $isResignMonth,
                'resign_day' => $isResignMonth ? $endDate->day : null,
                'result' => $grossSalaryCurrentYearByFTE,
            ],
            'step_3_retroactive' => [
                'previous_month_start_day' => null,
                'deferred_days' => 0,
                'adjusted_gross_salary_input' => $adjustedGrossSalary,
                'result' => $retroactiveAdjustment,
            ],
            'step_4_thirteen_month' => [
                'is_december' => $payPeriodDate->month === 12,
                'result' => $thirteenthMonthSalary,
                // 'accrued' => $thirteenthMonthAccrued, // Disabled — accrual projection not needed for now
            ],
            'step_5_pvd_saving_fund' => [
                'monthly_salary_input' => $grossSalaryCurrentYearByFTE,
                'employment_pvd_toggle' => $employment->pvd,
                'employment_saving_fund_toggle' => $employment->saving_fund,
                'probation_required' => $employment->probation_required,
                'probation_passed_for_month' => ($employment->probation_required === false)
                    || ($employment->pass_probation_date
                        ? $payPeriodDate->copy()->endOfMonth()->gte(Carbon::parse($employment->pass_probation_date))
                        : false),
                'pvd_employee' => $pvdSavingCalculations['pvd_employee'],
                'saving_fund' => $pvdSavingCalculations['saving_fund'],
                'combined_employee' => $pvdSavingEmployee,
                'employer_portion' => $pvdEmployerPortion,
                'total_pvd_saving' => $totalPVDSaving,
            ],
            'step_6_social_security' => [
                'full_monthly_salary_input' => $adjustedGrossSalary,
                'fte' => $allocation->fte,
                'employer_ss' => $employerSocialSecurity,
                'employee_ss' => $employeeSocialSecurity,
            ],
            'step_7_health_welfare' => [
                'full_monthly_salary_input' => $adjustedGrossSalary,
                'fte' => $allocation->fte,
                'employer_hw' => $healthWelfareEmployer,
                'employee_hw' => $healthWelfareEmployee,
            ],
            'step_8_tax' => [
                'method' => 'ACM (Accumulative Calculation Method)',
                'note' => 'Tax calculated on FULL gross salary, assigned to ONE allocation only (highest FTE)',
                'is_tax_allocation' => $isTaxAllocation,
                'full_gross_salary_input' => $adjustedGrossSalary,
                'fte' => $allocation->fte,
                'current_month' => $payPeriodDate->month,
                'employee_has_spouse' => $employee->has_spouse,
                'employee_children_count' => $employee->employeeChildren->count(),
                'employee_eligible_parents' => $employee->eligible_parents_count,
                'actual_monthly_ssf_by_fte' => $employeeSocialSecurity,
                'actual_monthly_pvd_sf_by_fte' => $pvdSavingEmployee,
                'result_tax_by_fte' => $incomeTax,
            ],
            'step_9_totals' => [
                'total_income_formula' => 'gross_by_fte + retro + 13th_month + bonus',
                'total_income' => $totalIncome,
                'total_deductions_formula' => 'pvd_saving + employee_ss + employee_hw + tax',
                'total_deductions' => $totalDeductions,
                'employer_contributions_formula' => 'employer_ss + employer_hw + pvd_sf_employer',
                'employer_contributions' => $employerContributions,
                'net_salary_formula' => 'total_income - total_deductions',
                'net_salary' => $netSalary,
                'total_salary_formula' => 'gross_by_fte + retro + 13th_month + bonus + employer_ss + employer_hw',
                'total_salary' => $totalSalary,
            ],
        ];

        // Fill retroactive details
        $previousMonth = $payPeriodDate->copy()->subMonth();
        if ($startDate->year == $previousMonth->year && $startDate->month == $previousMonth->month && $startDate->day >= 16) {
            $calculationBreakdown['step_3_retroactive']['previous_month_start_day'] = $startDate->day;
            $calculationBreakdown['step_3_retroactive']['deferred_days'] = 30 - $startDate->day + 1;
        }

        return [
            'allocation_id' => $allocation->id,
            'staff_id' => $employee->staff_id,
            'employee_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
            'department' => $employment->department->name ?? 'N/A',
            'position' => $employment->position->title ?? 'N/A',
            'fte_percentage' => ($allocation->fte ?? 1.0) * 100, // Convert decimal to percentage
            'funding_source' => $this->getFundingSourceName($allocation),
            'calculations' => [
                // ===== PAYROLL FIELDS (matching database schema) =====
                'gross_salary' => $grossSalary,
                'gross_salary_by_fte' => $grossSalaryCurrentYearByFTE,
                'gross_salary_by_FTE' => $grossSalaryCurrentYearByFTE, // Legacy compatibility
                'salary_increase_1_percent' => $annualIncrease,
                'retroactive_adjustment' => $retroactiveAdjustment,
                'thirteen_month_salary' => $thirteenthMonthSalary,
                'thirteen_month_salary_accured' => $thirteenthMonthAccrued,
                'pvd_saving_fund_employee' => $pvdSavingEmployee,
                'employer_social_security' => $employerSocialSecurity,
                'employee_social_security' => $employeeSocialSecurity,
                'employer_health_welfare' => $healthWelfareEmployer,
                'employee_health_welfare' => $healthWelfareEmployee,
                'income_tax' => $incomeTax,
                'salary_bonus' => $salaryBonus,
                'net_salary' => $netSalary,
                'total_salary' => $totalSalary,
                'total_pvd_saving_fund' => $totalPVDSaving,

                // ===== ADDITIONAL CALCULATED FIELDS =====
                'pvd' => $pvdSavingCalculations['pvd_employee'],
                'saving_fund' => $pvdSavingCalculations['saving_fund'],
                'pvd_employer' => round($pvdSavingCalculations['pvd_employee'] > 0 ? $pvdEmployerPortion : 0),
                'saving_fund_employer' => round($pvdSavingCalculations['saving_fund'] > 0 ? $pvdEmployerPortion : 0),
                'tax' => $incomeTax,
                'total_income' => $totalIncome,
                'total_deduction' => $totalDeductions,
                'employer_contribution' => $employerContributions,
                'total_pvd' => round($pvdSavingCalculations['pvd_employee'] + ($pvdSavingCalculations['pvd_employee'] > 0 ? $pvdEmployerPortion : 0)),
                'total_saving_fund' => round($pvdSavingCalculations['saving_fund'] + ($pvdSavingCalculations['saving_fund'] > 0 ? $pvdEmployerPortion : 0)),
            ],
            'calculation_breakdown' => $calculationBreakdown,
        ];
    }

    /**
     * Create inter-organization advance if needed
     */
    public function createInterOrganizationAdvanceIfNeeded(Employee $employee, EmployeeFundingAllocation $allocation, Payroll $payroll, Carbon $payPeriodDate): ?InterOrganizationAdvance
    {
        $projectGrant = $this->getFundingGrant($allocation);
        if (! $projectGrant) {
            Log::warning('Cannot create inter-organization advance: No project grant found', [
                'allocation_id' => $allocation->id,
                'payroll_id' => $payroll->id,
            ]);

            return null;
        }

        $fundingOrganization = $projectGrant->organization;
        $employeeOrganization = $employee->organization;

        // No advance needed if subsidiaries match
        if ($fundingOrganization === $employeeOrganization) {
            return null;
        }

        // Get the correct hub grant for the lending organization
        $hubGrant = \App\Models\Grant::getHubGrantForOrganization($fundingOrganization);

        if (! $hubGrant) {
            Log::error('Hub grant not found for organization', [
                'organization' => $fundingOrganization,
                'allocation_id' => $allocation->id,
                'payroll_id' => $payroll->id,
            ]);

            return null;
        }

        $advance = InterOrganizationAdvance::create([
            'payroll_id' => $payroll->id,
            'from_organization' => $fundingOrganization,
            'to_organization' => $employeeOrganization,
            'via_grant_id' => $hubGrant->id, // Use hub grant, not project grant
            'amount' => $payroll->net_salary,
            'advance_date' => $payPeriodDate,
            'notes' => "Hub grant advance: {$projectGrant->code} → {$hubGrant->code} for {$employee->staff_id}",
            'created_by' => auth()->user()?->name ?? 'system',
            'updated_by' => auth()->user()?->name ?? 'system',
        ]);

        Log::info('Inter-organization advance created', [
            'advance_id' => $advance->id,
            'from' => $fundingOrganization,
            'to' => $employeeOrganization,
            'amount' => $payroll->net_salary,
            'employee' => $employee->staff_id,
            'project_grant' => $projectGrant->code,
            'hub_grant' => $hubGrant->code,
            'payroll_id' => $payroll->id,
        ]);

        return $advance;
    }

    /**
     * Get funding organization from allocation
     */
    private function getFundingOrganization(EmployeeFundingAllocation $allocation): string
    {
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            return $allocation->grantItem->grant->organization ?? 'UNKNOWN';
        }

        return 'UNKNOWN';
    }

    /**
     * Get funding grant from allocation
     */
    private function getFundingGrant(EmployeeFundingAllocation $allocation): ?object
    {
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            return $allocation->grantItem->grant;
        }

        return null;
    }

    /**
     * Get funding source name from allocation
     */
    private function getFundingSourceName(EmployeeFundingAllocation $allocation): string
    {
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            return $allocation->grantItem->grant->name ?? 'Grant';
        }

        return 'Grant';
    }

    // ===========================================
    // INDIVIDUAL PAYROLL CALCULATION METHODS
    // 13 Required Payroll Items
    // ===========================================

    /**
     * 1. Calculate Gross Salary (Position Salary)
     * Uses date-aware salary lookup so retroactive payroll picks the correct salary.
     */
    private function calculateGrossSalary($employment, Carbon $payPeriodDate): float
    {
        return $employment->getSalaryAmountForDate($payPeriodDate);
    }

    /**
     * 2. Calculate Gross Salary of Current Year by FTE
     * Uses standardized 30-day month basis (client req 2.1).
     * Mid-month start rule (client req Scenario 2-3):
     *   - Start day 1: full 30 days
     *   - Start day 2-15: pay for (30 - startDay) days
     *   - Start day >= 16: no pay this month (deferred to next month as retroactive adjustment)
     * Resignation mid-month (client req Scenario 4):
     *   - Pay through the resignation day based on 30-day month
     */
    private function calculateGrossSalaryCurrentYearByFTE($employment, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate, float $adjustedGrossSalary): float
    {
        $daysWorked = 30; // Full month default (30-day standardized)

        // Adjust for mid-month start
        $startDate = Carbon::parse($employment->start_date);
        $isStartMonth = ($startDate->year == $payPeriodDate->year && $startDate->month == $payPeriodDate->month);

        if ($isStartMonth) {
            if ($startDate->day === 1) {
                $daysWorked = 30;
            } elseif ($startDate->day >= 16) {
                // Client Scenario 3: start day >= 16 means no pay this month.
                // The deferred amount is handled by calculateRetroactiveAdjustment() next month.
                return 0.0;
            } else {
                // Client Scenario 2: e.g., start day 15 → 30 - 15 + 1 = 16 pay days (inclusive of start day)
                $daysWorked = 30 - $startDate->day + 1;
            }
        }

        // Adjust for resignation mid-month (Client Scenario 4)
        $endDate = $employment->end_date ? Carbon::parse($employment->end_date) : null;
        if ($endDate &&
            $endDate->year == $payPeriodDate->year && $endDate->month == $payPeriodDate->month) {
            // Pay through the resignation day (e.g., resign on 8th → 8 days out of 30)
            $resignDays = min($endDate->day, 30);
            $daysWorked = min($daysWorked, $resignDays);
        }

        if ($daysWorked <= 0) {
            return 0.0;
        }

        // Pro-rate on FULL salary first, then apply FTE
        // This avoids per-allocation rounding drift (e.g., 80/20 split rounding daily rates separately)
        $proRatedSalary = $adjustedGrossSalary;
        if ($daysWorked < 30) {
            $proRatedSalary = round($adjustedGrossSalary / 30) * $daysWorked;
        }

        return round($proRatedSalary * $allocation->fte);
    }

    /**
     * 3. Calculate Retroactive Adjustment
     * Handles deferred salary from previous month when employee started on day >= 16.
     * Client Scenario 3: "Start Date: 17 | Don't get paid on the start month
     * but will be calculated back on the next month paid. Additional 13 days."
     * Positive = under-paid (repay to employee), Negative = over-paid (deduct from employee).
     */
    private function calculateRetroactiveAdjustment($employment, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate, float $adjustedGrossSalary): float
    {
        $startDate = Carbon::parse($employment->start_date);
        $previousMonth = $payPeriodDate->copy()->subMonth();

        // Check if employee started mid-month in the PREVIOUS month with day >= 16
        // (meaning they got 0 pay that month — salary was deferred to this month)
        if ($startDate->year == $previousMonth->year &&
            $startDate->month == $previousMonth->month &&
            $startDate->day >= 16) {
            // Deferred days from previous month: e.g., start on 17th → 30 - 17 + 1 = 14 days (inclusive of start day)
            $deferredDays = 30 - $startDate->day + 1;

            // Pro-rate on FULL salary first, then apply FTE
            $proRatedSalary = round($adjustedGrossSalary / 30) * $deferredDays;

            return round($proRatedSalary * $allocation->fte);
        }

        return 0.0;
    }

    /**
     * 4. Calculate 13th Month Salary (per-allocation, YTD-based)
     *
     * Paid once a year in the December payroll only.
     * Eligibility: employee must have passed probation by the pay period.
     *
     * Formula (confirmed by client whiteboard):
     *   SUM(gross_salary_by_FTE for all payroll records of this allocation in the year) / divisor
     *
     * This correctly handles multi-allocation employees where grants change mid-year.
     * Each grant pays its own share based on what it actually funded.
     *
     * Example: Employee on Grant A (Jan-May) earning ฿50,000/month by FTE
     *   → YTD gross for Grant A = 5 × 50,000 = 250,000
     *   → 13th month for Grant A = 250,000 / 12 = ฿20,833
     *
     * @param  Employee  $employee  Employee model
     * @param  mixed  $employment  Employment model
     * @param  Carbon  $payPeriodDate  Pay period date
     * @param  float  $grossSalaryCurrentYearByFTE  Current month's FTE-adjusted gross
     * @param  EmployeeFundingAllocation|null  $allocation  Funding allocation for YTD lookup
     */
    private function calculateThirteenthMonthSalaryAmount(
        Employee $employee,
        $employment,
        Carbon $payPeriodDate,
        float $grossSalaryCurrentYearByFTE,
        ?EmployeeFundingAllocation $allocation = null
    ): float {
        $policy = PayrollPolicySetting::getActivePolicy();

        $enabled = $policy?->thirteenth_month_enabled ?? true;
        if (! $enabled) {
            return 0.0;
        }

        // Only paid in December payroll
        if ($payPeriodDate->month !== 12) {
            return 0.0;
        }

        // 13th month is eligible from start_date — no probation gate.
        // Both probation salary and post-probation salary months are included.

        // Sum YTD gross_salary_by_FTE from prior months + current month
        $ytdGrossByFTE = $this->getYtdGrossSalaryByFTE($employment, $allocation, $payPeriodDate);
        $totalYearGrossByFTE = $ytdGrossByFTE + $grossSalaryCurrentYearByFTE;

        if ($totalYearGrossByFTE <= 0) {
            return 0.0;
        }

        $divisor = $policy?->thirteenth_month_divisor ?? 12;

        return round($totalYearGrossByFTE / $divisor);
    }

    /**
     * 5. Calculate PVD/Saving Fund (percentage from benefit_settings)
     * Local ID = PVD, Local non ID = Saving Fund
     */
    private function calculatePVDSavingFund(Employee $employee, float $monthlySalary, $employment, Carbon $payPeriodDate): array
    {
        // PVD/Saving Fund only deducted after passing probation (or if probation not required)
        // Use end-of-month comparison so transition month (probation passes mid-month) is included
        if ($employment->probation_required === false) {
            $hasPassed = true;
        } else {
            $probationPassDate = $employment->pass_probation_date ? Carbon::parse($employment->pass_probation_date) : null;
            $hasPassed = $probationPassDate && $payPeriodDate->copy()->endOfMonth()->gte($probationPassDate);
        }

        if (! $hasPassed) {
            return ['pvd_employee' => 0.0, 'saving_fund' => 0.0];
        }

        // Get PVD/Saving Fund percentage from benefit_settings (single source of truth)
        $pvdPercentage = BenefitSetting::getActiveSetting(BenefitSetting::KEY_PVD_EMPLOYEE_RATE) ?? 7.5;
        $savingFundPercentage = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SAVING_FUND_EMPLOYEE_RATE) ?? 7.5;

        // Local ID (Thai) → PVD, requires employment.pvd toggle enabled by HR
        if ($employee->status?->value === Employee::STATUS_LOCAL_ID && $employment->pvd) {
            return [
                'pvd_employee' => round($monthlySalary * ($pvdPercentage / 100)),
                'saving_fund' => 0.0,
            ];
        }

        // Local non ID (Non-Thai) → Saving Fund, requires employment.saving_fund toggle enabled by HR
        if ($employee->status?->value === Employee::STATUS_LOCAL_NON_ID && $employment->saving_fund) {
            return [
                'pvd_employee' => 0.0,
                'saving_fund' => round($monthlySalary * ($savingFundPercentage / 100)),
            ];
        }

        // Expats or toggle not enabled → no PVD/Saving Fund
        return ['pvd_employee' => 0.0, 'saving_fund' => 0.0];
    }

    /**
     * 6. Calculate Employer Social Security
     * Client req 2.7.1: 5% of salary, capped at 875/month, min salary 1,650, max salary 17,500.
     * FTE-proportional cap: total SSF across all allocations must not exceed 875.
     * Skipped for Expats.
     *
     * @param  Employee  $employee  Employee model (for status check)
     * @param  float  $fullMonthlySalary  FULL monthly salary (pre-FTE)
     * @param  float  $fte  FTE of the current allocation (0.0–1.0)
     */
    private function calculateEmployerSocialSecurity(Employee $employee, float $fullMonthlySalary, float $fte): float
    {
        // SSF only for Local ID Staff (Thai) and Local non ID Staff (Non-Thai with work permit)
        if (! in_array($employee->status?->value, [Employee::STATUS_LOCAL_ID, Employee::STATUS_LOCAL_NON_ID])) {
            return 0.0;
        }

        $rate = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_EMPLOYER_RATE) ?? 5.0;
        $minSalary = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_MIN_SALARY) ?? 1650.0;
        $maxSalary = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_MAX_SALARY) ?? 17500.0;
        $maxMonthly = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_MAX_MONTHLY) ?? 875.0;

        // Apply salary range limits on FULL salary
        $effectiveSalary = max($minSalary, min($fullMonthlySalary, $maxSalary));

        // Calculate contribution on full salary, then allocate by FTE
        $fullContribution = min($effectiveSalary * ($rate / 100), $maxMonthly);

        return round($fullContribution * $fte);
    }

    /**
     * 7. Calculate Employee Social Security
     * Same rules as employer SSF — 5%, min 1,650, max 17,500, cap 875, FTE-proportional.
     *
     * @param  Employee  $employee  Employee model (for status check)
     * @param  float  $fullMonthlySalary  FULL monthly salary (pre-FTE)
     * @param  float  $fte  FTE of the current allocation (0.0–1.0)
     */
    private function calculateEmployeeSocialSecurity(Employee $employee, float $fullMonthlySalary, float $fte): float
    {
        if (! in_array($employee->status?->value, [Employee::STATUS_LOCAL_ID, Employee::STATUS_LOCAL_NON_ID])) {
            return 0.0;
        }

        $rate = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_EMPLOYEE_RATE) ?? 5.0;
        $minSalary = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_MIN_SALARY) ?? 1650.0;
        $maxSalary = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_MAX_SALARY) ?? 17500.0;
        $maxMonthly = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SSF_MAX_MONTHLY) ?? 875.0;

        $effectiveSalary = max($minSalary, min($fullMonthlySalary, $maxSalary));
        $fullContribution = min($effectiveSalary * ($rate / 100), $maxMonthly);

        return round($fullContribution * $fte);
    }

    /**
     * 8. Calculate Health Welfare Employer
     * Client req 2.7.2: Employer pays ONLY for Non-Thai ID staff at eligible organizations.
     * Thai ID staff: employer contribution = 0.
     * Uses FULL salary for tier determination, FTE for allocation splitting.
     *
     * @param  Employee  $employee  Employee model
     * @param  float  $fullMonthlySalary  FULL monthly salary (pre-FTE) for tier determination
     * @param  float  $fte  FTE for proportional allocation
     */
    private function calculateHealthWelfareEmployer(Employee $employee, float $fullMonthlySalary, float $fte): float
    {
        $eligibility = BenefitSetting::getEmployerHWEligibility();

        if (! $eligibility) {
            return 0.0;
        }

        $eligibleStatuses = $eligibility['eligible_statuses'] ?? [];
        $eligibleOrgs = $eligibility['eligible_organizations'] ?? [];

        // Employer pays health welfare ONLY for eligible statuses in eligible organizations
        if (! in_array($employee->organization, $eligibleOrgs) ||
            ! in_array($employee->status?->value, $eligibleStatuses)) {
            return 0.0;
        }

        // Use Non-Thai employer tier amounts
        $highThreshold = BenefitSetting::getActiveSetting('health_welfare_high_threshold') ?? 15000.0;
        $mediumThreshold = BenefitSetting::getActiveSetting('health_welfare_medium_threshold') ?? 5000.0;

        if ($fullMonthlySalary > $highThreshold) {
            $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_NONTHAI_EMPLOYER_HIGH) ?? 150.0;
        } elseif ($fullMonthlySalary > $mediumThreshold) {
            $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_NONTHAI_EMPLOYER_MEDIUM) ?? 100.0;
        } else {
            $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_NONTHAI_EMPLOYER_LOW) ?? 60.0;
        }

        return round($amount * $fte);
    }

    /**
     * 9. Calculate Health Welfare Employee
     * Client req 2.7.2: Different tiers for Thai vs Non-Thai employees.
     * Expats: skipped by default (some receive HW individually — future per-employee toggle).
     * Uses FULL salary for tier determination, FTE for allocation splitting.
     *
     * Non-Thai: <=5000 → 30, 5001-15000 → 50, >15000 → 75
     * Thai:     <=5000 → 50, 5001-15000 → 80, >15000 → 100
     *
     * @param  Employee  $employee  Employee model
     * @param  float  $fullMonthlySalary  FULL monthly salary (pre-FTE) for tier determination
     * @param  float  $fte  FTE for proportional allocation
     */
    private function calculateHealthWelfareEmployee(Employee $employee, float $fullMonthlySalary, float $fte): float
    {
        // Skip for Expats by default (per-individual override can be added later)
        if ($employee->status?->value === Employee::STATUS_EXPATS) {
            return 0.0;
        }

        $highThreshold = BenefitSetting::getActiveSetting('health_welfare_high_threshold') ?? 15000.0;
        $mediumThreshold = BenefitSetting::getActiveSetting('health_welfare_medium_threshold') ?? 5000.0;

        // Non-Thai employees (Local non ID Staff)
        $isNonThai = $employee->status?->value === Employee::STATUS_LOCAL_NON_ID;

        if ($isNonThai) {
            if ($fullMonthlySalary > $highThreshold) {
                $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_NONTHAI_EMPLOYEE_HIGH) ?? 75.0;
            } elseif ($fullMonthlySalary > $mediumThreshold) {
                $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_NONTHAI_EMPLOYEE_MEDIUM) ?? 50.0;
            } else {
                $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_NONTHAI_EMPLOYEE_LOW) ?? 30.0;
            }
        } else {
            // Thai employees (Local ID)
            if ($fullMonthlySalary > $highThreshold) {
                $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_THAI_EMPLOYEE_HIGH) ?? 100.0;
            } elseif ($fullMonthlySalary > $mediumThreshold) {
                $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_THAI_EMPLOYEE_MEDIUM) ?? 80.0;
            } else {
                $amount = BenefitSetting::getActiveSetting(BenefitSetting::KEY_HW_THAI_EMPLOYEE_LOW) ?? 50.0;
            }
        }

        return round($amount * $fte);
    }

    /**
     * 10. Calculate Tax (Income Tax) using ACM (Accumulative Calculation Method).
     *
     * Tax is a personal obligation calculated on the FULL gross salary (not FTE portion).
     * Tax goes to ONE allocation only (the one with highest FTE) — not split across allocations.
     *
     * YTD payroll history is aggregated across ALL allocations for this employment
     * to give the tax service an accurate picture of total earnings and withholdings.
     *
     * @param  Employee  $employee  Employee model
     * @param  float  $fullGrossSalary  Full monthly gross salary (before FTE split)
     * @param  float  $fte  FTE ratio for this allocation (e.g. 0.56)
     * @param  mixed  $employment  Employment model
     * @param  Carbon  $payPeriodDate  Pay period date
     * @param  EmployeeFundingAllocation|null  $allocation  Funding allocation
     * @param  float  $actualMonthlySsfByFte  Actual SSF amount (by FTE)
     * @param  float  $actualMonthlyPvdSfByFte  Actual PVD/SF amount (by FTE)
     * @param  bool  $isTaxAllocation  Whether this allocation bears the tax
     */
    private function calculateIncomeTax(
        Employee $employee,
        float $fullGrossSalary,
        float $fte,
        $employment,
        Carbon $payPeriodDate,
        ?EmployeeFundingAllocation $allocation = null,
        float $actualMonthlySsfByFte = 0.0,
        float $actualMonthlyPvdSfByFte = 0.0,
        bool $isTaxAllocation = true
    ): float {
        // Tax goes to ONE allocation only (the one with highest FTE)
        if (! $isTaxAllocation) {
            return 0.0;
        }

        $currentMonth = $payPeriodDate->month;

        // Query YTD payroll data across ALL allocations for this employment
        // Tax is personal — we need total income/withholdings, not per-allocation
        $ytdIncome = 0.0;
        $ytdTaxWithheld = 0.0;
        $ytdSSF = 0.0;
        $ytdPvdSf = 0.0;

        $ytdPayrolls = Payroll::where('employment_id', $employment->id)
            ->whereYear('pay_period_date', $payPeriodDate->year)
            ->where('pay_period_date', '<', $payPeriodDate->copy()->startOfMonth())
            ->get();

        foreach ($ytdPayrolls as $p) {
            $ytdIncome += (float) $p->total_income;
            $ytdTaxWithheld += (float) $p->tax;
            $ytdSSF += (float) $p->employee_social_security;
            $ytdPvdSf += (float) $p->pvd + (float) $p->saving_fund;
        }

        // Scale actual deductions from FTE-level back to full-salary-level
        // so the tax service calculates on total amounts, not per-allocation
        $actualFullMonthlySsf = $fte > 0 ? $actualMonthlySsfByFte / $fte : 0;
        $actualFullMonthlyPvdSf = $fte > 0 ? $actualMonthlyPvdSfByFte / $fte : 0;

        // Prepare employee data with ACM fields
        $employeeData = [
            'has_spouse' => $employee->has_spouse,
            'children' => $employee->employeeChildren->count(),
            'eligible_parents' => $employee->eligible_parents_count,
            'employee_status' => $employee->status,
            'months_working_this_year' => $this->calculateMonthsWorkingThisYear($employment, $payPeriodDate),
            // ACM data (aggregated across all allocations)
            'current_month' => $currentMonth,
            'ytd_income' => $ytdIncome,
            'ytd_tax_withheld' => $ytdTaxWithheld,
            'ytd_ssf' => $ytdSSF,
            'ytd_pvd_sf' => $ytdPvdSf,
            // Actual payroll deductions for this month (full salary level)
            'actual_monthly_ssf' => $actualFullMonthlySsf,
            'actual_monthly_pvd_sf' => $actualFullMonthlyPvdSf,
        ];

        // Calculate income tax on FULL gross salary
        $taxCalculation = $this->taxService->calculateEmployeeTax($fullGrossSalary, $employeeData);

        // Full tax amount on this allocation (no FTE split — tax goes to one grant only)
        $fullMonthlyTax = $taxCalculation['monthly_tax_amount'];

        // Tax rounded to 2 decimal places per business logic
        return round($fullMonthlyTax, 2);
    }

    /**
     * 11. Calculate Net Salary
     * Formula: (Salary by FTE + Retroactive + 13th Month + Bonus) - (PVD/Saving + Employee SSF + Employee HW + Tax)
     */
    private function calculateNetSalary(
        float $grossSalaryCurrentYearByFTE,
        float $retroactiveAdjustment,
        float $thirteenthMonthSalary,
        float $salaryBonus,
        float $pvdSavingEmployee,
        float $employeeSocialSecurity,
        float $healthWelfareEmployee,
        float $incomeTax
    ): float {
        $totalIncome = $grossSalaryCurrentYearByFTE + $retroactiveAdjustment + $thirteenthMonthSalary + $salaryBonus;
        $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax;

        // Net salary cannot be negative per business logic
        return max(0, round($totalIncome - $totalDeductions));
    }

    /**
     * 12. Calculate Total Salary (Total Cost to Company)
     * Formula: Salary by FTE + Retroactive + 13th Month + Bonus + Employer SSF + Employer HW
     */
    private function calculateTotalSalary(
        float $grossSalaryCurrentYearByFTE,
        float $retroactiveAdjustment,
        float $thirteenthMonthSalary,
        float $salaryBonus,
        float $employerSocialSecurity,
        float $healthWelfareEmployer
    ): float {
        return round(
            $grossSalaryCurrentYearByFTE +
            $retroactiveAdjustment +
            $thirteenthMonthSalary +
            $salaryBonus +
            $employerSocialSecurity +
            $healthWelfareEmployer
        );
    }

    /**
     * 13. Calculate Total PVD/Saving Fund (Employee + Employer)
     * Uses employer rates from benefit_settings instead of hardcoded multiplier
     */
    private function calculateTotalPVDSaving(float $pvdSavingEmployee): float
    {
        if ($pvdSavingEmployee <= 0) {
            return 0.0;
        }

        // Calculate employer portion using the ratio of employer rate / employee rate
        $pvdEmployeeRate = BenefitSetting::getActiveSetting(BenefitSetting::KEY_PVD_EMPLOYEE_RATE) ?? 7.5;
        $pvdEmployerRate = BenefitSetting::getActiveSetting(BenefitSetting::KEY_PVD_EMPLOYER_RATE) ?? 7.5;
        $savingEmployeeRate = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SAVING_FUND_EMPLOYEE_RATE) ?? 7.5;
        $savingEmployerRate = BenefitSetting::getActiveSetting(BenefitSetting::KEY_SAVING_FUND_EMPLOYER_RATE) ?? 7.5;

        // Since pvdSavingEmployee is already the employee portion, derive employer portion
        // For now, the employer rate typically equals employee rate (both 7.5%), so total = employee * 2
        // But this approach correctly handles cases where rates differ
        $maxEmployeeRate = max($pvdEmployeeRate, $savingEmployeeRate);
        $maxEmployerRate = max($pvdEmployerRate, $savingEmployerRate);

        if ($maxEmployeeRate <= 0) {
            return $pvdSavingEmployee;
        }

        $employerPortion = $pvdSavingEmployee * ($maxEmployerRate / $maxEmployeeRate);

        return round($pvdSavingEmployee + $employerPortion);
    }

    // ===========================================
    // LEGACY HELPER METHODS (for compatibility)
    // ===========================================

    // Helper calculation methods (simplified versions of the ones in PayrollController)

    private function calculateProRatedSalaryForProbation($employment, Carbon $payPeriodDate): array
    {
        // Use ProbationTransitionService for salary calculations with standardized 30-day month approach
        $probationService = app(ProbationTransitionService::class);

        // Check if this is the first month (employee started mid-month)
        $startDate = Carbon::parse($employment->start_date);
        $isFirstMonth = $probationService->startedMidMonthIn($employment, $payPeriodDate);

        if ($isFirstMonth) {
            // Return FULL applicable salary — mid-month day pro-rating is handled
            // by calculateGrossSalaryCurrentYearByFTE() to avoid double pro-rating
            $applicableSalary = (float) ($employment->probation_salary ?? $employment->pass_probation_salary);

            return ['gross_salary' => $applicableSalary];
        }

        // Check if this is transition month (probation completion falls mid-month)
        $isTransitionMonth = $probationService->isTransitionMonth($employment, $payPeriodDate);

        if ($isTransitionMonth) {
            // Calculate pro-rated salary for transition month
            $proRatedSalary = $probationService->calculateProRatedSalary($employment, $payPeriodDate);

            return ['gross_salary' => $proRatedSalary];
        }

        // Regular month - use date-aware salary for the pay period
        $salary = $employment->getSalaryAmountForDate($payPeriodDate);

        return ['gross_salary' => $salary];
    }

    /**
     * Calculate annual salary increase based on payroll policy settings.
     *
     * Business logic rules:
     * - Uses 365 CALENDAR days (not working days)
     * - 15th cutoff: if start_date day > 15, effective counting starts from 1st of next month
     * - Salary increase only applies in the configured effective month (if set)
     */
    private function calculateAnnualSalaryIncrease(Employee $employee, $employment, Carbon $payPeriodDate): float
    {
        $policy = PayrollPolicySetting::getActivePolicy();

        $enabled = $policy?->salary_increase_enabled ?? true;
        if (! $enabled) {
            return 0.0;
        }

        // Salary increase only applies in the configured effective month (e.g., October)
        $effectiveMonth = $policy?->salary_increase_effective_month;
        if ($effectiveMonth && $payPeriodDate->month !== $effectiveMonth) {
            return 0.0;
        }

        $rate = ($policy?->salary_increase_rate ?? 1.00) / 100; // Convert percentage to decimal
        $minDays = $policy?->salary_increase_min_working_days ?? 365;

        $startDate = Carbon::parse($employment->start_date);

        // 15th cutoff rule: if start day > 15, effective start = 1st of next month
        $effectiveStartDate = $startDate->day > 15
            ? $startDate->copy()->startOfMonth()->addMonth()
            : $startDate->copy();

        // Calculate calendar days (not working days)
        $calendarDays = $effectiveStartDate->diffInDays($payPeriodDate);

        if ($calendarDays >= $minDays) {
            return round($employment->pass_probation_salary * $rate);
        }

        return 0.0;
    }

    /**
     * @deprecated Use calculateReproactiveAdjustment() instead.
     * Kept temporarily for legacy code paths in PayrollController.
     */
    private function calculateCompensationRefund($employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        return $this->calculateReproactiveAdjustment($employment, $payPeriodDate, $monthlySalary);
    }

    private function calculateMonthsWorkingThisYear($employment, Carbon $payPeriodDate): int
    {
        $startDate = Carbon::parse($employment->start_date);
        $currentYear = $payPeriodDate->year;

        if ($startDate->year < $currentYear) {
            return 12;
        }

        if ($startDate->year == $currentYear) {
            return min(12, $startDate->diffInMonths(Carbon::create($currentYear, 12, 31)) + 1);
        }

        return 12;
    }

    /**
     * Get Year-to-Date gross_salary_by_FTE for a specific allocation.
     *
     * Fetches all payroll records for this allocation in the given year,
     * excluding the current pay period (not yet saved to DB).
     * Sums in PHP because salary columns are encrypted.
     *
     * @param  mixed  $employment  Employment model
     * @param  EmployeeFundingAllocation|null  $allocation  Funding allocation
     * @param  Carbon  $payPeriodDate  Current pay period date
     * @return float Sum of gross_salary_by_FTE for prior months this year
     */
    private function getYtdGrossSalaryByFTE($employment, ?EmployeeFundingAllocation $allocation, Carbon $payPeriodDate): float
    {
        if (! $allocation) {
            return 0.0;
        }

        $ytdPayrolls = Payroll::where('employment_id', $employment->id)
            ->where('employee_funding_allocation_id', $allocation->id)
            ->whereYear('pay_period_date', $payPeriodDate->year)
            ->where('pay_period_date', '<', $payPeriodDate->copy()->startOfMonth())
            ->get();

        $total = 0.0;
        foreach ($ytdPayrolls as $p) {
            $total += (float) $p->gross_salary_by_FTE;
        }

        return $total;
    }
}
