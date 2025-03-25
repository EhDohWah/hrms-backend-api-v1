<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use Illuminate\Validation\Rule;
use App\Models\WorkLocation;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Employees",
 *     description="API Endpoints for Employee management"
 * )
 */
class EmployeeController extends Controller
{
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
     *     description="Creates a new employee",
     *     operationId="createEmployee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Employee data",
     *         @OA\JsonContent(
     *             required={"staff_id","first_name","last_name","gender","date_of_birth","status"},
     *             @OA\Property(property="staff_id", type="string", example="EMP001", description="Unique staff identifier"),
     *             @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}, example="SMRU", description="Employee subsidiary"),
     *             @OA\Property(property="first_name", type="string", example="John", description="Employee first name"),
     *             @OA\Property(property="middle_name", type="string", example="William", description="Employee middle name"),
     *             @OA\Property(property="last_name", type="string", example="Doe", description="Employee last name"),
     *             @OA\Property(property="gender", type="string", example="male", description="Employee gender"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Employee date of birth"),
     *             @OA\Property(property="status", type="string", enum={"Expats", "Local ID", "Local non ID"}, example="Expats", description="Employee status"),
     *             @OA\Property(property="religion", type="string", example="Buddhism", description="Employee religion"),
     *             @OA\Property(property="birth_place", type="string", example="Bangkok", description="Employee birth place"),
     *             @OA\Property(property="identification_number", type="string", example="1234567890123", description="Employee ID number"),
     *             @OA\Property(property="social_security_number", type="string", example="SSN123456", description="Employee social security number"),
     *             @OA\Property(property="tax_number", type="string", example="TAX123456", description="Employee tax number"),
     *             @OA\Property(property="passport_number", type="string", example="P123456", description="Employee passport number"),
     *             @OA\Property(property="bank_name", type="string", example="Bangkok Bank", description="Employee bank name"),
     *             @OA\Property(property="bank_branch", type="string", example="Silom", description="Employee bank branch"),
     *             @OA\Property(property="bank_account_name", type="string", example="John Doe", description="Employee bank account name"),
     *             @OA\Property(property="bank_account_number", type="string", example="1234567890", description="Employee bank account number"),
     *             @OA\Property(property="office_phone", type="string", example="021234567", description="Employee office phone"),
     *             @OA\Property(property="mobile_phone", type="string", example="+66812345678", description="Employee mobile phone"),
     *             @OA\Property(property="permanent_address", type="string", example="123 Main St, Bangkok", description="Employee permanent address"),
     *             @OA\Property(property="current_address", type="string", example="456 Second St, Bangkok", description="Employee current address"),
     *             @OA\Property(property="stay_with", type="string", example="Family", description="Employee stays with"),
     *             @OA\Property(property="military_status", type="boolean", example=false, description="Employee military status"),
     *             @OA\Property(property="marital_status", type="string", example="Single", description="Employee marital status"),
     *             @OA\Property(property="spouse_name", type="string", example="Jane Doe", description="Employee spouse name"),
     *             @OA\Property(property="spouse_occupation", type="string", example="Doctor", description="Employee spouse occupation"),
     *             @OA\Property(property="father_name", type="string", example="James Doe", description="Employee father name"),
     *             @OA\Property(property="father_occupation", type="string", example="Engineer", description="Employee father occupation"),
     *             @OA\Property(property="mother_name", type="string", example="Mary Doe", description="Employee mother name"),
     *             @OA\Property(property="mother_occupation", type="string", example="Teacher", description="Employee mother occupation"),
     *             @OA\Property(property="driver_license_number", type="string", example="DL123456", description="Employee driver license number"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com", description="Employee email address")
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
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|string|max:50|unique:employees',
            'subsidiary' => ['nullable', Rule::in(['SMRU', 'BHF'])],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|max:10',
            'date_of_birth' => 'required|date',
            'status' => ['required', Rule::in(['Expats', 'Local ID', 'Local non ID'])],
            'religion' => 'nullable|string|max:100',
            'birth_place' => 'nullable|string|max:100',
            'identification_number' => 'nullable|string|max:50',
            'social_security_number' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'office_phone' => 'nullable|string|max:20',
            'mobile_phone' => 'nullable|string|max:20',
            'permanent_address' => 'nullable|string',
            'current_address' => 'nullable|string',
            'stay_with' => 'nullable|string|max:100',
            'military_status' => 'boolean',
            'marital_status' => 'nullable|string|max:20',
            'spouse_name' => 'nullable|string|max:100',
            'spouse_occupation' => 'nullable|string|max:100',
            'father_name' => 'nullable|string|max:100',
            'father_occupation' => 'nullable|string|max:100',
            'mother_name' => 'nullable|string|max:100',
            'mother_occupation' => 'nullable|string|max:100',
            'driver_license_number' => 'nullable|string|max:50',
            'created_by' => 'nullable|string|max:255',
            'updated_by' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $employee = Employee::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => $employee
        ], 201);
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
     *             required={"staff_id","first_name","last_name","gender","date_of_birth","status"},
     *             @OA\Property(property="staff_id", type="string", maxLength=50, example="EMP001", description="Unique staff identifier"),
     *             @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}, example="SMRU", description="Employee subsidiary"),
     *             @OA\Property(property="first_name", type="string", maxLength=255, example="John", description="Employee first name"),
     *             @OA\Property(property="middle_name", type="string", maxLength=255, nullable=true, example="William", description="Employee middle name"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, example="Doe", description="Employee last name"),
     *             @OA\Property(property="gender", type="string", maxLength=10, example="male", description="Employee gender"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Employee date of birth"),
     *             @OA\Property(property="status", type="string", enum={"Expats", "Local ID", "Local non ID"}, example="Expats", description="Employee status"),
     *             @OA\Property(property="religion", type="string", maxLength=100, nullable=true, description="Employee religion"),
     *             @OA\Property(property="birth_place", type="string", maxLength=100, nullable=true, description="Employee birth place"),
     *             @OA\Property(property="identification_number", type="string", maxLength=50, nullable=true, description="Employee ID number"),
     *             @OA\Property(property="social_security_number", type="string", maxLength=50, nullable=true, description="Employee social security number"),
     *             @OA\Property(property="tax_number", type="string", maxLength=50, nullable=true, description="Employee tax number"),
     *             @OA\Property(property="passport_number", type="string", maxLength=50, nullable=true, description="Employee passport number"),
     *             @OA\Property(property="bank_name", type="string", maxLength=100, nullable=true, description="Employee bank name"),
     *             @OA\Property(property="bank_branch", type="string", maxLength=100, nullable=true, description="Employee bank branch"),
     *             @OA\Property(property="bank_account_name", type="string", maxLength=100, nullable=true, description="Employee bank account name"),
     *             @OA\Property(property="bank_account_number", type="string", maxLength=100, nullable=true, description="Employee bank account number"),
     *             @OA\Property(property="office_phone", type="string", maxLength=20, nullable=true, description="Employee office phone"),
     *             @OA\Property(property="mobile_phone", type="string", maxLength=20, nullable=true, description="Employee mobile phone"),
     *             @OA\Property(property="permanent_address", type="string", nullable=true, description="Employee permanent address"),
     *             @OA\Property(property="current_address", type="string", nullable=true, description="Employee current address"),
     *             @OA\Property(property="stay_with", type="string", maxLength=100, nullable=true, description="Employee stays with"),
     *             @OA\Property(property="military_status", type="boolean", example=false, description="Employee military status"),
     *             @OA\Property(property="marital_status", type="string", maxLength=20, nullable=true, description="Employee marital status"),
     *             @OA\Property(property="spouse_name", type="string", maxLength=100, nullable=true, description="Employee spouse name"),
     *             @OA\Property(property="spouse_occupation", type="string", maxLength=100, nullable=true, description="Employee spouse occupation"),
     *             @OA\Property(property="father_name", type="string", maxLength=100, nullable=true, description="Employee father name"),
     *             @OA\Property(property="father_occupation", type="string", maxLength=100, nullable=true, description="Employee father occupation"),
     *             @OA\Property(property="mother_name", type="string", maxLength=100, nullable=true, description="Employee mother name"),
     *             @OA\Property(property="mother_occupation", type="string", maxLength=100, nullable=true, description="Employee mother occupation"),
     *             @OA\Property(property="driver_license_number", type="string", maxLength=50, nullable=true, description="Employee driver license number"),
     *             @OA\Property(property="email", type="string", format="email", nullable=true, description="Employee email address")
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
            'subsidiary' => ['nullable', Rule::in(['SMRU', 'BHF'])],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|max:10',
            'date_of_birth' => 'required|date',
            'status' => ['required', Rule::in(['Expats', 'Local ID', 'Local non ID'])],
            'religion' => 'nullable|string|max:100',
            'birth_place' => 'nullable|string|max:100',
            'identification_number' => 'nullable|string|max:50',
            'social_security_number' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'office_phone' => 'nullable|string|max:20',
            'mobile_phone' => 'nullable|string|max:20',
            'permanent_address' => 'nullable|string',
            'current_address' => 'nullable|string',
            'stay_with' => 'nullable|string|max:100',
            'military_status' => 'boolean',
            'marital_status' => 'nullable|string|max:20',
            'spouse_name' => 'nullable|string|max:100',
            'spouse_occupation' => 'nullable|string|max:100',
            'father_name' => 'nullable|string|max:100',
            'father_occupation' => 'nullable|string|max:100',
            'mother_name' => 'nullable|string|max:100',
            'mother_occupation' => 'nullable|string|max:100',
            'driver_license_number' => 'nullable|string|max:50',
            'updated_by' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $employee->update($validated);

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

    /**
     * Upload employee data from Excel file
     *
     * @OA\Post(
     *     path="/employees/upload",
     *     summary="Upload employee data from Excel file",
     *     description="Upload employee data from Excel file",
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
     *                     description="Excel file containing employee data"
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
     *             @OA\Property(property="message", type="string", example="Employee data uploaded successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
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
    public function uploadEmployeeData(Request $request)
    {
        $this->validateFile($request);

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $data = Excel::toArray(new EmployeeImport, $filePath);

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No data found in the uploaded file'
            ], 422);
        }

        $employeeData = $data[0];
        $errors = [];

        foreach ($employeeData as $row) {
            $staffId = $row[0];
            $subsidiary = $row[1];
            $firstName = $row[2];
            $middleName = $row[3];
            $lastName = $row[4];
            $gender = $row[5];
            $dateOfBirth = $row[6];
            $status = $row[7];
            $religion = $row[8];
            $birthPlace = $row[9];
            $identificationNumber = $row[10];
            $socialSecurityNumber = $row[11];
            $taxNumber = $row[12];
            $passportNumber = $row[13];
            $bankName = $row[14];
            $bankBranch = $row[15];
            $bankAccountNumber = $row[16];
            $officePhone = $row[17];
            $mobilePhone = $row[18];
            $permanentAddress = $row[19];
            $currentAddress = $row[20];
            $stayWith = $row[21];
            $militaryStatus = $row[22];
            $maritalStatus = $row[23];
            $spouseName = $row[24];
            $spouseOccupation = $row[25];
            $fatherName = $row[26];
            $fatherOccupation = $row[27];
            $motherName = $row[28];
            $motherOccupation = $row[29];
            $driverLicenseNumber = $row[30];
            $email = $row[31];

            // Validate required fields
            if (empty($staffId) || empty($subsidiary) || empty($firstName) || empty($lastName) || empty($gender) || empty($dateOfBirth) || empty($status)) {
                $errors[] = "Missing required fields for employee: $staffId";
                continue;
            }

            // Check if employee already exists
            $existingEmployee = Employee::where('staff_id', $staffId)->first();

            if ($existingEmployee) {
                $errors[] = "Employee already exists: $staffId";
                continue;
            }

            // Create new employee
            $employee = new Employee();
            $employee->staff_id = $staffId;
            $employee->subsidiary = $subsidiary;
            $employee->first_name = $firstName;
            $employee->middle_name = $middleName;
            $employee->last_name = $lastName;
            $employee->gender = $gender;
            $employee->date_of_birth = $dateOfBirth;
            $employee->status = $status;
            $employee->religion = $religion;
            $employee->birth_place = $birthPlace;
            $employee->identification_number = $identificationNumber;
            $employee->social_security_number = $socialSecurityNumber;
            $employee->tax_number = $taxNumber;
            $employee->passport_number = $passportNumber;
            $employee->bank_name = $bankName;
            $employee->bank_branch = $bankBranch;
            $employee->bank_account_number = $bankAccountNumber;
            $employee->office_phone = $officePhone;
            $employee->mobile_phone = $mobilePhone;
            $employee->permanent_address = $permanentAddress;
            $employee->current_address = $currentAddress;
            $employee->stay_with = $stayWith;
            $employee->military_status = $militaryStatus;
            $employee->marital_status = $maritalStatus;
            $employee->spouse_name = $spouseName;
            $employee->spouse_occupation = $spouseOccupation;
            $employee->father_name = $fatherName;
            $employee->father_occupation = $fatherOccupation;
            $employee->mother_name = $motherName;
            $employee->mother_occupation = $motherOccupation;
            $employee->driver_license_number = $driverLicenseNumber;
            $employee->email = $email;

            $employee->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee data uploaded successfully',
            'data' => $employeeData
        ]);
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
