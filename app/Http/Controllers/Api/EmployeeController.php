<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use Illuminate\Validation\Rule;
use App\Models\WorkLocation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * @OA\Tag(
 *     name="Employees",
 *     description="API Endpoints for Employee management"
 * )
 */
class EmployeeController extends Controller
{

    /**
     * @OA\Post(
     *     path="/employees/upload",
     *     summary="Upload employee data from Excel file",
     *     description="Upload an Excel file where each row contains data for creating an employee record. Identifications and beneficiaries can also be handled if columns are provided and parsed into arrays.",
     *     operationId="uploadEmployeeData",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Excel file containing employee data",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file (xlsx, xls, or csv) with employee data"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee data uploaded successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee data upload completed"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="processed_employees", type="integer", example=5),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error (no file or invalid format)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to import employees",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to import employee data"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function uploadEmployeeData(Request $request)
    {
        // 1) Validate the uploaded file
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            // 2) Load the spreadsheet
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);

            // If there's no data or only headers, return early
            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid rows found in the uploaded file'
                ], 422);
            }

            $processedCount = 0;
            $errors         = [];

            // 3) Process each row (starting at row 2 if row 1 is headers)
            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i];

                // Skip the row if it doesn't contain any data
                if (!array_filter($row)) {
                    continue;
                }

                // Build employee data (without identifications or beneficiaries)
                $employeeData = [
                    'subsidiary'                   => $row['A'] ?? 'SMRU',
                    'staff_id'                     => $row['B'] ?? null,
                    'user_id'                      => null, // Map if available
                    'department_position_id'       => null, // Parse from Excel if available
                    'initial_en'                   => $row['C'] ?? null,
                    'first_name_en'                => $row['D'] ?? null,
                    'last_name_en'                 => $row['E'] ?? null,
                    'initial_th'                   => $row['F'] ?? null,
                    'first_name_th'                => $row['G'] ?? null,
                    'last_name_th'                 => $row['H'] ?? null,
                    'gender'                       => $row['I'] ?? null,
                    'date_of_birth'                => $this->parseDate($row['J'] ?? null),
                    'date_of_birth_th'             => $this->parseDate($row['K'] ?? null),
                    'age'                          => $row['L'] ?? null,
                    'status'                       => $row['M'] ?? null,
                    'nationality'                  => $row['N'] ?? null,
                    'religion'                     => $row['O'] ?? null,
                    'social_security_number'       => $row['R'] ?? null,
                    'tax_number'                   => $row['S'] ?? null,
                    'driver_license_number'        => $row['T'] ?? null,
                    'bank_name'                    => $row['U'] ?? null,
                    'bank_branch'                  => $row['V'] ?? null,
                    'bank_account_name'            => $row['W'] ?? null,
                    'bank_account_number'          => $row['X'] ?? null,
                    'mobile_phone'                 => $row['Y'] ?? null,
                    'current_address'              => $row['Z'] ?? null,
                    'permanent_address'            => $row['AA'] ?? null,
                    'marital_status'               => $row['AB'] ?? null,
                    'spouse_name'                  => $row['AC'] ?? null,
                    'spouse_phone_number'          => $row['AD'] ?? null,
                    'emergency_contact_person_name'=> $row['AE'] ?? null,
                    'emergency_contact_person_relationship' => $row['AF'] ?? null,
                    'emergency_contact_person_phone'=> $row['AG'] ?? null,
                    'father_name'                  => $row['AH'] ?? null,
                    'father_occupation'            => $row['AI'] ?? null,
                    'father_phone_number'          => $row['AJ'] ?? null,
                    'mother_name'                  => $row['AK'] ?? null,
                    'mother_occupation'            => $row['AL'] ?? null,
                    'mother_phone_number'          => $row['AM'] ?? null,
                    'military_status'              => $this->boolFromString($row['AT'] ?? null),
                    'remark'                       => $row['AU'] ?? null,
                ];



                // Build identifications array (for example, using columns P, Q for type & document number)
                $identifications = [];
                if (!empty($row['P']) && !empty($row['Q'])) {
                    $identifications[] = [
                        'id_type'         => $row['P'],
                        'document_number' => $row['Q'],
                        'issue_date'      => null,
                        'expiry_date'     => null,
                    ];
                }

                // Build beneficiaries array from Excel columns (for example, columns AN–AS)
                $beneficiaries = [];
                if (!empty($row['AN'])) {
                    $beneficiaries[] = [
                        'beneficiary_name'        => $row['AN'],
                        'beneficiary_relationship'=> $row['AO'] ?? null,
                        'phone_number'            => $row['AP'] ?? null,
                    ];
                }
                if (!empty($row['AQ'])) {
                    $beneficiaries[] = [
                        'beneficiary_name'        => $row['AQ'],
                        'beneficiary_relationship'=> $row['AR'] ?? null,
                        'phone_number'            => $row['AS'] ?? null,
                    ];
                }

                // Validate only the employee data first
                $employeeValidator = Validator::make($employeeData, [
                    'staff_id' => 'required|string|max:50|unique:employees,staff_id',
                    'subsidiary' => 'nullable|string|in:SMRU,BHF',
                    'user_id' => 'nullable|integer|exists:users,id',
                    'department_position_id' => 'nullable|integer|exists:department_positions,id',
                    'initial_en' => 'nullable|string|max:10',
                    'initial_th' => 'nullable|string|max:10',
                    'first_name_en' => 'required|string|max:255',
                    'last_name_en' => 'required|string|max:255',
                    'first_name_th' => 'nullable|string|max:255',
                    'last_name_th' => 'nullable|string|max:255',
                    'gender' => 'required|string|max:10',
                    'date_of_birth' => 'required|date',
                    'date_of_birth_th' => 'nullable|string',
                    'age' => 'nullable|integer',
                    'status' => 'required|string',
                    'nationality' => 'nullable|string|max:100',
                    'religion' => 'nullable|string|max:100',
                    'social_security_number' => 'nullable|string|max:50',
                    'tax_number' => 'nullable|string|max:50',
                    'bank_name' => 'nullable|string|max:100',
                    'bank_branch' => 'nullable|string|max:100',
                    'bank_account_name' => 'nullable|string|max:100',
                    'bank_account_number' => 'nullable|string|max:50',
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

                if ($employeeValidator->fails()) {
                    $errors[] = "Row $i: " . $employeeValidator->errors()->first() . " (Staff ID: {$employeeData['staff_id']})";
                    continue;
                }

                // Now create the employee and then load the related identifications and beneficiaries
                try {
                    DB::beginTransaction();

                    // Create employee first
                    $createdEmployee = Employee::create(array_merge($employeeData, [
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                    ]));

                    // Validate and create identifications (if any)
                    if (!empty($identifications)) {
                        $identificationValidator = Validator::make(
                            ['identifications' => $identifications],
                            [
                                'identifications' => 'array',
                                'identifications.*.id_type' => 'required|string',
                                'identifications.*.document_number' => 'required|string',
                                'identifications.*.issue_date' => 'nullable|date',
                                'identifications.*.expiry_date' => 'nullable|date',
                            ]
                        );
                        if ($identificationValidator->fails()) {
                            $errors[] = "Row $i: " . $identificationValidator->errors()->first() . " (Staff ID: {$employeeData['staff_id']})";
                            DB::rollBack();
                            continue;
                        }
                        foreach ($identifications as $identData) {
                            $createdEmployee->employeeIdentification()->create(array_merge($identData, [
                                'created_by' => auth()->user()->name ?? 'system',
                                'updated_by' => auth()->user()->name ?? 'system',
                            ]));
                        }
                    }

                    // Validate and create beneficiaries (if any)
                    if (!empty($beneficiaries)) {
                        $beneficiaryValidator = Validator::make(
                            ['beneficiaries' => $beneficiaries],
                            [
                                'beneficiaries' => 'array',
                                'beneficiaries.*.beneficiary_name' => 'required|string',
                                'beneficiaries.*.beneficiary_relationship' => 'required|string',
                                'beneficiaries.*.phone_number' => 'nullable|string',
                            ]
                        );
                        if ($beneficiaryValidator->fails()) {
                            $errors[] = "Row $i: " . $beneficiaryValidator->errors()->first() . " (Staff ID: {$employeeData['staff_id']})";
                            DB::rollBack();
                            continue;
                        }
                        foreach ($beneficiaries as $beneData) {
                            $createdEmployee->employeeBeneficiaries()->create(array_merge($beneData, [
                                'created_by' => auth()->user()->name ?? 'system',
                                'updated_by' => auth()->user()->name ?? 'system',
                            ]));
                        }
                    }

                    DB::commit();
                    $processedCount++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Row $i: Failed to create employee (Staff ID: {$employeeData['staff_id']}). Error: {$e->getMessage()}";
                }
            }

            // Final response
            return response()->json([
                'success' => true,
                'message' => 'Employee data upload completed',
                'data' => [
                    'processed_employees' => $processedCount,
                    'errors' => $errors,
                ],
            ], 200);

        } catch (\Exception $e) {
            // Catch any unexpected top-level errors
            return response()->json([
                'success' => false,
                'message' => 'Failed to import employee data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to parse date from Excel cell if needed.
     */
    private function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null; // or throw an exception
        }
    }

    /**
     * Convert a string like "Yes"/"No" to boolean if needed
     */
    private function boolFromString($value)
    {
        if (is_null($value)) {
            return false;
        }
        $lower = strtolower(trim($value));
        return in_array($lower, ['yes', '1', 'true']);
    }


    /**
     * Get all employees
     *
     * @OA\Get(
     *     path="/employees",
     *     summary="Get all employees",
     *     description="Returns a list of all employees with related data",
     *     operationId="getEmployees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employees retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Employee")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $employees = Employee::with([
            'employment',
            'employment.workLocation',
            'employment.departmentPosition',
            'employment.grantAllocations.grantItemAllocation',
            'employment.grantAllocations.grantItemAllocation.grant',
            'employeeBeneficiaries',
            'employeeIdentification'
        ])->get();

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => $employees
        ]);
    }

    /**
     * Get a single employee
     *
     * @OA\Get(
     *     path="/employees/{id}",
     *     summary="Get a single employee",
     *     description="Returns a single employee by ID with related data",
     *     operationId="getEmployeeById",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID of the employee", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Employee")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee not found")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $employee = Employee::with([
            'employment',
            'employment.workLocation',
            'employment.grantAllocations.grantItemAllocation',
            'employment.grantAllocations.grantItemAllocation.grant',
            'employeeBeneficiaries',
            'employeeIdentification'
        ])->find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee retrieved successfully',
            'data' => $employee
        ]);
    }

    /**
     * Create a new employee
     *
     * @OA\Post(
     *     path="/employees",
     *     summary="Create a new employee",
     *     description="Creates a new employee with optional identifications and beneficiaries",
     *     operationId="createEmployee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Employee data",
     *         @OA\JsonContent(
     *             required={"staff_id","first_name_en","last_name_en","gender","date_of_birth","status"},
     *             @OA\Property(property="staff_id", type="string", example="EMP001", description="Unique staff identifier"),
     *             @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}, example="SMRU", description="Employee subsidiary"),
     *             @OA\Property(property="user_id", type="integer", nullable=true, description="Associated user ID"),
     *             @OA\Property(property="department_position_id", type="integer", description="Department position ID"),
     *             @OA\Property(property="initial_en", type="string", example="Mr.", description="English initial/title"),
     *             @OA\Property(property="initial_th", type="string", example="นาย", description="Thai initial/title"),
     *             @OA\Property(property="first_name_en", type="string", example="John", description="Employee first name in English"),
     *             @OA\Property(property="last_name_en", type="string", example="Doe", description="Employee last name in English"),
     *             @OA\Property(property="first_name_th", type="string", example="จอห์น", description="Employee first name in Thai"),
     *             @OA\Property(property="last_name_th", type="string", example="โด", description="Employee last name in Thai"),
     *             @OA\Property(property="gender", type="string", example="male", description="Employee gender"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Employee date of birth"),
     *             @OA\Property(property="date_of_birth_th", type="string", example="01/01/2533", description="Employee date of birth in Thai format"),
     *             @OA\Property(property="age", type="integer", example=33, description="Employee age"),
     *             @OA\Property(property="status", type="string", enum={"Expats", "Local ID", "Local non ID"}, example="Expats", description="Employee status"),
     *             @OA\Property(property="nationality", type="string", example="Thai", description="Employee nationality"),
     *             @OA\Property(property="religion", type="string", example="Buddhism", description="Employee religion"),
     *             @OA\Property(property="social_security_number", type="string", example="SSN123456", description="Employee social security number"),
     *             @OA\Property(property="tax_number", type="string", example="TAX123456", description="Employee tax number"),
     *             @OA\Property(property="bank_name", type="string", example="Bangkok Bank", description="Employee bank name"),
     *             @OA\Property(property="bank_branch", type="string", example="Silom", description="Employee bank branch"),
     *             @OA\Property(property="bank_account_name", type="string", example="John Doe", description="Employee bank account name"),
     *             @OA\Property(property="bank_account_number", type="string", example="1234567890", description="Employee bank account number"),
     *             @OA\Property(property="mobile_phone", type="string", example="0812345678", description="Employee mobile phone"),
     *             @OA\Property(property="permanent_address", type="string", example="123 Main St, Bangkok", description="Employee permanent address"),
     *             @OA\Property(property="current_address", type="string", example="456 Second St, Bangkok", description="Employee current address"),
     *             @OA\Property(property="military_status", type="boolean", example=false, description="Employee military status"),
     *             @OA\Property(property="marital_status", type="string", example="Single", description="Employee marital status"),
     *             @OA\Property(property="spouse_name", type="string", example="Jane Doe", description="Employee spouse name"),
     *             @OA\Property(property="spouse_phone_number", type="string", example="0812345679", description="Employee spouse phone number"),
     *             @OA\Property(property="emergency_contact_person_name", type="string", example="James Doe", description="Emergency contact name"),
     *             @OA\Property(property="emergency_contact_person_relationship", type="string", example="Father", description="Emergency contact relationship"),
     *             @OA\Property(property="emergency_contact_person_phone", type="string", example="0812345680", description="Emergency contact phone"),
     *             @OA\Property(property="father_name", type="string", example="James Doe", description="Employee father name"),
     *             @OA\Property(property="father_occupation", type="string", example="Engineer", description="Employee father occupation"),
     *             @OA\Property(property="father_phone_number", type="string", example="0812345681", description="Employee father phone number"),
     *             @OA\Property(property="mother_name", type="string", example="Mary Doe", description="Employee mother name"),
     *             @OA\Property(property="mother_occupation", type="string", example="Teacher", description="Employee mother occupation"),
     *             @OA\Property(property="mother_phone_number", type="string", example="0812345682", description="Employee mother phone number"),
     *             @OA\Property(property="driver_license_number", type="string", example="DL123456", description="Employee driver license number"),
     *             @OA\Property(property="remark", type="string", example="Additional notes", description="Additional remarks"),
     *             @OA\Property(
     *                 property="identifications",
     *                 type="array",
     *                 description="Employee identification documents",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id_type", type="string", example="Passport", description="Type of identification"),
     *                     @OA\Property(property="document_number", type="string", example="P123456", description="Document number"),
     *                     @OA\Property(property="issue_date", type="string", format="date", example="2020-01-01", description="Issue date"),
     *                     @OA\Property(property="expiry_date", type="string", format="date", example="2030-01-01", description="Expiry date")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="beneficiaries",
     *                 type="array",
     *                 description="Employee beneficiaries",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="beneficiary_name", type="string", example="Jane Doe", description="Beneficiary name"),
     *                     @OA\Property(property="beneficiary_relationship", type="string", example="Spouse", description="Relationship to employee"),
     *                     @OA\Property(property="phone_number", type="string", example="0812345679", description="Beneficiary phone number")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Employee")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|string|max:50|unique:employees',
            'subsidiary' => 'nullable|string|in:SMRU,BHF',
            'user_id' => 'nullable|integer|exists:users,id',
            'department_position_id' => 'required|integer|exists:department_positions,id',
            'initial_en' => 'nullable|string|max:10',
            'initial_th' => 'nullable|string|max:10',
            'first_name_en' => 'required|string|max:255',
            'last_name_en' => 'required|string|max:255',
            'first_name_th' => 'nullable|string|max:255',
            'last_name_th' => 'nullable|string|max:255',
            'gender' => 'required|string|max:10',
            'date_of_birth' => 'required|date',
            'date_of_birth_th' => 'nullable|string',
            'age' => 'nullable|integer',
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
            'identifications' => 'nullable|array',
            'identifications.*.id_type' => 'required|string',
            'identifications.*.document_number' => 'required|string',
            'identifications.*.issue_date' => 'nullable|date',
            'identifications.*.expiry_date' => 'nullable|date',
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.beneficiary_name' => 'required|string',
            'beneficiaries.*.beneficiary_relationship' => 'required|string',
            'beneficiaries.*.phone_number' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Create the employee
            $employee = Employee::create([
                'user_id' => $request->input('user_id'),
                'department_position_id' => $request->input('department_position_id'),
                'subsidiary' => $request->input('subsidiary', 'SMRU'),
                'staff_id' => $request->input('staff_id'),
                'initial_en' => $request->input('initial_en'),
                'initial_th' => $request->input('initial_th'),
                'first_name_en' => $request->input('first_name_en'),
                'last_name_en' => $request->input('last_name_en'),
                'first_name_th' => $request->input('first_name_th'),
                'last_name_th' => $request->input('last_name_th'),
                'gender' => $request->input('gender'),
                'date_of_birth' => $request->input('date_of_birth'),
                'date_of_birth_th' => $request->input('date_of_birth_th'),
                'age' => $request->input('age'),
                'status' => $request->input('status'),
                'nationality' => $request->input('nationality'),
                'religion' => $request->input('religion'),
                'social_security_number' => $request->input('social_security_number'),
                'tax_number' => $request->input('tax_number'),
                'bank_name' => $request->input('bank_name'),
                'bank_branch' => $request->input('bank_branch'),
                'bank_account_name' => $request->input('bank_account_name'),
                'bank_account_number' => $request->input('bank_account_number'),
                'mobile_phone' => $request->input('mobile_phone'),
                'permanent_address' => $request->input('permanent_address'),
                'current_address' => $request->input('current_address'),
                'military_status' => $request->input('military_status', false),
                'marital_status' => $request->input('marital_status'),
                'spouse_name' => $request->input('spouse_name'),
                'spouse_phone_number' => $request->input('spouse_phone_number'),
                'emergency_contact_person_name' => $request->input('emergency_contact_person_name'),
                'emergency_contact_person_relationship' => $request->input('emergency_contact_person_relationship'),
                'emergency_contact_person_phone' => $request->input('emergency_contact_person_phone'),
                'father_name' => $request->input('father_name'),
                'father_occupation' => $request->input('father_occupation'),
                'father_phone_number' => $request->input('father_phone_number'),
                'mother_name' => $request->input('mother_name'),
                'mother_occupation' => $request->input('mother_occupation'),
                'mother_phone_number' => $request->input('mother_phone_number'),
                'driver_license_number' => $request->input('driver_license_number'),
                'remark' => $request->input('remark'),
                'created_by' => auth()->user()->name ?? 'system',
                'updated_by' => auth()->user()->name ?? 'system',
            ]);

            // Create employee identifications if provided
            if ($request->has('identifications')) {
                foreach ($request->input('identifications') as $identData) {
                    $employee->employeeIdentification()->create([
                        'id_type' => $identData['id_type'],
                        'document_number' => $identData['document_number'],
                        'issue_date' => $identData['issue_date'] ?? null,
                        'expiry_date' => $identData['expiry_date'] ?? null,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                    ]);
                }
            }

            // Create employee beneficiaries if provided
            if ($request->has('beneficiaries')) {
                foreach ($request->input('beneficiaries') as $beneData) {
                    $employee->employeeBeneficiaries()->create([
                        'beneficiary_name' => $beneData['beneficiary_name'],
                        'beneficiary_relationship' => $beneData['beneficiary_relationship'],
                        'phone_number' => $beneData['phone_number'] ?? null,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee->load(['employeeIdentification', 'employeeBeneficiaries'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an employee
     *
     * @OA\Put(
     *     path="/employees/{id}",
     *     summary="Update employee details",
     *     description="Updates an existing employee record with the provided information",
     *     operationId="updateEmployee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Employee information to update",
     *         @OA\JsonContent(
     *             required={"staff_id","first_name_en","last_name_en","gender","date_of_birth","status"},
     *             @OA\Property(property="staff_id", type="string", example="EMP001", description="Unique staff identifier"),
     *             @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}, example="SMRU", description="Employee subsidiary"),
     *             @OA\Property(property="user_id", type="integer", nullable=true, description="Associated user ID"),
     *             @OA\Property(property="department_position_id", type="integer", description="Department position ID"),
     *             @OA\Property(property="initial_en", type="string", example="Mr.", description="English initial/title"),
     *             @OA\Property(property="initial_th", type="string", example="นาย", description="Thai initial/title"),
     *             @OA\Property(property="first_name_en", type="string", example="John", description="Employee first name in English"),
     *             @OA\Property(property="last_name_en", type="string", example="Doe", description="Employee last name in English"),
     *             @OA\Property(property="first_name_th", type="string", example="จอห์น", description="Employee first name in Thai"),
     *             @OA\Property(property="last_name_th", type="string", example="โด", description="Employee last name in Thai"),
     *             @OA\Property(property="gender", type="string", example="male", description="Employee gender"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Employee date of birth"),
     *             @OA\Property(property="date_of_birth_th", type="string", example="01/01/2533", description="Employee date of birth in Thai format"),
     *             @OA\Property(property="age", type="integer", example=33, description="Employee age"),
     *             @OA\Property(property="status", type="string", enum={"Expats", "Local ID", "Local non ID"}, example="Expats", description="Employee status"),
     *             @OA\Property(property="nationality", type="string", example="Thai", description="Employee nationality"),
     *             @OA\Property(property="religion", type="string", example="Buddhism", description="Employee religion"),
     *             @OA\Property(property="social_security_number", type="string", example="SSN123456", description="Employee social security number"),
     *             @OA\Property(property="tax_number", type="string", example="TAX123456", description="Employee tax number"),
     *             @OA\Property(property="bank_name", type="string", example="Bangkok Bank", description="Employee bank name"),
     *             @OA\Property(property="bank_branch", type="string", example="Silom", description="Employee bank branch"),
     *             @OA\Property(property="bank_account_name", type="string", example="John Doe", description="Employee bank account name"),
     *             @OA\Property(property="bank_account_number", type="string", example="1234567890", description="Employee bank account number"),
     *             @OA\Property(property="mobile_phone", type="string", example="0812345678", description="Employee mobile phone"),
     *             @OA\Property(property="permanent_address", type="string", example="123 Main St, Bangkok", description="Employee permanent address"),
     *             @OA\Property(property="current_address", type="string", example="456 Second St, Bangkok", description="Employee current address"),
     *             @OA\Property(property="military_status", type="boolean", example=false, description="Employee military status"),
     *             @OA\Property(property="marital_status", type="string", example="Single", description="Employee marital status"),
     *             @OA\Property(property="spouse_name", type="string", example="Jane Doe", description="Employee spouse name"),
     *             @OA\Property(property="spouse_phone_number", type="string", example="0812345679", description="Employee spouse phone number"),
     *             @OA\Property(property="emergency_contact_person_name", type="string", example="James Doe", description="Emergency contact name"),
     *             @OA\Property(property="emergency_contact_person_relationship", type="string", example="Father", description="Emergency contact relationship"),
     *             @OA\Property(property="emergency_contact_person_phone", type="string", example="0812345680", description="Emergency contact phone"),
     *             @OA\Property(property="father_name", type="string", example="James Doe", description="Employee father name"),
     *             @OA\Property(property="father_occupation", type="string", example="Engineer", description="Employee father occupation"),
     *             @OA\Property(property="father_phone_number", type="string", example="0812345681", description="Employee father phone number"),
     *             @OA\Property(property="mother_name", type="string", example="Mary Doe", description="Employee mother name"),
     *             @OA\Property(property="mother_occupation", type="string", example="Teacher", description="Employee mother occupation"),
     *             @OA\Property(property="mother_phone_number", type="string", example="0812345682", description="Employee mother phone number"),
     *             @OA\Property(property="driver_license_number", type="string", example="DL123456", description="Employee driver license number"),
     *             @OA\Property(property="remark", type="string", example="Additional notes", description="Additional remarks")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Employee")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $validated = $request->validate([
            'staff_id' => "required|string|max:50|unique:employees,staff_id,{$id}",
            'subsidiary' => 'nullable|string|in:SMRU,BHF',
            'user_id' => 'nullable|integer|exists:users,id',
            'department_position_id' => 'nullable|integer|exists:department_positions,id',
            'initial_en' => 'nullable|string|max:10',
            'initial_th' => 'nullable|string|max:10',
            'first_name_en' => 'required|string|max:255',
            'last_name_en' => 'required|string|max:255',
            'first_name_th' => 'nullable|string|max:255',
            'last_name_th' => 'nullable|string|max:255',
            'gender' => 'required|string|max:10',
            'date_of_birth' => 'required|date',
            'date_of_birth_th' => 'nullable|string',
            'age' => 'nullable|integer',
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
            'updated_by' => auth()->user()->name ?? 'system'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $employee
        ]);
    }

    /**
     * Delete an employee
     *
     * @OA\Delete(
     *     path="/employees/{id}",
     *     summary="Delete an employee",
     *     description="Deletes an employee by ID",
     *     operationId="deleteEmployee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee not found")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully'
        ]);
    }

    /**
     * Get Site records
     *
     * @OA\Get(
     *     path="/employees/site-records",
     *     summary="Get Site records",
     *     description="Returns a list of all work locations/sites",
     *     operationId="getSiteRecords",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Site records retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Main Office"),
     *                     @OA\Property(property="address", type="string", example="123 Main Street"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getSiteRecords()
    {
        $sites = WorkLocation::all();

        return response()->json([
            'success' => true,
            'message' => 'Site records retrieved successfully',
            'data' => $sites
        ]);
    }

    /**
     * Filter employees by given criteria
     *
     * @OA\Get(
     *     path="/employees/filter",
     *     summary="Filter employees by criteria",
     *     description="Returns employees filtered by the provided criteria",
     *     operationId="filterEmployees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="staff_id",
     *         in="query",
     *         description="Staff ID to filter by",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Employee status to filter by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Expats", "Local ID", "Local non ID"})
     *     ),
     *     @OA\Parameter(
     *         name="subsidiary",
     *         in="query",
     *         description="Employee subsidiary to filter by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"SMRU", "BHF"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Filtered employees retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employees retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Employee")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No employees found matching the criteria",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No employees found.")
     *         )
     *     )
     * )
     */
    public function filterEmployees(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'nullable|string|max:50',
            'status' => 'nullable|in:Expats,Local ID,Local non ID',
            'subsidiary' => 'nullable|in:SMRU,BHF',
        ]);

        $query = Employee::query();

        if (!empty($validated['staff_id'])) {
            $query->where('staff_id', 'like', '%' . $validated['staff_id'] . '%');
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['subsidiary'])) {
            $query->where('subsidiary', $validated['subsidiary']);
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
        ]);
    }

    /**
     * Upload profile picture for an employee
     *
     * @OA\Post(
     *     path="/employees/{id}/profile-picture",
     *     summary="Upload profile picture",
     *     description="Upload a profile picture for an employee",
     *     operationId="uploadProfilePictureEmployee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Profile picture file",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profile_picture"},
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary",
     *                     description="Profile picture file (jpeg, png, jpg, gif, svg, max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile picture uploaded successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile picture uploaded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="profile_picture", type="string", example="employee/profile_pictures/image.jpg"),
     *                 @OA\Property(property="url", type="string", example="http://example.com/storage/employee/profile_pictures/image.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function uploadProfilePicture(Request $request, $id)
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

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
                'url' => Storage::disk('public')->url($path)
            ]
        ]);
    }

    /**
     * Validate uploaded file
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240' // Added max file size
        ]);
    }

    /**
     * Convert string value to float
     *
     * @param mixed $value Value to convert
     * @return float|null Converted float value or null if input is null
     */
    private function toFloat($value): ?float
    {
        if (is_null($value)) return null;
        return floatval(preg_replace('/[^0-9.-]/', '', $value));
    }


    // employee grant-item add
    public function addEmployeeGrantItem(Request $request)
    {
        $request->validate([
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

        $employee = Employee::find($request->employee_id);
        $grantItem = GrantItem::find($request->grant_item_id);

        $employee->grant_items()->attach($grantItem, [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'payment_method' => $request->payment_method,
            'payment_account' => $request->payment_account,
            'payment_account_name' => $request->payment_account_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee grant-item added successfully',
            'data' => $employee
        ]);
    }

}
