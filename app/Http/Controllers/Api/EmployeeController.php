<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use Illuminate\Validation\Rule;
use App\Models\WorkLocation;


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
     *     description="Returns a list of all employees",
     *     operationId="getEmployees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Employee")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $employees = Employee::all();
        return response()->json($employees);
    }

    /**
     * Get a single employee
     *
     * @OA\Get(
     *     path="/employees/{id}",
     *     summary="Get a single employee",
     *     description="Returns a single employee by ID",
     *     operationId="getEmployeeById",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID of the employee", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/Employee"
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $employee = Employee::find($id);
        return response()->json($employee);
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
     *         @OA\JsonContent(ref="#/components/schemas/Employee")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Employee")
     *     )
     * )
     */
    /**
     * @OA\Post(
     *     path="/employees",
     *     summary="Create a new employee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"staff_id","first_name","last_name","gender","date_of_birth"},
     *             @OA\Property(property="staff_id", type="string", example="EMP001"),
     *             @OA\Property(property="subsidiary", type="string", example="SMRU"),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="middle_name", type="string", example="William"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="gender", type="string", example="male"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="mobile_phone", type="string", example="+1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|string|max:50|unique:employees',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|max:10',
            'date_of_birth' => 'required|date',
            'status' => ['required', Rule::in(['Expats', 'Local ID', 'Local non ID'])],
            'religion' => 'nullable|string|max:100',
            'birth_place' => 'nullable|string|max:100',
            'identification_number' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'office_phone' => 'nullable|string|max:20',
            'mobile_phone' => 'nullable|string|max:20',
            'height' => 'nullable|numeric|between:0,999.99',
            'weight' => 'nullable|numeric|between:0,999.99',
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
        ]);

        $employee = Employee::create($validated);

        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => $employee
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/employees/{id}",
     *     summary="Update employee details",
     *     description="Updates an existing employee record with the provided information",
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
     *             @OA\Property(property="staff_id", type="string", maxLength=50, example="EMP001"),
     *             @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
     *             @OA\Property(property="middle_name", type="string", maxLength=255, nullable=true, example="William"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, example="Doe"),
     *             @OA\Property(property="gender", type="string", maxLength=10, example="male"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *             @OA\Property(property="religion", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="birth_place", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="identification_number", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="passport_number", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="bank_name", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="bank_branch", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="bank_account_name", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="bank_account_number", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="office_phone", type="string", maxLength=20, nullable=true),
     *             @OA\Property(property="mobile_phone", type="string", maxLength=20, nullable=true),
     *             @OA\Property(property="height", type="number", format="float", nullable=true, example=175.5),
     *             @OA\Property(property="weight", type="number", format="float", nullable=true, example=70.5),
     *             @OA\Property(property="permanent_address", type="string", nullable=true),
     *             @OA\Property(property="current_address", type="string", nullable=true),
     *             @OA\Property(property="stay_with", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="military_status", type="boolean", example=false),
     *             @OA\Property(property="marital_status", type="string", maxLength=20, nullable=true),
     *             @OA\Property(property="spouse_name", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="spouse_occupation", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="father_name", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="father_occupation", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="mother_name", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="mother_occupation", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="driver_license_number", type="string", maxLength=50, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="staff_id", type="string", example="EMP001"),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $validated = $request->validate([
            'staff_id' => "required|string|max:50|unique:employees,staff_id,{$id}",
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|max:10',
            'date_of_birth' => 'required|date',
            'status' => ['required', Rule::in(['Expats', 'Local ID', 'Local non ID'])],
            'religion' => 'nullable|string|max:100',
            'birth_place' => 'nullable|string|max:100',
            'identification_number' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'office_phone' => 'nullable|string|max:20',
            'mobile_phone' => 'nullable|string|max:20',
            'height' => 'nullable|numeric|between:0,999.99',
            'weight' => 'nullable|numeric|between:0,999.99',
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
        ]);

        $employee->update($validated);
        return response()->json($employee);
    }

    /**
     * @OA\Delete(
     *     path="/employees/{id}",
     *     summary="Delete an employee",
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
     *         description="Employee deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully']);
    }


    /**
     * Get Site records
     *
     * @OA\Get(
     *     path="/employees/site-records",
     *     summary="Get Site records",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function getSiteRecords()
    {
        $sites = WorkLocation::all();
        return response()->json($sites);
    }

}
