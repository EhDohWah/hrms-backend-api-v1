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
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EmployeesImport;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\UploadEmployeeImportRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Requests\FilterEmployeeRequest;
use App\Http\Resources\EmployeeCollection;
use App\Http\Requests\ShowEmployeeRequest;
use App\Http\Resources\EmployeeDetailResource;
use App\Models\Employment;
use App\Models\EmployeeGrantAllocation;
use App\Models\EmployeeBeneficiary;
use App\Models\EmployeeIdentification;
use App\Imports\DevEmployeesImport;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;

/**
 * @OA\Tag(
 *     name="Employees",
 *     description="API Endpoints for Employee management"
 * )
 */
class EmployeeController extends Controller
{

    /**
     * Get all employees for tree search
     *
     * @OA\Get(
     *     path="/employees/tree-search",
     *     summary="Get all employees for tree search",
     *     description="Returns a list of all employees organized by subsidiary for tree-based search",
     *     operationId="getEmployeesForTreeSearch",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employees retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="key", type="string", example="subsidiary-SMRU"),
     *                 @OA\Property(property="title", type="string", example="SMRU"),
     *                 @OA\Property(property="value", type="string", example="subsidiary-SMRU"),
     *                 @OA\Property(property="children", type="array", @OA\Items(
     *                     @OA\Property(property="key", type="string", example="employee-1"),
     *                     @OA\Property(property="title", type="string", example="EMP001 - John Doe"),
     *                     @OA\Property(property="value", type="string", example="employee-1")
     *                 ))
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployeesForTreeSearch()
    {
        try {
            $employees = Employee::select('id', 'subsidiary', 'staff_id', 'first_name_en', 'last_name_en', 'status')->get();

            // Group employees by subsidiary
            $grouped = $employees->groupBy('subsidiary');

            // Map each subsidiary into a parent node with its employees as children
            $treeData = $grouped->map(function($subsidiaryEmployees, $subsidiary) {
                return [
                    'key' => "subsidiary-{$subsidiary}",
                    'title' => $subsidiary,
                    'value' => "subsidiary-{$subsidiary}",
                    'children' => $subsidiaryEmployees->map(function($emp) {
                        $fullName = $emp->first_name_en;
                        if ($emp->last_name_en && $emp->last_name_en !== '-') {
                            $fullName .= ' ' . $emp->last_name_en;
                        }

                        return [
                            'key' => "{$emp->id}",
                            'title' => "{$emp->staff_id} - {$fullName}",
                            'status' => $emp->status,
                            'value' => "{$emp->id}"
                        ];
                    })->values()->toArray()
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Employees retrieved successfully',
                'data' => $treeData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/employees/delete-selected/{ids}",
     *     summary="Delete multiple employees by their IDs",
     *     description="Deletes multiple employees and their related records based on the provided IDs",
     *     operationId="deleteSelectedEmployees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Employee IDs to delete",
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 description="Array of employee IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employees deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="5 employee(s) deleted successfully"),
     *             @OA\Property(property="count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete employees"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     *
     * Delete multiple employees by their IDs
     *
     * @param FilterEmployeeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
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
            EmployeeGrantAllocation::whereIn('employee_id', $ids)->delete();
            EmployeeBeneficiary::whereIn('employee_id', $ids)->delete();
            EmployeeIdentification::whereIn('employee_id', $ids)->delete();
            Employment::whereIn('employee_id', $ids)->delete();

            // Delete the employees
            $count = Employee::whereIn('id', $ids)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $count . ' employee(s) deleted successfully',
                'count' => $count
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete employees: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
     *                 @OA\Property(property="processed_employees", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or no rows imported",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No rows were imported – check your column headings & data."),
     *             @OA\Property(property="debug", type="object",
     *                 @OA\Property(property="first_row_snapshot", type="object", example={"staff_id": "A001", "first_name": "John"}),
     *                 @OA\Property(property="custom_errors", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="validation_failures", type="array", @OA\Items(type="string"))
     *             )
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
    public function uploadEmployeeData(UploadEmployeeImportRequest $request)
    {
        $file = $request->file('file');

            // Generate a unique import ID (for tracking/debugging)
        $importId = uniqid('import_', true);
        // or $importId = Str::uuid(); // (needs: use Illuminate\Support\Str;)

        $import = new EmployeesImport($importId);

        Excel::queueImport($import, $file); // for production

        // if (app()->environment('production')) {
        //     Excel::queueImport(new EmployeesImport, $file);

        //     return response()->json([
        //         'success' => true,
        //         'message' => "Import queued; you'll get notified when it's done.",
        //     ], 202);
        // }

        // ── Dev branch ────────────────────────────────────────────────────────
        // $import = new DevEmployeesImport;
        // Excel::import($import, $file);


        // Now $import is defined, so you can safely gather debug info:
        $processed = $import->getProcessedEmployees();
        $errors    = $import->getErrors();
        $fails     = $import->getValidationFailures();
        $snapshot  = $import->getFirstRowSnapshot();

        return response()->json([
            'success' => true,
            'message' => "Your file is being imported. You'll be notified when it's done.",
            'import_id' => $importId
        ], 202);

    }

    /**
     * Get all employees
     *
     * @OA\Get(
     *     path="/employees",
     *     summary="Get all employees",
     *     description="Returns a list of all employees with related data and statistics",
     *     operationId="getEmployees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=true,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="staff_id",
     *         in="query",
     *         required=false,
     *         description="Filter by staff ID",
     *         @OA\Schema(type="string", maxLength=50)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by employee status",
     *         @OA\Schema(type="string", enum={"Expats", "Local ID", "Local non ID"})
     *     ),
     *     @OA\Parameter(
     *         name="subsidiary",
     *         in="query",
     *         required=false,
     *         description="Filter by subsidiary",
     *         @OA\Schema(type="string", enum={"SMRU", "BHF"})
     *     ),
     *     @OA\Parameter(
     *         name="gender",
     *         in="query",
     *         required=false,
     *         description="Filter by gender",
     *         @OA\Schema(type="string", enum={"Male", "Female"})
     *     ),
     *     @OA\Parameter(
     *         name="date_of_birth",
     *         in="query",
     *         required=false,
     *         description="Filter by date of birth",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="age",
     *         in="query",
     *         required=false,
     *         description="Filter by age",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         description="Field to sort by",
     *         @OA\Schema(type="string", enum={"subsidiary", "staff_id", "initials", "first_name_en", "last_name_en", "gender", "date_of_birth", "age", "status", "id_type", "id_number", "social_security_number", "tax_number", "mobile_phone"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         required=false,
     *         description="Sort direction",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
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
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="subsidiary", type="string", example="SMRU"),
     *                     @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                     @OA\Property(property="initial_en", type="string", example="Mr."),
     *                     @OA\Property(property="first_name_en", type="string", example="John"),
     *                     @OA\Property(property="last_name_en", type="string", example="Doe"),
     *                     @OA\Property(property="gender", type="string", example="Male"),
     *                     @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                     @OA\Property(property="status", type="string", example="Local ID"),
     *                     @OA\Property(property="id_type", type="string", example="Passport"),
     *                     @OA\Property(property="id_number", type="string", example="AB123456"),
     *                     @OA\Property(property="social_security_number", type="string", example="1234567890"),
     *                     @OA\Property(property="tax_number", type="string", example="TAX123456"),
     *                     @OA\Property(property="mobile_phone", type="string", example="+66812345678"),
     *                     @OA\Property(
     *                         property="identification",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id_type", type="string", example="Passport"),
     *                             @OA\Property(property="document_number", type="string", example="AB123456"),
     *                             @OA\Property(property="issue_date", type="string", format="date", example="2018-01-01"),
     *                             @OA\Property(property="expiry_date", type="string", format="date", example="2028-01-01")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="statistics",
     *                 type="object",
     *                 @OA\Property(property="totalEmployees", type="integer", example=450),
     *                 @OA\Property(property="activeCount", type="integer", example=400),
     *                 @OA\Property(property="inactiveCount", type="integer", example=50),
     *                 @OA\Property(property="newJoinerCount", type="integer", example=15),
     *                 @OA\Property(
     *                     property="subsidiaryCount",
     *                     type="object",
     *                     @OA\Property(property="SMRU_count", type="integer", example=300),
     *                     @OA\Property(property="BHF_count", type="integer", example=150)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 example={"first": "http://example.com/api/employees?page=1", "last": "http://example.com/api/employees?page=5", "prev": null, "next": "http://example.com/api/employees?page=2"}
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 example={"current_page": 1, "from": 1, "last_page": 5, "path": "http://example.com/api/employees", "per_page": 100, "to": 100, "total": 450}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="per_page",
     *                     type="array",
     *                     @OA\Items(type="string", example="The per page must be at least 100.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(FilterEmployeeRequest $request)
    {
        $validated = $request->validated();

        // 1) Start the query
        $q = Employee::select([
            'id',
            'subsidiary',
            'staff_id',
            'initial_en',
            'first_name_en',
            'last_name_en',
            'gender',
            'date_of_birth',
            'status',
            'social_security_number',
            'tax_number',
            'mobile_phone'
        ])->with([
            'employeeIdentification:id,employee_id,id_type,document_number,issue_date,expiry_date',
            'employment:id,employee_id,active,start_date'
        ])->orderBy('created_at', 'desc');

        // 2) Apply filters if present
        if (!empty($validated['staff_id'])) {
            $q->where('staff_id', 'like', '%'.$validated['staff_id'].'%');
        }
        if (!empty($validated['status'])) {
            $q->where('status', $validated['status']);
        }
        if (!empty($validated['subsidiary'])) {
            $q->where('subsidiary', $validated['subsidiary']);
        }

        if (!empty($validated['gender'])) {
            $q->where('gender', $validated['gender']);
        }

        if (!empty($validated['date_of_birth'])) {
            $q->where('date_of_birth', $validated['date_of_birth']);
        }

        if (!empty($validated['age'])) {
            $q->where('age', $validated['age']);
        }

        if (!empty($validated['status'])) {
            $q->where('status', $validated['status']);
        }

        // 3) Apply sorting if present
        if (!empty($validated['sort_by'])) {
            $dir = $validated['sort_order'] ?? 'asc';
            $q->orderBy($validated['sort_by'], $dir);
        }

        if (!empty($validated['id_type'])) {
            $q->whereHas('employeeIdentification', function($query) use ($validated) {
                $query->where('id_type', $validated['id_type']);
            });
        }

        // 4) Paginate
        $employees = $q->paginate($validated['per_page']);

        // 5) Calculate statistics
        $allEmployees = Employee::with('employment:id,employee_id,active,start_date')->get();

        $now = now();
        $threeMonthsAgo = now()->subMonths(3);

        $statistics = [
            'totalEmployees' => $allEmployees->count(),
            'activeCount' => $allEmployees->filter(function($employee) {
                return $employee->employment && $employee->employment->active === 1;
            })->count(),
            'inactiveCount' => $allEmployees->filter(function($employee) {
                return $employee->employment && $employee->employment->active === 0;
            })->count(),
            'newJoinerCount' => $allEmployees->filter(function($employee) use ($threeMonthsAgo, $now) {
                if (!$employee->employment || !$employee->employment->start_date) return false;
                $startDate = new \DateTime($employee->employment->start_date);
                return $startDate >= $threeMonthsAgo && $startDate <= $now;
            })->count(),
            'subsidiaryCount' => [
                'SMRU_count' => $allEmployees->where('subsidiary', 'SMRU')->count(),
                'BHF_count' => $allEmployees->where('subsidiary', 'BHF')->count()
            ]
        ];

        // 6) Return wrapped in your Resource/Collection with statistics
        return (new EmployeeCollection($employees))
                ->additional([
                    'success' => true,
                    'message' => 'Employees retrieved successfully',
                    'statistics' => $statistics
                ]);
    }

    /**
     * Get a single employee
     *
     * @OA\Get(
     *     path="/employees/staff-id/{staff_id}",
     *     summary="Get a single employee",
     *     description="Returns employee(s) by staff ID with related data",
     *     operationId="getEmployeeByStaffId",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="staff_id", in="path", required=true, description="Staff ID of the employee", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee(s) retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="subsidiary", type="string", example="SMRU"),
     *                     @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                     @OA\Property(property="initial_en", type="string", example="Mr."),
     *                     @OA\Property(property="first_name_en", type="string", example="John"),
     *                     @OA\Property(property="last_name_en", type="string", example="Doe"),
     *                     @OA\Property(property="gender", type="string", example="male"),
     *                     @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                     @OA\Property(property="age", type="integer", example=33),
     *                     @OA\Property(property="status", type="string", example="Expats"),
     *                     @OA\Property(property="social_security_number", type="string", example="SSN123456"),
     *                     @OA\Property(property="tax_number", type="string", example="TAX123456"),
     *                     @OA\Property(property="mobile_phone", type="string", example="0812345678"),
     *                     @OA\Property(
     *                         property="employee_identification",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="employee_id", type="integer", example=1),
     *                             @OA\Property(property="id_type", type="string", example="Passport"),
     *                             @OA\Property(property="document_number", type="string", example="P12345678"),
     *                             @OA\Property(property="issue_date", type="string", format="date", example="2020-01-01"),
     *                             @OA\Property(property="expiry_date", type="string", format="date", example="2030-01-01")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="employment",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="employee_id", type="integer", example=1),
     *                         @OA\Property(property="active", type="integer", example=1),
     *                         @OA\Property(property="start_date", type="string", format="date", example="2023-01-01")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="No employee found with staff_id = EMP001")
     *         )
     *     )
     * )
     */
    public function show(ShowEmployeeRequest $request, string $staff_id)
    {


        // 1) base query: same columns + relations as your show()
            $query = Employee::select([
                'id',
                'subsidiary',
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
                'employment:id,employee_id,active,start_date',
            ]);

        // 2) exact match on staff_id
        $employees = $query->where('staff_id', $staff_id)->get();

        // 3) if none found, return 404
        if ($employees->isEmpty()) {
        abort(404, "No employee found with staff_id = {$staff_id}");
        }

        // 4) wrap in a collection resource so `data` is an array
        return (new EmployeeCollection($employees))
            ->additional([
                'success' => true,
                'message' => 'Employee(s) retrieved successfully',
            ]);
    }

