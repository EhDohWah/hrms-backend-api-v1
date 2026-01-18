<?php

namespace App\Http\Controllers\Api;

use App\Exports\EmployeesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowEmployeeRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeBankRequest;
use App\Http\Requests\UpdateEmployeeFamilyRequest;
use App\Http\Requests\UpdateEmployeePersonalRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UploadEmployeeImportRequest;
use App\Http\Resources\EmployeeCollection;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\EmployeeBeneficiary;
use App\Models\EmployeeChild;
use App\Models\EmployeeEducation;
use App\Models\EmployeeFundingAllocation;
use App\Models\EmployeeIdentification;
use App\Models\EmployeeLanguage;
use App\Models\EmployeeTraining;
use App\Models\Employment;
use App\Models\EmploymentHistory;
use App\Models\GrantItem;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Site;
use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\EmployeeActionNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employees', description: 'API Endpoints for Employee management')]
class EmployeeController extends Controller
{
    /**
     * Get all employees for tree search
     */
    #[OA\Get(
        path: '/employees/tree-search',
        summary: 'Get all employees for tree search',
        description: 'Returns a list of all employees organized by organization for tree-based search',
        operationId: 'getEmployeesForTreeSearch',
        security: [['bearerAuth' => []]],
        tags: ['Employees']
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 500, description: 'Server error')]
    public function getEmployeesForTreeSearch()
    {
        try {
            $employees = Employee::select('id', 'organization', 'staff_id', 'first_name_en', 'last_name_en', 'status')
                ->with([
                    'employment:id,employee_id,department_id,position_id',
                    'employment.department:id,name',
                    'employment.position:id,title',
                ])
                ->get();

            // Group employees by organization
            $grouped = $employees->groupBy('organization');

            // Map each organization into a parent node with its employees as children
            $treeData = $grouped->map(function ($organizationEmployees, $organization) {
                return [
                    'key' => "organization-{$organization}",
                    'title' => $organization,
                    'value' => "organization-{$organization}",
                    'children' => $organizationEmployees->map(function ($emp) {
                        $fullName = $emp->first_name_en;
                        if ($emp->last_name_en && $emp->last_name_en !== '-') {
                            $fullName .= ' '.$emp->last_name_en;
                        }

                        $employeeData = [
                            'key' => "{$emp->id}",
                            'title' => "{$emp->staff_id} - {$fullName}",
                            'status' => $emp->status,
                            'value' => "{$emp->id}",
                            'department_id' => null,
                            'position_id' => null,
                            'employment' => null,
                        ];

                        // Add employment information if available
                        if ($emp->employment) {
                            $employeeData['department_id'] = $emp->employment->department_id;
                            $employeeData['position_id'] = $emp->employment->position_id;
                            $employeeData['employment'] = [
                                'department' => $emp->employment->department ? [
                                    'id' => $emp->employment->department->id,
                                    'name' => $emp->employment->department->name,
                                ] : null,
                                'position' => $emp->employment->position ? [
                                    'id' => $emp->employment->position->id,
                                    'title' => $emp->employment->position->title,
                                ] : null,
                            ];
                        }

                        return $employeeData;
                    })->values()->toArray(),
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Employees retrieved successfully',
                'data' => $treeData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employees',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete multiple employees by their IDs
     */
    #[OA\Delete(
        path: '/employees/delete-selected/{ids}',
        summary: 'Delete multiple employees by their IDs',
        description: 'Deletes multiple employees and their related records based on the provided IDs',
        operationId: 'deleteSelectedEmployees',
        security: [['bearerAuth' => []]],
        tags: ['Employees']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['ids'],
            properties: [
                new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer')),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Employees deleted successfully')]
    #[OA\Response(response: 500, description: 'Server error')]
    public function deleteSelectedEmployees(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:employees,id',
        ]);
        $ids = $validated['ids'];

        try {
            DB::beginTransaction();

            // Delete related records first to maintain referential integrity
            // Note: Some tables have cascadeOnDelete, but we explicitly delete for better control
            EmployeeFundingAllocation::whereIn('employee_id', $ids)->delete();
            EmployeeBeneficiary::whereIn('employee_id', $ids)->delete();
            EmployeeIdentification::whereIn('employee_id', $ids)->delete();
            EmployeeChild::whereIn('employee_id', $ids)->delete();
            EmployeeEducation::whereIn('employee_id', $ids)->delete();
            EmployeeLanguage::whereIn('employee_id', $ids)->delete();
            EmployeeTraining::whereIn('employee_id', $ids)->delete();
            EmploymentHistory::whereIn('employee_id', $ids)->delete();
            LeaveBalance::whereIn('employee_id', $ids)->delete();
            LeaveRequest::whereIn('employee_id', $ids)->delete();
            TravelRequest::whereIn('employee_id', $ids)->delete();
            Employment::whereIn('employee_id', $ids)->delete();

            // Delete the employees
            $count = Employee::whereIn('id', $ids)->delete();

            DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            return response()->json([
                'success' => true,
                'message' => $count.' employee(s) deleted successfully',
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete employees: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employees',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload employee data from Excel file
     */
    #[OA\Post(
        path: '/employees/upload',
        summary: 'Upload employee data from Excel file',
        description: 'Upload an Excel file where each row contains data for creating an employee record',
        operationId: 'uploadEmployeeData',
        security: [['bearerAuth' => []]],
        tags: ['Employees']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Employee data uploaded successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 500, description: 'Failed to import employees')]
    public function uploadEmployeeData(UploadEmployeeImportRequest $request)
    {
        $file = $request->file('file');
        $importId = uniqid('import_', true);
        $userId = auth()->id();

        $import = new \App\Imports\EmployeesImport($importId, $userId);

        // Queue on dedicated import queue
        Excel::queueImport($import, $file)->onQueue('import');

        return response()->json([
            'success' => true,
            'message' => "Your file is being imported. You'll be notified when it's done.",
            'data' => [
                'import_id' => $importId,
            ],
        ], 202);
    }

    /**
     * Download Employee Import Excel Template
     */
    #[OA\Get(
        path: '/uploads/employee/template',
        summary: 'Download Employee Import Excel Template',
        description: 'Downloads an Excel template file with headers, validation rules, and sample data for employee bulk import',
        operationId: 'downloadEmployeeTemplate',
        security: [['bearerAuth' => []]],
        tags: ['Employees']
    )]
    #[OA\Response(response: 200, description: 'Excel template file download')]
    #[OA\Response(response: 500, description: 'Failed to generate template')]
    public function downloadEmployeeTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;

            // Remove default sheet and create employee data sheet
            $spreadsheet->removeSheetByIndex(0);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Employee Data');

            // Define all columns based on EmployeesImport
            $columns = [
                'A' => ['header' => 'org', 'width' => 10, 'validation' => 'String - NULLABLE - Max 10 chars - Organization code'],
                'B' => ['header' => 'staff_id', 'width' => 15, 'validation' => 'String - NOT NULL - Max 50 chars - Must be unique'],
                'C' => ['header' => 'initial', 'width' => 10, 'validation' => 'String - NULLABLE - Max 10 chars - Initial (EN)'],
                'D' => ['header' => 'first_name', 'width' => 20, 'validation' => 'String - NOT NULL - Max 255 chars - First name (EN)'],
                'E' => ['header' => 'last_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 255 chars - Last name (EN)'],
                'F' => ['header' => 'initial_th', 'width' => 10, 'validation' => 'String - NULLABLE - Max 20 chars - Initial (TH)'],
                'G' => ['header' => 'first_name_th', 'width' => 20, 'validation' => 'String - NULLABLE - Max 255 chars - First name (TH)'],
                'H' => ['header' => 'last_name_th', 'width' => 20, 'validation' => 'String - NULLABLE - Max 255 chars - Last name (TH)'],
                'I' => ['header' => 'gender', 'width' => 10, 'validation' => 'String - NOT NULL - Values: M, F'],
                'J' => ['header' => 'date_of_birth', 'width' => 15, 'validation' => 'Date - NOT NULL - Format: YYYY-MM-DD or Excel date'],
                'K' => ['header' => 'age', 'width' => 10, 'validation' => 'Formula - AUTO CALCULATED - Do not edit'],
                'L' => ['header' => 'status', 'width' => 20, 'validation' => 'String - NULLABLE - Values: Expats (Local), Local ID Staff, Local non ID Staff'],
                'M' => ['header' => 'nationality', 'width' => 15, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'N' => ['header' => 'religion', 'width' => 15, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'O' => ['header' => 'id_type', 'width' => 15, 'validation' => 'String - NULLABLE - Values: 10 years ID, Burmese ID, CI, Borderpass, Thai ID, Passport, Other'],
                'P' => ['header' => 'id_no', 'width' => 20, 'validation' => 'String - NULLABLE - ID number'],
                'Q' => ['header' => 'social_security_no', 'width' => 20, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'R' => ['header' => 'tax_no', 'width' => 20, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'S' => ['header' => 'driver_license', 'width' => 20, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'T' => ['header' => 'bank_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'U' => ['header' => 'bank_branch', 'width' => 20, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'V' => ['header' => 'bank_acc_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'W' => ['header' => 'bank_acc_no', 'width' => 20, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'X' => ['header' => 'mobile_no', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'Y' => ['header' => 'current_address', 'width' => 30, 'validation' => 'Text - NULLABLE'],
                'Z' => ['header' => 'permanent_address', 'width' => 30, 'validation' => 'Text - NULLABLE'],
                'AA' => ['header' => 'marital_status', 'width' => 15, 'validation' => 'String - NULLABLE - Values: Single, Married, Divorced, Widowed'],
                'AB' => ['header' => 'spouse_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 200 chars'],
                'AC' => ['header' => 'spouse_mobile_no', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'AD' => ['header' => 'emergency_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'AE' => ['header' => 'relationship', 'width' => 15, 'validation' => 'String - NULLABLE - Max 100 chars'],
                'AF' => ['header' => 'emergency_mobile_no', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'AG' => ['header' => 'father_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 200 chars'],
                'AH' => ['header' => 'father_occupation', 'width' => 20, 'validation' => 'String - NULLABLE - Max 200 chars'],
                'AI' => ['header' => 'father_mobile_no', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'AJ' => ['header' => 'mother_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 200 chars'],
                'AK' => ['header' => 'mother_occupation', 'width' => 20, 'validation' => 'String - NULLABLE - Max 200 chars'],
                'AL' => ['header' => 'mother_mobile_no', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'AM' => ['header' => 'kin1_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 255 chars - Beneficiary 1'],
                'AN' => ['header' => 'kin1_relationship', 'width' => 15, 'validation' => 'String - NULLABLE - Max 255 chars'],
                'AO' => ['header' => 'kin1_mobile', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'AP' => ['header' => 'kin2_name', 'width' => 20, 'validation' => 'String - NULLABLE - Max 255 chars - Beneficiary 2'],
                'AQ' => ['header' => 'kin2_relationship', 'width' => 15, 'validation' => 'String - NULLABLE - Max 255 chars'],
                'AR' => ['header' => 'kin2_mobile', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'AS' => ['header' => 'military_status', 'width' => 15, 'validation' => 'String - NULLABLE - Max 50 chars'],
                'AT' => ['header' => 'remark', 'width' => 30, 'validation' => 'String - NULLABLE - Max 255 chars'],
            ];

            // Set column widths and headers
            foreach ($columns as $col => $data) {
                $sheet->getColumnDimension($col)->setWidth($data['width']);
                $sheet->setCellValue($col.'1', $data['header']);
                $sheet->setCellValue($col.'2', $data['validation']);
            }

            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ];
            $sheet->getStyle('A1:AT1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Style validation row
            $validationStyle = [
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '666666']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF9E6']],
                'alignment' => ['wrapText' => true],
            ];
            $sheet->getStyle('A2:AT2')->applyFromArray($validationStyle);
            $sheet->getRowDimension(2)->setRowHeight(30);

            // Add sample data (2 rows)
            $sampleData = [
                ['SMRU', 'EMP001', 'Mr.', 'John', 'Doe', 'นาย', 'จอห์น', 'โด', 'M', '1990-01-15', '', 'Local ID Staff', 'Thai', 'Buddhist', 'Thai ID', '1234567890123', 'SS123456', 'TAX123456', 'DL123456', 'Bangkok Bank', 'Headquarters', 'John Doe', '1234567890', '0812345678', '123 Main St, Bangkok', '456 Home St, Bangkok', 'Single', '', '', 'Jane Doe', 'Sister', '0823456789', 'Robert Doe', 'Engineer', '0834567890', 'Mary Doe', 'Teacher', '0845678901', 'Jane Doe', 'Sister', '0823456789', '', '', '', '', '', '', 'Completed', 'New employee'],
                ['BHF', 'EMP002', 'Ms.', 'Sarah', 'Smith', 'นางสาว', 'ซาร่าห์', 'สมิธ', 'F', '1985-05-20', '', 'Expats (Local)', 'American', 'Christian', 'Passport', 'P1234567', 'SS234567', 'TAX234567', '', 'Kasikorn Bank', 'Silom Branch', 'Sarah Smith', '0987654321', '0898765432', '789 Office Rd, Bangkok', '321 Apartment, Bangkok', 'Married', 'Tom Smith', '0887654321', 'Emergency Contact', 'Friend', '0876543210', 'David Smith', 'Doctor', '0865432109', 'Linda Smith', 'Nurse', '0854321098', 'Tom Smith', 'Spouse', '0887654321', '', '', '', '', '', '', 'Exempt', 'Senior staff'],
            ];

            foreach ($sampleData as $rowIndex => $rowData) {
                $rowNum = $rowIndex + 3;
                $colIndex = 0;
                foreach ($columns as $col => $colData) {
                    if ($colIndex < count($rowData)) {
                        $value = $rowData[$colIndex];
                        $sheet->setCellValue($col.$rowNum, $value);

                        // Add age formula for column K
                        if ($col === 'K' && isset($rowData[9])) {
                            $sheet->setCellValue($col.$rowNum, '=DATEDIF(J'.$rowNum.',TODAY(),"Y")');
                        }
                    }
                    $colIndex++;
                }
            }

            // Add data validation for dropdowns
            // Gender dropdown (column I)
            for ($row = 3; $row <= 1000; $row++) {
                $validation = $sheet->getCell('I'.$row)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Invalid Gender');
                $validation->setError('Please select M or F');
                $validation->setPromptTitle('Gender');
                $validation->setPrompt('Select M (Male) or F (Female)');
                $validation->setFormula1('"M,F"');
            }

            // ID Type dropdown (column O)
            for ($row = 3; $row <= 1000; $row++) {
                $validation = $sheet->getCell('O'.$row)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Invalid ID Type');
                $validation->setError('Please select from the list');
                $validation->setPromptTitle('ID Type');
                $validation->setPrompt('Select ID type');
                $validation->setFormula1('"10 years ID,Burmese ID,CI,Borderpass,Thai ID,Passport,Other"');
            }

            // Identity Status dropdown (column L)
            for ($row = 3; $row <= 1000; $row++) {
                $validation = $sheet->getCell('L'.$row)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Invalid Identity Status');
                $validation->setError('Please select from the list');
                $validation->setPromptTitle('Identity Status');
                $validation->setPrompt('Select employee identity status');
                $validation->setFormula1('"Expats (Local),Local ID Staff,Local non ID Staff"');
            }

            // Marital Status dropdown (column AA)
            for ($row = 3; $row <= 1000; $row++) {
                $validation = $sheet->getCell('AA'.$row)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Invalid Marital Status');
                $validation->setError('Please select from the list');
                $validation->setPromptTitle('Marital Status');
                $validation->setPrompt('Select marital status');
                $validation->setFormula1('"Single,Married,Divorced,Widowed"');
            }

            // Freeze header rows
            $sheet->freezePane('A3');

            // Set active sheet
            $spreadsheet->setActiveSheetIndex(0);

            // Generate filename with timestamp
            $filename = 'employee_import_template_'.date('Y-m-d_His').'.xlsx';

            // Create writer and save to temporary file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'employee_template_');
            $writer->save($tempFile);

            // Return file download response with proper CORS headers
            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportEmployees()
    {
        return Excel::download(new EmployeesExport, 'employees.xlsx');
    }

    /**
     * Get all employees with advanced filtering and sorting
     */
    #[OA\Get(
        path: '/employees',
        summary: 'Get all employees with advanced filtering and sorting',
        description: 'Returns a paginated list of employees with comprehensive filtering, sorting capabilities and statistics',
        operationId: 'getEmployees',
        security: [['bearerAuth' => []]],
        tags: ['Employees']
    )]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'filter_organization', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filter_status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 500, description: 'Server error')]
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters - matching GrantController exactly
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_organization' => 'string|nullable',
                'filter_status' => 'string|nullable',
                'filter_gender' => 'string|nullable',
                'filter_age' => 'integer|nullable',
                'filter_id_type' => 'string|nullable',
                'filter_staff_id' => 'string|nullable',
                'sort_by' => 'string|nullable|in:organization,staff_id,first_name_en,last_name_en,gender,date_of_birth,status,age,id_type',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query using model scopes for optimization
            $query = Employee::forPagination()
                ->withOptimizedRelations();

            // Apply filters if provided
            if (! empty($validated['filter_organization'])) {
                $query->byOrganization($validated['filter_organization']);
            }

            if (! empty($validated['filter_status'])) {
                $query->byStatus($validated['filter_status']);
            }

            if (! empty($validated['filter_gender'])) {
                $query->byGender($validated['filter_gender']);
            }

            if (! empty($validated['filter_age'])) {
                $query->byAge($validated['filter_age']);
            }

            if (! empty($validated['filter_id_type'])) {
                $query->byIdType($validated['filter_id_type']);
            }

            if (! empty($validated['filter_staff_id'])) {
                $query->where('staff_id', 'like', '%'.$validated['filter_staff_id'].'%');
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Validate sort field and apply sorting
            if (in_array($sortBy, ['organization', 'staff_id', 'first_name_en', 'last_name_en', 'gender', 'date_of_birth', 'status'])) {
                $query->orderBy('employees.'.$sortBy, $sortOrder);
            } elseif ($sortBy === 'age') {
                // Sort by age means sort by date_of_birth in reverse order
                $query->orderBy('employees.date_of_birth', $sortOrder === 'asc' ? 'desc' : 'asc');
            } elseif ($sortBy === 'id_type') {
                // Sort by id_type from relationship - need to specify table aliases to avoid ambiguous column names
                $query->leftJoin('employee_identifications as ei', 'employees.id', '=', 'ei.employee_id')
                    ->orderBy('ei.id_type', $sortOrder);
            } else {
                $query->orderBy('employees.created_at', 'desc');
            }

            // Execute pagination
            $employees = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_organization'])) {
                $appliedFilters['organization'] = explode(',', $validated['filter_organization']);
            }
            if (! empty($validated['filter_status'])) {
                $appliedFilters['status'] = explode(',', $validated['filter_status']);
            }
            if (! empty($validated['filter_gender'])) {
                $appliedFilters['gender'] = explode(',', $validated['filter_gender']);
            }
            if (! empty($validated['filter_age'])) {
                $appliedFilters['age'] = $validated['filter_age'];
            }
            if (! empty($validated['filter_id_type'])) {
                $appliedFilters['id_type'] = explode(',', $validated['filter_id_type']);
            }
            if (! empty($validated['filter_staff_id'])) {
                $appliedFilters['staff_id'] = $validated['filter_staff_id'];
            }

            // Calculate statistics using the model's static method
            $statistics = Employee::getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Employees retrieved successfully',
                'data' => EmployeeResource::collection($employees->items()),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total(),
                    'last_page' => $employees->lastPage(),
                    'from' => $employees->firstItem(),
                    'to' => $employees->lastItem(),
                    'has_more_pages' => $employees->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employees',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/employees/staff-id/{staff_id}',
        summary: 'Get a single employee',
        description: 'Returns employee(s) by staff ID with related data',
        operationId: 'getEmployeeByStaffId',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'staff_id', in: 'path', required: true, description: 'Staff ID of the employee', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function show(ShowEmployeeRequest $request, string $staff_id)
    {

        // 1) base query: Remove 'active' from employment selection
        $query = Employee::select([
            'id',
            'organization',
            'staff_id',
            'initial_en',
            'first_name_en',
            'last_name_en',
            'gender',
            'date_of_birth',
            'status',
            'social_security_number',
            'tax_number',
            'mobile_phone',
        ])
            ->with([
                'employeeIdentification:id,employee_id,id_type,document_number,issue_date,expiry_date',
                'employment:id,employee_id,start_date,end_date', // Removed 'active', added 'end_date'
                'employeeEducation:id,employee_id,school_name,degree,start_date,end_date',
            ]);

        // 2) exact match on staff_id
        $employees = $query->where('staff_id', $staff_id)->get();

        // 3) if none found, return 404
        if ($employees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No employee found with staff_id = {$staff_id}",
            ], 404);
        }

        // 4) wrap in a collection resource so `data` is an array
        return (new EmployeeCollection($employees))
            ->additional([
                'success' => true,
                'message' => 'Employee(s) retrieved successfully',
            ]);
    }

    #[OA\Get(
        path: '/employees/{id}',
        summary: 'Get employee details',
        description: 'Returns employee details by ID with related data including employment, grant allocations, beneficiaries, identification, and leave balances',
        operationId: 'getEmployeeDetailsById',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of the employee', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function employeeDetails(Request $request, $id)
    {
        $employee = Employee::with([
            'employment',
            'employment.department',
            'employment.position',
            'employment.site',
            'employeeFundingAllocations',
            'employeeFundingAllocations.grantItem',
            'employeeFundingAllocations.grantItem.grant',
            'employeeFundingAllocations.grant',
            'employeeFundingAllocations.employment',
            'employeeFundingAllocations.employment.department',
            'employeeFundingAllocations.employment.position',
            'employeeBeneficiaries',
            'employeeIdentification',
            'employeeEducation',
            'employeeChildren',
            'employeeLanguages',
            'leaveBalances',
            'leaveBalances.leaveType',
        ])->find($id);

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee retrieved successfully',
            'data' => $employee,
        ], 200);
    }

    #[OA\Post(
        path: '/employees',
        summary: 'Create a new employee',
        description: 'Creates a new employee with optional identifications and beneficiaries',
        operationId: 'createEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Employee data',
            content: new OA\JsonContent(ref: '#/components/schemas/Employee')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Employee created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function store(StoreEmployeeRequest $request)
    {
        try {
            $validated = $request->validated();
            $employee = Employee::create($validated);

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                app(NotificationService::class)->notifyByModule(
                    'employees',
                    new EmployeeActionNotification('created', $employee, $performedBy, 'employees'),
                    'created'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/employees/{id}',
        summary: 'Update employee details',
        description: 'Updates an existing employee record with the provided information',
        operationId: 'updateEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employee ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Employee information to update',
            content: new OA\JsonContent(ref: '#/components/schemas/Employee')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employee updated successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $employee = Employee::findOrFail($id);

            $validated = $request->validate([
                'staff_id' => "required|string|max:50|unique:employees,staff_id,{$id}",
                'organization' => 'nullable|string|in:SMRU,BHF',
                'user_id' => 'nullable|integer|exists:users,id',
                'initial_en' => 'nullable|string|max:10',
                'initial_th' => 'nullable|string|max:10',
                'first_name_en' => 'required|string|max:255',
                'last_name_en' => 'required|string|max:255',
                'first_name_th' => 'nullable|string|max:255',
                'last_name_th' => 'nullable|string|max:255',
                'gender' => 'required|string|max:10',
                'date_of_birth' => 'required|date',
                'status' => 'required|string|in:Expats,Local ID,Local non ID',
                'nationality' => 'nullable|string|max:100',
                'religion' => 'nullable|string|max:100',
                'social_security_number' => 'nullable|string|max:50',
                'tax_number' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
                'bank_branch' => 'nullable|string|max:100',
                'bank_account_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:100',
                'mobile_phone' => 'nullable|string|max:20',
                'permanent_address' => 'nullable|string',
                'current_address' => 'nullable|string',
                'military_status' => 'nullable|boolean',
                'marital_status' => 'nullable|string|max:20',
                'spouse_name' => 'nullable|string|max:100',
                'spouse_phone_number' => 'nullable|string|max:20',
                'emergency_contact_person_name' => 'nullable|string|max:100',
                'emergency_contact_person_relationship' => 'nullable|string|max:50',
                'emergency_contact_person_phone' => 'nullable|string|max:20',
                'father_name' => 'nullable|string|max:100',
                'father_occupation' => 'nullable|string|max:100',
                'father_phone_number' => 'nullable|string|max:20',
                'mother_name' => 'nullable|string|max:100',
                'mother_occupation' => 'nullable|string|max:100',
                'mother_phone_number' => 'nullable|string|max:20',
                'driver_license_number' => 'nullable|string|max:50',
                'remark' => 'nullable|string',
            ]);

            $employee->update($validated + [
                'updated_by' => auth()->user()->name ?? 'system',
            ]);

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                $employee->refresh();
                app(NotificationService::class)->notifyByModule(
                    'employees',
                    new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => $employee,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/employees/{id}',
        summary: 'Delete an employee',
        description: 'Deletes an employee by ID',
        operationId: 'deleteEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employee ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employee deleted successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $employee = Employee::findOrFail($id);

            DB::beginTransaction();

            // Delete related records first to maintain referential integrity
            // Note: Some tables have cascadeOnDelete, but we explicitly delete for better control
            EmployeeFundingAllocation::where('employee_id', $id)->delete();
            EmployeeBeneficiary::where('employee_id', $id)->delete();
            EmployeeIdentification::where('employee_id', $id)->delete();
            EmployeeChild::where('employee_id', $id)->delete();
            EmployeeEducation::where('employee_id', $id)->delete();
            EmployeeLanguage::where('employee_id', $id)->delete();
            EmployeeTraining::where('employee_id', $id)->delete();
            EmploymentHistory::where('employee_id', $id)->delete();
            LeaveBalance::where('employee_id', $id)->delete();
            LeaveRequest::where('employee_id', $id)->delete();
            TravelRequest::where('employee_id', $id)->delete();
            Employment::where('employee_id', $id)->delete();

            // Store employee data before deletion for notification
            $employeeData = (object) [
                'id' => $employee->id,
                'staff_id' => $employee->staff_id,
                'first_name_en' => $employee->first_name_en,
                'last_name_en' => $employee->last_name_en,
            ];

            // Delete the employee
            $employee->delete();

            DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                app(NotificationService::class)->notifyByModule(
                    'employees',
                    new EmployeeActionNotification('deleted', $employeeData, $performedBy, 'employees'),
                    'deleted'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete employee: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/employees/site-records',
        summary: 'Get Site records',
        description: 'Returns a list of all work locations/sites',
        operationId: 'getSiteRecords',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
        ]
    )]
    public function getSiteRecords()
    {
        $sites = Site::all();

        return response()->json([
            'success' => true,
            'message' => 'Site records retrieved successfully',
            'data' => $sites,
        ], 200);
    }

    #[OA\Get(
        path: '/employees/filter',
        summary: 'Filter employees by criteria',
        description: 'Returns employees filtered by the provided criteria',
        operationId: 'filterEmployees',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'staff_id', in: 'query', required: false, description: 'Staff ID to filter by', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Employee status to filter by', schema: new OA\Schema(type: 'string', enum: ['Expats', 'Local ID', 'Local non ID'])),
            new OA\Parameter(name: 'organization', in: 'query', required: false, description: 'Employee organization to filter by', schema: new OA\Schema(type: 'string', enum: ['SMRU', 'BHF'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Filtered employees retrieved successfully'),
            new OA\Response(response: 404, description: 'No employees found matching the criteria'),
        ]
    )]
    public function filterEmployees(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'nullable|string|max:50',
            'status' => 'nullable|in:Expats,Local ID,Local non ID',
            'organization' => 'nullable|in:SMRU,BHF',
        ]);

        $query = Employee::query();

        if (! empty($validated['staff_id'])) {
            $query->where('staff_id', 'like', '%'.$validated['staff_id'].'%');
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['organization'])) {
            $query->where('organization', $validated['organization']);
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No employees found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => $employees,
        ], 200);
    }

    #[OA\Post(
        path: '/employees/{id}/profile-picture',
        summary: 'Upload profile picture',
        description: 'Upload a profile picture for an employee',
        operationId: 'uploadProfilePictureEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employee ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Profile picture file',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['profile_picture'],
                    properties: [
                        new OA\Property(property: 'profile_picture', type: 'string', format: 'binary', description: 'Profile picture file (jpeg, png, jpg, gif, svg, max 2MB)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile picture uploaded successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function uploadProfilePicture(Request $request, $id)
    {
        try {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            $employee = Employee::findOrFail($id);

            // Delete old profile picture if exists
            if ($employee->profile_picture) {
                Storage::disk('public')->delete($employee->profile_picture);
            }

            // Store new profile picture
            $path = $request->file('profile_picture')->store('employee/profile_pictures', 'public');

            // Update employee record
            $employee->profile_picture = $path;
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'data' => [
                    'profile_picture' => $path,
                    'url' => Storage::disk('public')->url($path),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate uploaded file
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Added max file size
        ]);
    }

    /**
     * Convert string value to float
     *
     * @param  mixed  $value  Value to convert
     * @return float|null Converted float value or null if input is null
     */
    private function toFloat($value): ?float
    {
        if (is_null($value)) {
            return null;
        }

        return floatval(preg_replace('/[^0-9.-]/', '', $value));
    }

    /**
     * Clear employee statistics cache
     */
    private function clearEmployeeStatisticsCache(): void
    {
        \Cache::forget('employee_statistics');
    }

    // employee grant-item add
    public function addEmployeeGrantItem(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'grant_item_id' => 'required|exists:grant_items,id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date',
                'amount' => 'required|numeric',
                'currency' => 'required|string',
                'payment_method' => 'required|string',
                'payment_account' => 'required|string',
                'payment_account_name' => 'required|string',
            ]);

            $employee = Employee::findOrFail($validated['employee_id']);
            $grantItem = GrantItem::findOrFail($validated['grant_item_id']);

            $employee->grant_items()->attach($grantItem, [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'payment_method' => $validated['payment_method'],
                'payment_account' => $validated['payment_account'],
                'payment_account_name' => $validated['payment_account_name'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee grant-item added successfully',
                'data' => $employee,
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee or grant item not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add employee grant-item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/employees/{employee}/basic-information',
        summary: 'Update employee basic information',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, description: 'Employee ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Employee')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employee basic information updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function updateEmployeeBasicInformation(UpdateEmployeeRequest $request, Employee $employee)
    {
        \Log::info('Employee injected by route model binding', ['employee' => $employee]);

        try {
            // validate the request
            $validated = $request->validated();

            // update the employee
            $employee->update($validated);

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                $employee->refresh();
                app(NotificationService::class)->notifyByModule(
                    'employees',
                    new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee basic information updated successfully',
                'data' => $employee,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error updating employee basic information', [
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee basic information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/employees/{employee}/personal-information',
        summary: 'Update employee personal information',
        description: 'Update the personal information of an employee, including identification and languages',
        operationId: 'updateEmployeePersonalInformation',
        tags: ['Employees'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, description: 'ID of the employee', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Employee')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employee personal information updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function updateEmployeePersonalInformation(UpdateEmployeePersonalRequest $request, Employee $employee)
    {
        \Log::info('Employee injected by route model binding', ['employee' => $employee]);

        \DB::beginTransaction();

        try {
            // Get only main Employee table fields (filter out relations)
            $validated = $request->safe()->except(['employee_identification', 'languages']);

            // Update Employee main table
            $employee->update($validated);

            // --------- Update Employee Identification (One-to-One) ---------
            if ($request->has('employee_identification')) {
                $identData = $request->input('employee_identification');
                if ($employee->employeeIdentification) {
                    $employee->employeeIdentification()->update($identData);
                } else {
                    $employee->employeeIdentification()->create($identData);
                }
            }

            // --------- Update Employee Languages (Many) ---------
            if ($request->has('languages')) {
                $languages = $request->input('languages');
                // Remove existing language records for this employee
                $employee->employeeLanguages()->delete();

                // Insert new language records
                foreach ($languages as $lang) {
                    $employee->employeeLanguages()->create([
                        'language' => is_array($lang) ? ($lang['language'] ?? '') : $lang,
                        'proficiency_level' => is_array($lang) ? ($lang['proficiency_level'] ?? null) : null,
                        'created_by' => auth()->id() ?? 'system',
                    ]);
                }
            }

            \DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            // Reload employee with relations if needed for API response
            $employee->load('employeeIdentification', 'employeeLanguages');

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                $employee->refresh();
                app(NotificationService::class)->notifyByModule(
                    'employees',
                    new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee personal information updated successfully',
                'data' => $employee,
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error updating employee personal information', [
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee personal information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/employees/{employee}/family-information',
        summary: 'Update employee family information',
        description: 'Update the family information of an employee including parents and emergency contact',
        operationId: 'updateEmployeeFamilyInformation',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, description: 'ID of the employee', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/Employee')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employee family information updated successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function updateEmployeeFamilyInformation(UpdateEmployeeFamilyRequest $request, Employee $employee)
    {
        try {
            $validated = $request->validated();

            $fieldMapping = [
                'father_name' => 'father_name',
                'father_occupation' => 'father_occupation',
                'father_phone' => 'father_phone_number',
                'mother_name' => 'mother_name',
                'mother_occupation' => 'mother_occupation',
                'mother_phone' => 'mother_phone_number',
                'spouse_name' => 'spouse_name',
                'spouse_phone_number' => 'spouse_phone_number',
                'emergency_contact_name' => 'emergency_contact_person_name',
                'emergency_contact_relationship' => 'emergency_contact_person_relationship',
                'emergency_contact_phone' => 'emergency_contact_person_phone',
            ];

            $updateData = [];
            foreach ($fieldMapping as $inputKey => $column) {
                if (array_key_exists($inputKey, $validated)) {
                    $updateData[$column] = $validated[$inputKey];
                }
            }

            // Audit
            $updateData['updated_by'] = auth()->user()->name ?? 'system';

            if (! empty($updateData)) {
                $employee->update($updateData);
            }

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            $responseData = $employee->only([
                'father_name',
                'father_occupation',
                'father_phone_number',
                'mother_name',
                'mother_occupation',
                'mother_phone_number',
                'spouse_name',
                'spouse_phone_number',
                'emergency_contact_person_name',
                'emergency_contact_person_relationship',
                'emergency_contact_person_phone',
            ]);

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                $employee->refresh();
                app(NotificationService::class)->notifyByModule(
                    'employees',
                    new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee family information updated successfully',
                'data' => $responseData,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error updating employee family information', [
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee family information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Add method to check import status
    public function getImportStatus(string $importId)
    {
        $stats = Cache::get("import_{$importId}_stats");

        if (! $stats) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Import status retrieved successfully',
            'data' => $stats,
        ], 200);
    }

    #[OA\Put(
        path: '/employees/{id}/bank-information',
        summary: 'Update employee bank information',
        description: 'Updates bank details for a specific employee including bank name, branch, account name and account number',
        operationId: 'updateEmployeeBankInformation',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employee ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Bank information to update',
            content: new OA\JsonContent(ref: '#/components/schemas/Employee')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Bank information updated successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function updateBankInformation(UpdateEmployeeBankRequest $request, $id)
    {
        try {
            $employee = Employee::findOrFail($id);

            $validated = $request->validated();

            // Add audit fields
            $validated['updated_by'] = auth()->user()->name ?? 'system';

            // Update only bank-related fields
            $employee->update($validated);

            // Clear cache to ensure fresh statistics
            $this->clearEmployeeStatisticsCache();

            // Return only bank information in response
            $bankInfo = $employee->only([
                'bank_name',
                'bank_branch',
                'bank_account_name',
                'bank_account_number',
            ]);

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                $employee->refresh();
                app(NotificationService::class)->notifyByModule(
                    'employees',
                    new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank information updated successfully',
                'data' => $bankInfo,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update employee bank information: '.$e->getMessage(), [
                'employee_id' => $id,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Add new method here

}