    /**
     * Get employee details
     *
     * @OA\Get(
     *     path="/employees/{id}",
     *     summary="Get employee details",
     *     description="Returns employee details by ID with related data including employment, grant allocations, beneficiaries, and identification",
     *     operationId="getEmployeeDetailsById",
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
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/Employee",
     *                 description="Employee data with related information"
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
     *     )
     * )
     */
    public function employeeDetails(Request $request, $id)
    {
        $employee = Employee::with([
            'employment',
            'employeeGrantAllocations',
            'employeeGrantAllocations.grantItemAllocation',
            'employeeGrantAllocations.grantItemAllocation.grant',
            'employment.workLocation',
            // 'employment.grantAllocations.grantItemAllocation',
            // 'employment.grantAllocations.grantItemAllocation.grant',
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
     * Create a new employee with basic information
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
     *             required={"subsidiary","staff_id","first_name_en","gender","date_of_birth","status"},
     *             @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}, example="SMRU", description="Employee subsidiary"),
     *             @OA\Property(property="staff_id", type="string", example="EMP001", description="Unique staff identifier"),
     *             @OA\Property(property="initial_en", type="string", example="Mr.", description="English initial/title"),
     *             @OA\Property(property="initial_th", type="string", example="นาย", description="Thai initial/title"),
     *             @OA\Property(property="first_name_en", type="string", example="John", description="Employee first name in English"),
     *             @OA\Property(property="last_name_en", type="string", example="Doe", description="Employee last name in English"),
     *             @OA\Property(property="first_name_th", type="string", example="จอห์น", description="Employee first name in Thai"),
     *             @OA\Property(property="last_name_th", type="string", example="โด", description="Employee last name in Thai"),
     *             @OA\Property(property="gender", type="string", example="Male", description="Employee gender"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Employee date of birth"),
     *             @OA\Property(property="date_of_birth_th", type="string", example="01/01/2533", description="Employee date of birth in Thai format"),
     *             @OA\Property(property="age", type="integer", example=33, description="Employee age"),
     *             @OA\Property(property="status", type="string", enum={"Expats (Local)", "Local ID Staff", "Local non ID Staff"}, example="Local ID Staff", description="Employee status"),
     *             @OA\Property(property="mobile_phone", type="string", example="0812345678", description="Employee mobile phone"),
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
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="staff_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The staff id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="first_name_en",
     *                     type="array",
     *                     @OA\Items(type="string", example="The first name en field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create employee"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function store(StoreEmployeeRequest $request)
    {
        try {
            $validated = $request->validated();
            $employee = Employee::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data'    => $employee,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an employees
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

    /**
     * @OA\Put(
     *     path="/employees/{id}/basic-information",
     *     summary="Update employee basic information",
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
     *         @OA\JsonContent(
     *             required={"subsidiary", "staff_id", "first_name_en", "gender", "date_of_birth", "status"},
     *             @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}),
     *             @OA\Property(property="staff_id", type="string", maxLength=50),
     *             @OA\Property(property="initial_en", type="string", maxLength=10, nullable=true),
     *             @OA\Property(property="initial_th", type="string", maxLength=10, nullable=true),
     *             @OA\Property(property="first_name_en", type="string", maxLength=255),
     *             @OA\Property(property="last_name_en", type="string", maxLength=255, nullable=true),
     *             @OA\Property(property="first_name_th", type="string", maxLength=255, nullable=true),
     *             @OA\Property(property="last_name_th", type="string", maxLength=255, nullable=true),
     *             @OA\Property(property="gender", type="string", maxLength=10),
     *             @OA\Property(property="date_of_birth", type="string", format="date"),
     *             @OA\Property(property="status", type="string", enum={"Expats (Local)", "Local ID Staff", "Local non ID Staff"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee basic information updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee basic information updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/Employee"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     )
     * )
     */
    public function updateEmployeeBasicInformation(UpdateEmployeeRequest $request)
    {
        // validate the request
        $validated = $request->validated();

        // update the employee
        $employee = Employee::find($request->id);
        $employee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee basic information updated successfully',
            'data' => $employee
        ]);
    }

}
