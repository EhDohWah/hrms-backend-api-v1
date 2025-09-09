<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmploymentRequest;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\OrgFundedAllocation;
use App\Models\PositionSlot;
use App\Traits\HasCacheManagement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Employments",
 *     description="API Endpoints for managing employee employment records"
 * )
 */
class EmploymentController extends Controller
{
    use HasCacheManagement;

    /**
     * Override model name for cache management
     */
    protected function getModelName(): string
    {
        return 'employment';
    }

    /**
     * Display a listing of employments with advanced pagination, filtering, and sorting.
     *
     * @OA\Get(
     *     path="/employments",
     *     summary="Get employment records with advanced filtering and pagination",
     *     description="Returns a paginated list of employment records with filtering by subsidiary, employment type, and work location. Supports sorting by staff ID, employee name, work location, and start date.",
     *     operationId="getEmployments",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_subsidiary",
     *         in="query",
     *         description="Filter employments by employee subsidiary (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="SMRU,BHF")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_employment_type",
     *         in="query",
     *         description="Filter by employment type (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Full-Time,Part-Time")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_work_location",
     *         in="query",
     *         description="Filter by work location name (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Main Office,Branch Office")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_department",
     *         in="query",
     *         description="Filter by department name (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Administration,Finance,IT")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"staff_id", "employee_name", "work_location", "start_date"}, example="start_date")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employments retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Employment")
     *             ),
     *
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(
     *                     property="applied_filters",
     *                     type="object",
     *                     @OA\Property(property="subsidiary", type="array", @OA\Items(type="string"), example={"SMRU"}),
     *                     @OA\Property(property="employment_type", type="array", @OA\Items(type="string"), example={"Full-Time"}),
     *                     @OA\Property(property="work_location", type="array", @OA\Items(type="string"), example={"Main Office"}),
     *                     @OA\Property(property="department", type="array", @OA\Items(type="string"), example={"Administration"})
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid parameters provided",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"per_page": {"The per page must be between 1 and 100."}})
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve employments"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_subsidiary' => 'string|nullable',
                'filter_employment_type' => 'string|nullable',
                'filter_work_location' => 'string|nullable',
                'filter_department' => 'string|nullable',
                'sort_by' => 'string|nullable|in:staff_id,employee_name,work_location,start_date',
                'sort_order' => 'string|nullable|in:asc,desc',
                'include_allocations' => 'boolean', // New parameter for conditional loading
            ]);

            // Determine pagination parameters
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $sortBy = $validated['sort_by'] ?? 'start_date';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Build optimized query with selective fields
            $query = Employment::select([
                'id',
                'employee_id',
                'employment_type',
                'pay_method',
                'probation_pass_date',
                'start_date',
                'end_date',
                'department_position_id',
                'work_location_id',
                'position_salary',
                'probation_salary',
                'fte',
                'health_welfare',
                'pvd',
                'saving_fund',
                'created_at',
                'updated_at',
                'created_by',
                'updated_by',
            ])->with([
                'employee:id,staff_id,subsidiary,first_name_en,last_name_en',
                'departmentPosition:id,department,position',
                'workLocation:id,name',
            ]);

            // Conditionally load allocations if requested
            if ($validated['include_allocations'] ?? false) {
                $query->with([
                    'employeeFundingAllocations:id,employment_id,allocation_type,level_of_effort,allocated_amount',
                    'employeeFundingAllocations.positionSlot:id,grant_id,position_title',
                    'employeeFundingAllocations.positionSlot.grant:id,name',
                ]);
            }

            // Apply subsidiary filter (through employee relationship)
            if (! empty($validated['filter_subsidiary'])) {
                $subsidiaries = array_map('trim', explode(',', $validated['filter_subsidiary']));
                $query->whereHas('employee', function ($q) use ($subsidiaries) {
                    $q->whereIn('subsidiary', $subsidiaries);
                });
            }

            // Apply employment type filter
            if (! empty($validated['filter_employment_type'])) {
                $employmentTypes = array_map('trim', explode(',', $validated['filter_employment_type']));
                $query->whereIn('employment_type', $employmentTypes);
            }

            // Apply work location filter (by work location name or ID)
            if (! empty($validated['filter_work_location'])) {
                $workLocations = array_map('trim', explode(',', $validated['filter_work_location']));
                $query->where(function ($q) use ($workLocations) {
                    $q->whereHas('workLocation', function ($wq) use ($workLocations) {
                        $wq->whereIn('name', $workLocations);
                    })->orWhereIn('work_location_id', array_filter($workLocations, 'is_numeric'));
                });
            }

            // Apply department filter (by department name from department_positions table)
            if (! empty($validated['filter_department'])) {
                $departments = array_map('trim', explode(',', $validated['filter_department']));
                $query->whereHas('departmentPosition', function ($dq) use ($departments) {
                    $dq->whereIn('department', $departments);
                });
            }

            // Apply sorting optimizations
            switch ($sortBy) {
                case 'staff_id':
                    $query->addSelect([
                        'sort_staff_id' => Employee::select('staff_id')
                            ->whereColumn('employees.id', 'employments.employee_id')
                            ->limit(1),
                    ])->orderBy('sort_staff_id', $sortOrder);
                    break;

                case 'employee_name':
                    $query->addSelect([
                        'sort_employee_name' => Employee::selectRaw("CONCAT(COALESCE(first_name_en, ''), ' ', COALESCE(last_name_en, ''))")
                            ->whereColumn('employees.id', 'employments.employee_id')
                            ->limit(1),
                    ])->orderBy('sort_employee_name', $sortOrder);
                    break;

                case 'work_location':
                    $query->addSelect([
                        'sort_location_name' => DB::table('work_locations')
                            ->select('name')
                            ->whereColumn('work_locations.id', 'employments.work_location_id')
                            ->limit(1),
                    ])->orderBy('sort_location_name', $sortOrder);
                    break;

                case 'start_date':
                default:
                    $query->orderBy('start_date', $sortOrder);
                    break;
            }

            // Use the new caching system instead of manual cache management
            $filters = array_filter([
                'filter_subsidiary' => $validated['filter_subsidiary'] ?? null,
                'filter_employment_type' => $validated['filter_employment_type'] ?? null,
                'filter_work_location' => $validated['filter_work_location'] ?? null,
                'filter_department' => $validated['filter_department'] ?? null,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'include_allocations' => $validated['include_allocations'] ?? false,
            ]);

            // Cache and paginate results using the new caching system
            $employments = $this->cacheAndPaginate($query, $filters, $perPage);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_subsidiary'])) {
                $appliedFilters['subsidiary'] = explode(',', $validated['filter_subsidiary']);
            }
            if (! empty($validated['filter_employment_type'])) {
                $appliedFilters['employment_type'] = explode(',', $validated['filter_employment_type']);
            }
            if (! empty($validated['filter_work_location'])) {
                $appliedFilters['work_location'] = explode(',', $validated['filter_work_location']);
            }
            if (! empty($validated['filter_department'])) {
                $appliedFilters['department'] = explode(',', $validated['filter_department']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Employments retrieved successfully',
                'data' => $employments->items(),
                'pagination' => [
                    'current_page' => $employments->currentPage(),
                    'per_page' => $employments->perPage(),
                    'total' => $employments->total(),
                    'last_page' => $employments->lastPage(),
                    'from' => $employments->firstItem(),
                    'to' => $employments->lastItem(),
                    'has_more_pages' => $employments->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search employment records by staff ID.
     *
     * @OA\Get(
     *     path="/employments/search/staff-id/{staffId}",
     *     summary="Search employment records by staff ID",
     *     description="Returns employment records for a specific employee identified by their staff ID. Includes all related information like employee details, department position, work location, and funding allocations.",
     *     operationId="searchEmploymentByStaffId",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="staffId",
     *         in="path",
     *         description="Staff ID of the employee to search for",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="EMP001")
     *     ),
     *
     *     @OA\Parameter(
     *         name="include_inactive",
     *         in="query",
     *         description="Include inactive/ended employment records",
     *         required=false,
     *
     *         @OA\Schema(type="boolean", example=false, default=false)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employment records found successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employment records found for staff ID: EMP001"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                     @OA\Property(property="subsidiary", type="string", example="SMRU"),
     *                     @OA\Property(property="first_name_en", type="string", example="John"),
     *                     @OA\Property(property="last_name_en", type="string", example="Doe"),
     *                     @OA\Property(property="full_name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(
     *                     property="employments",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/Employment")
     *                 ),
     *
     *                 @OA\Property(property="total_employments", type="integer", example=2),
     *                 @OA\Property(property="active_employments", type="integer", example=1),
     *                 @OA\Property(property="inactive_employments", type="integer", example=1)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No employee found with staff ID: EMP001")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to search employment records"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function searchByStaffId(Request $request, $staffId)
    {
        try {
            // Validate the staff ID parameter
            if (empty($staffId) || ! is_string($staffId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff ID is required and must be a valid string',
                ], 422);
            }

            // Validate query parameters
            $validated = $request->validate([
                'include_inactive' => 'boolean',
            ]);

            $includeInactive = $validated['include_inactive'] ?? false;

            // First, find the employee by staff ID
            $employee = Employee::where('staff_id', $staffId)
                ->select('id', 'staff_id', 'subsidiary', 'first_name_en', 'last_name_en')
                ->first();

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => "No employee found with staff ID: {$staffId}",
                ], 404);
            }

            // Build employment query with relationships - EXACTLY like index() method
            $employmentsQuery = Employment::with([
                'employee:id,staff_id,subsidiary,first_name_en,last_name_en',
                'departmentPosition:id,department,position',
                'workLocation:id,name',
                'employeeFundingAllocations',
            ])->where('employee_id', $employee->id);

            // Filter active/inactive employments if requested
            if (! $includeInactive) {
                $employmentsQuery->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>', now());
                });
            }

            // Order by start date (most recent first) - EXACTLY like index() method
            $employments = $employmentsQuery->orderBy('start_date', 'desc')->get();

            // Calculate employment statistics
            $totalEmployments = $employments->count();
            $activeEmployments = $employments->filter(function ($employment) {
                return ! $employment->end_date || $employment->end_date > now();
            })->count();
            $inactiveEmployments = $totalEmployments - $activeEmployments;

            // Add computed full name to employee data
            $employeeData = $employee->toArray();
            $employeeData['full_name'] = trim($employee->first_name_en.' '.$employee->last_name_en);

            // Return EXACTLY the same format as index() method
            return response()->json([
                'success' => true,
                'message' => "Employment records found for staff ID: {$staffId}",
                'data' => $employments, // ← FIXED: Return employments directly like index() method
                'employee_summary' => [
                    'staff_id' => $employee->staff_id,
                    'full_name' => $employeeData['full_name'],
                    'subsidiary' => $employee->subsidiary,
                ],
                'statistics' => [
                    'total_employments' => $totalEmployments,
                    'active_employments' => $activeEmployments,
                    'inactive_employments' => $inactiveEmployments,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search employment records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/employments",
     *     operationId="createEmploymentWithFundingAllocations",
     *     tags={"Employments"},
     *     summary="Create employment record with funding allocations",
     *     description="Creates an employment record and associated funding allocations. For org_funded allocations, creates org_funded_allocation records first, then creates employee_funding_allocations for both grant and org_funded types.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "employment_type", "start_date", "position_salary", "allocations"},
     *
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee"),
     *             @OA\Property(property="employment_type", type="string", description="Type of employment"),
     *             @OA\Property(property="pay_method", type="string", description="Pay method", nullable=true),
     *             @OA\Property(property="probation_pass_date", type="string", format="date", description="Probation pass date", nullable=true),
     *             @OA\Property(property="start_date", type="string", format="date", description="Employment start date"),
     *             @OA\Property(property="end_date", type="string", format="date", description="Employment end date", nullable=true),
     *             @OA\Property(property="department_position_id", type="integer", description="Department position ID", nullable=true),
     *             @OA\Property(property="section_department", type="string", description="Section department", nullable=true),
     *             @OA\Property(property="work_location_id", type="integer", description="Work location ID", nullable=true),
     *             @OA\Property(property="position_salary", type="number", format="float", description="Position salary"),
     *             @OA\Property(property="probation_salary", type="number", format="float", description="Probation salary", nullable=true),

     *             @OA\Property(property="fte", type="number", format="float", description="Full-time equivalent", nullable=true),
     *             @OA\Property(property="health_welfare", type="boolean", description="Health welfare benefit", default=false),
     *             @OA\Property(property="pvd", type="boolean", description="Provident fund", default=false),
     *             @OA\Property(property="saving_fund", type="boolean", description="Saving fund", default=false),
     *             @OA\Property(
     *                 property="allocations",
     *                 type="array",
     *                 description="Array of funding allocations",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"allocation_type", "level_of_effort"},
     *
     *                     @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, description="Type of allocation"),
     *                     @OA\Property(property="position_slot_id", type="integer", description="Position slot ID (for grant allocations)", nullable=true),
     *                     @OA\Property(property="org_funded_id", type="integer", description="Temporary org_funded_id from frontend (will be ignored)", nullable=true),
     *                     @OA\Property(property="grant_id", type="integer", description="Grant ID (for org_funded allocations)", nullable=true),
     *                     @OA\Property(property="level_of_effort", type="number", format="float", minimum=0, maximum=100, description="Level of effort as percentage (0-100)"),
     *                     @OA\Property(property="allocated_amount", type="number", format="float", minimum=0, description="Allocated amount", nullable=true),
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Employment and allocations created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employment and funding allocations created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employment", ref="#/components/schemas/Employment"),
     *                 @OA\Property(property="funding_allocations", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *                 @OA\Property(property="org_funded_allocations", type="array", @OA\Items(ref="#/components/schemas/OrgFundedAllocation"))
     *             ),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="employment_created", type="boolean"),
     *                 @OA\Property(property="org_funded_created", type="integer"),
     *                 @OA\Property(property="funding_allocations_created", type="integer")
     *             ),
     *             @OA\Property(property="warnings", type="array", @OA\Items(type="string"), nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreEmploymentRequest $request)
    {
        try {
            // ============================================================================
            // SECTION 1: GET VALIDATED DATA FROM FORM REQUEST
            // ============================================================================
            $validated = $request->validated();
            $currentUser = Auth::user()->name ?? 'system';

            // ============================================================================
            // SECTION 2: BUSINESS LOGIC VALIDATION
            // ============================================================================

            // Validate that the total effort of all allocations equals exactly 100%
            $totalEffort = array_sum(array_column($validated['allocations'], 'level_of_effort'));
            if ($totalEffort != 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total effort of all allocations must equal exactly 100%',
                    'current_total' => $totalEffort,
                ], 422);
            }

            // Check if employee already has active employment (using date-based logic)
            $today = Carbon::today();
            $existingActiveEmployment = Employment::where('employee_id', $validated['employee_id'])
                ->where('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->exists();

            if ($existingActiveEmployment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already has an active employment record. Please end the existing employment first.',
                ], 422);
            }

            // ============================================================================
            // SECTION 3: DATABASE TRANSACTION START
            // ============================================================================
            DB::beginTransaction();

            $createdOrgFundedAllocations = [];
            $createdFundingAllocations = [];
            $errors = [];
            $warnings = [];

            // ============================================================================
            // SECTION 4: CREATE EMPLOYMENT RECORD
            // ============================================================================
            $employmentData = array_merge(
                collect($validated)->except('allocations')->toArray(),
                [
                    'created_by' => $currentUser,
                    'updated_by' => $currentUser,
                ]
            );

            $employment = Employment::create($employmentData);

            // ============================================================================
            // SECTION 5: PROCESS ALLOCATIONS
            // ============================================================================
            foreach ($validated['allocations'] as $index => $allocationData) {
                try {
                    $allocationType = $allocationData['allocation_type'];

                    // ============================================================================
                    // SECTION 5A: HANDLE GRANT ALLOCATIONS
                    // ============================================================================
                    if ($allocationType === 'grant') {
                        // Validate position slot exists
                        $positionSlot = PositionSlot::with('grantItem')->find($allocationData['position_slot_id']);
                        if (! $positionSlot) {

                            $errors[] = "Allocation #{$index}: Position slot not found";

                            continue;
                        }

                        // Check grant capacity constraints using date-based logic
                        $grantItem = $positionSlot->grantItem;
                        if ($grantItem && $grantItem->grant_position_number > 0) {
                            $currentAllocations = EmployeeFundingAllocation::whereHas('positionSlot', function ($query) use ($grantItem) {
                                $query->where('grant_item_id', $grantItem->id);
                            })
                                ->where('allocation_type', 'grant')
                                ->where('start_date', '<=', $today)
                                ->where(function ($query) use ($today) {
                                    $query->whereNull('end_date')
                                        ->orWhere('end_date', '>=', $today);
                                })
                                ->count();

                            if ($currentAllocations >= $grantItem->grant_position_number) {
                                $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}";

                                continue;
                            }
                        }

                        // Create grant funding allocation
                        $fundingAllocation = EmployeeFundingAllocation::create([
                            'employee_id' => $employment->employee_id,
                            'employment_id' => $employment->id,
                            'position_slot_id' => $allocationData['position_slot_id'],
                            'org_funded_id' => null,
                            'level_of_effort' => $allocationData['level_of_effort'] / 100, // Convert percentage to decimal
                            'allocation_type' => 'grant',
                            'allocated_amount' => $allocationData['allocated_amount'] ?? null, // Added allocated_amount
                            'start_date' => $validated['start_date'],
                            'end_date' => $validated['end_date'] ?? null,
                            'created_by' => $currentUser,
                            'updated_by' => $currentUser,
                        ]);

                        $createdFundingAllocations[] = $fundingAllocation;
                    }

                    // ============================================================================
                    // SECTION 5B: HANDLE ORG FUNDED ALLOCATIONS
                    // ============================================================================
                    elseif ($allocationType === 'org_funded') {
                        // For org_funded, we need grant_id to create the OrgFundedAllocation
                        if (empty($allocationData['grant_id'])) {
                            $errors[] = "Allocation #{$index}: grant_id is required for org_funded allocations";

                            continue;
                        }

                        // First, create the org_funded_allocation record
                        $orgFundedAllocation = OrgFundedAllocation::create([
                            'grant_id' => $allocationData['grant_id'],
                            'department_position_id' => $employment->department_position_id,
                            'description' => 'Auto-created for employment ID: '.$employment->id,
                            'created_by' => $currentUser,
                            'updated_by' => $currentUser,
                        ]);

                        $createdOrgFundedAllocations[] = $orgFundedAllocation;

                        // Then, create the employee funding allocation referencing the org_funded_allocation
                        $fundingAllocation = EmployeeFundingAllocation::create([
                            'employee_id' => $employment->employee_id,
                            'employment_id' => $employment->id,
                            'position_slot_id' => null,
                            'org_funded_id' => $orgFundedAllocation->id, // Use the ID from the created org_funded_allocation
                            'level_of_effort' => $allocationData['level_of_effort'] / 100, // Convert percentage to decimal
                            'allocation_type' => 'org_funded',
                            'allocated_amount' => $allocationData['allocated_amount'] ?? null, // Added allocated_amount
                            'start_date' => $validated['start_date'],
                            'end_date' => $validated['end_date'] ?? null,
                            'created_by' => $currentUser,
                            'updated_by' => $currentUser,
                        ]);

                        $createdFundingAllocations[] = $fundingAllocation;
                    }

                } catch (\Exception $e) {
                    $errors[] = "Allocation #{$index}: ".$e->getMessage();
                }
            }

            // ============================================================================
            // SECTION 6: HANDLE ERRORS AND ROLLBACK IF NECESSARY
            // ============================================================================
            if (empty($createdFundingAllocations) && ! empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employment and allocations',
                    'errors' => $errors,
                ], 422);
            }

            // If some allocations failed but others succeeded, add warnings
            if (! empty($errors)) {
                $warnings = array_merge($warnings, $errors);
            }

            // ============================================================================
            // SECTION 7: COMMIT TRANSACTION AND PREPARE RESPONSE
            // ============================================================================
            DB::commit();

            // Load the created records with their relationships
            $employmentWithRelations = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'departmentPosition:id,department,position',
                'workLocation:id,name',
            ])->find($employment->id);

            $fundingAllocationsWithRelations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'orgFunded.grant:id,name,code',
                'orgFunded.departmentPosition:id,department,position',
            ])->whereIn('id', collect($createdFundingAllocations)->pluck('id'))->get();

            $orgFundedAllocationsWithRelations = OrgFundedAllocation::with([
                'grant:id,name,code',
                'departmentPosition:id,department,position',
            ])->whereIn('id', collect($createdOrgFundedAllocations)->pluck('id'))->get();

            // ============================================================================
            // SECTION 8: RETURN SUCCESS RESPONSE
            // ============================================================================
            $response = [
                'success' => true,
                'message' => 'Employment and funding allocations created successfully',
                'data' => [
                    'employment' => $employmentWithRelations,
                    'funding_allocations' => $fundingAllocationsWithRelations,
                    'org_funded_allocations' => $orgFundedAllocationsWithRelations,
                ],
                'summary' => [
                    'employment_created' => true,
                    'org_funded_created' => count($createdOrgFundedAllocations),
                    'funding_allocations_created' => count($createdFundingAllocations),
                ],
            ];

            if (! empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            // Invalidate employment-related caches after successful creation
            $this->invalidateCacheAfterWrite($employment);

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create employment and funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified employment record.
     *
     * @OA\Get(
     *     path="/employments/{id}",
     *     summary="Get employment record by ID",
     *     description="Returns a specific employment record by ID",
     *     operationId="getEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to return",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Employment"),
     *             @OA\Property(property="message", type="string", example="Employment retrieved successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employment not found")
     * )
     */
    public function show($id)
    {
        try {
            $employment = Employment::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $employment,
                'message' => 'Employment retrieved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found',
            ], 404);
        }
    }

    /**
     * Get funding allocations for a specific employment record.
     *
     * @OA\Get(
     *     path="/employments/{id}/funding-allocations",
     *     summary="Get funding allocations for an employment record",
     *     description="Returns all funding allocations associated with a specific employment record, including related grant and position slot information",
     *     operationId="getEmploymentFundingAllocations",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Funding allocations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="employment_id", type="integer"),
     *                 @OA\Property(property="employee", type="object"),
     *                 @OA\Property(
     *                     property="funding_allocations",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="allocation_type", type="string"),
     *                         @OA\Property(property="level_of_effort", type="number"),
     *                         @OA\Property(property="allocated_amount", type="number"),
     *                         @OA\Property(property="start_date", type="string", format="date"),
     *                         @OA\Property(property="end_date", type="string", format="date", nullable=true),
     *                         @OA\Property(property="position_slot", type="object", nullable=true),
     *                         @OA\Property(property="org_funded", type="object", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="total_allocations", type="integer"),
     *                 @OA\Property(property="total_level_of_effort", type="number")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employment not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function getFundingAllocations($id)
    {
        try {
            // Find the employment record with employee information
            $employment = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
            ])->findOrFail($id);

            // Get funding allocations with related data
            $fundingAllocations = EmployeeFundingAllocation::with([
                'positionSlot:id,grant_item_id,slot_number,budget_line_id',
                'positionSlot.grantItem:id,grant_id,grant_position,grant_salary',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'orgFunded:id,grant_id,department_position_id,description',
                'orgFunded.grant:id,name,code',
                'orgFunded.departmentPosition:id,department,position',
            ])
                ->where('employment_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate summary statistics
            $totalAllocations = $fundingAllocations->count();
            $totalLevelOfEffort = $fundingAllocations->sum('level_of_effort');

            // Format the response data
            $formattedAllocations = $fundingAllocations->map(function ($allocation) {
                $formatted = [
                    'id' => $allocation->id,
                    'allocation_type' => $allocation->allocation_type,
                    'level_of_effort' => $allocation->level_of_effort,
                    'level_of_effort_percentage' => ($allocation->level_of_effort * 100).'%',
                    'allocated_amount' => $allocation->allocated_amount,
                    'formatted_allocated_amount' => $allocation->allocated_amount ? '฿'.number_format($allocation->allocated_amount, 2) : null,
                    'start_date' => $allocation->start_date,
                    'end_date' => $allocation->end_date,
                    'created_at' => $allocation->created_at,
                    'created_by' => $allocation->created_by,
                ];

                // Add grant/position slot information based on allocation type
                if ($allocation->allocation_type === 'grant' && $allocation->positionSlot) {
                    $formatted['position_slot'] = [
                        'id' => $allocation->positionSlot->id,
                        'slot_number' => $allocation->positionSlot->slot_number,
                        'grant_item' => $allocation->positionSlot->grantItem ? [
                            'id' => $allocation->positionSlot->grantItem->id,
                            'grant_position' => $allocation->positionSlot->grantItem->grant_position,
                            'grant_salary' => $allocation->positionSlot->grantItem->grant_salary,
                            'grant' => $allocation->positionSlot->grantItem->grant ? [
                                'id' => $allocation->positionSlot->grantItem->grant->id,
                                'name' => $allocation->positionSlot->grantItem->grant->name,
                                'code' => $allocation->positionSlot->grantItem->grant->code,
                            ] : null,
                        ] : null,
                        'budget_line' => $allocation->positionSlot->budgetLine ? [
                            'id' => $allocation->positionSlot->budgetLine->id,
                            'budget_line_code' => $allocation->positionSlot->budgetLine->budget_line_code,
                            'description' => $allocation->positionSlot->budgetLine->description,
                        ] : null,
                    ];
                    $formatted['org_funded'] = null;
                } elseif ($allocation->allocation_type === 'org_funded' && $allocation->orgFunded) {
                    $formatted['org_funded'] = [
                        'id' => $allocation->orgFunded->id,
                        'description' => $allocation->orgFunded->description,
                        'grant' => $allocation->orgFunded->grant ? [
                            'id' => $allocation->orgFunded->grant->id,
                            'name' => $allocation->orgFunded->grant->name,
                            'code' => $allocation->orgFunded->grant->code,
                        ] : null,
                        'department_position' => $allocation->orgFunded->departmentPosition ? [
                            'id' => $allocation->orgFunded->departmentPosition->id,
                            'department' => $allocation->orgFunded->departmentPosition->department,
                            'position' => $allocation->orgFunded->departmentPosition->position,
                        ] : null,
                    ];
                    $formatted['position_slot'] = null;
                } else {
                    $formatted['position_slot'] = null;
                    $formatted['org_funded'] = null;
                }

                return $formatted;
            });

            return response()->json([
                'success' => true,
                'message' => 'Funding allocations retrieved successfully',
                'data' => [
                    'employment_id' => $employment->id,
                    'employee' => [
                        'id' => $employment->employee->id,
                        'staff_id' => $employment->employee->staff_id,
                        'name' => $employment->employee->first_name_en.' '.$employment->employee->last_name_en,
                        'subsidiary' => $employment->employee->subsidiary,
                    ],
                    'funding_allocations' => $formattedAllocations,
                    'summary' => [
                        'total_allocations' => $totalAllocations,
                        'total_level_of_effort' => $totalLevelOfEffort,
                        'total_level_of_effort_percentage' => ($totalLevelOfEffort * 100).'%',
                        'allocation_types' => $fundingAllocations->groupBy('allocation_type')->map->count(),
                    ],
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment record not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified employment record with optional funding allocations.
     *
     * @OA\Put(
     *     path="/employments/{id}",
     *     operationId="updateEmploymentWithFundingAllocations",
     *     tags={"Employments"},
     *     summary="Update employment record with optional funding allocations",
     *     description="Updates an employment record and optionally replaces funding allocations. If allocations are provided, all existing allocations will be replaced with the new ones. For org_funded allocations, creates new org_funded_allocation records. Validates that total effort equals 100% if allocations are provided.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to update",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee", nullable=true),
     *             @OA\Property(property="employment_type", type="string", description="Type of employment", nullable=true),
     *             @OA\Property(property="pay_method", type="string", description="Pay method", nullable=true),
     *             @OA\Property(property="probation_pass_date", type="string", format="date", description="Probation pass date", nullable=true),
     *             @OA\Property(property="start_date", type="string", format="date", description="Employment start date", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", description="Employment end date", nullable=true),
     *             @OA\Property(property="department_position_id", type="integer", description="Department position ID", nullable=true),
     *             @OA\Property(property="section_department", type="string", description="Section department", nullable=true),
     *             @OA\Property(property="work_location_id", type="integer", description="Work location ID", nullable=true),
     *             @OA\Property(property="position_salary", type="number", format="float", description="Position salary", nullable=true),
     *             @OA\Property(property="probation_salary", type="number", format="float", description="Probation salary", nullable=true),
     *             @OA\Property(property="fte", type="number", format="float", description="Full-time equivalent", nullable=true),
     *             @OA\Property(property="health_welfare", type="boolean", description="Health welfare benefit", nullable=true),
     *             @OA\Property(property="pvd", type="boolean", description="Provident fund", nullable=true),
     *             @OA\Property(property="saving_fund", type="boolean", description="Saving fund", nullable=true),
     *             @OA\Property(
     *                 property="allocations",
     *                 type="array",
     *                 description="Optional array of funding allocations to replace existing ones",
     *                 nullable=true,
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"allocation_type", "level_of_effort"},
     *
     *                     @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, description="Type of allocation"),
     *                     @OA\Property(property="position_slot_id", type="integer", description="Position slot ID (for grant allocations)", nullable=true),
     *                     @OA\Property(property="org_funded_id", type="integer", description="Temporary org_funded_id from frontend (will be ignored)", nullable=true),
     *                     @OA\Property(property="grant_id", type="integer", description="Grant ID (for org_funded allocations)", nullable=true),
     *                     @OA\Property(property="level_of_effort", type="number", format="float", minimum=0, maximum=100, description="Level of effort as percentage (0-100)"),
     *                     @OA\Property(property="allocated_amount", type="number", format="float", minimum=0, description="Allocated amount", nullable=true),
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employment and allocations updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employment and funding allocations updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employment", ref="#/components/schemas/Employment"),
     *                 @OA\Property(property="funding_allocations", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *                 @OA\Property(property="org_funded_allocations", type="array", @OA\Items(ref="#/components/schemas/OrgFundedAllocation"))
     *             ),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="employment_updated", type="boolean"),
     *                 @OA\Property(property="allocations_updated", type="boolean"),
     *                 @OA\Property(property="old_allocations_removed", type="integer"),
     *                 @OA\Property(property="org_funded_created", type="integer"),
     *                 @OA\Property(property="funding_allocations_created", type="integer")
     *             ),
     *             @OA\Property(property="warnings", type="array", @OA\Items(type="string"), nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employment not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            // ============================================================================
            // SECTION 1: REQUEST VALIDATION
            // ============================================================================
            $validator = Validator::make($request->all(), [
                // Employment fields - all optional for updates
                'employee_id' => 'nullable|exists:employees,id',
                'employment_type' => 'nullable|string',
                'pay_method' => 'nullable|string',
                'probation_pass_date' => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'department_position_id' => 'nullable|exists:department_positions,id',
                'section_department' => 'nullable|string|max:255',
                'work_location_id' => 'nullable|exists:work_locations,id',
                'position_salary' => 'nullable|numeric',
                'probation_salary' => 'nullable|numeric',
                'fte' => 'nullable|numeric',
                'health_welfare' => 'nullable|boolean',
                'pvd' => 'nullable|boolean',
                'saving_fund' => 'nullable|boolean',

                // Allocation fields - optional for updates
                'allocations' => 'nullable|array|min:1',
                'allocations.*.allocation_type' => 'required_with:allocations|string|in:grant,org_funded',
                'allocations.*.position_slot_id' => 'required_if:allocations.*.allocation_type,grant|nullable|exists:position_slots,id',
                'allocations.*.org_funded_id' => 'nullable|integer', // Frontend sends this but we'll ignore it
                'allocations.*.grant_id' => 'nullable|exists:grants,id', // For org_funded, we need the grant_id
                'allocations.*.level_of_effort' => 'required_with:allocations|numeric|min:0|max:100',
                'allocations.*.allocated_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $currentUser = Auth::user()->name ?? 'system';

            // ============================================================================
            // SECTION 2: BUSINESS LOGIC VALIDATION
            // ============================================================================

            // Find the existing employment record
            $employment = Employment::findOrFail($id);

            // If allocations are provided, validate that the total effort equals exactly 100%
            $allocationsProvided = ! empty($validated['allocations']);
            if ($allocationsProvided) {
                $totalEffort = array_sum(array_column($validated['allocations'], 'level_of_effort'));
                if ($totalEffort != 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total effort of all allocations must equal exactly 100%',
                        'current_total' => $totalEffort,
                    ], 422);
                }
            }

            // Validate date constraints if dates are being updated
            if (isset($validated['start_date']) && isset($validated['end_date'])) {
                if ($validated['end_date'] && $validated['start_date'] > $validated['end_date']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'End date must be after or equal to start date',
                    ], 422);
                }
            }

            // ============================================================================
            // SECTION 3: DATABASE TRANSACTION START
            // ============================================================================
            DB::beginTransaction();

            $createdOrgFundedAllocations = [];
            $createdFundingAllocations = [];
            $removedAllocationsCount = 0;
            $errors = [];
            $warnings = [];

            // ============================================================================
            // SECTION 4: UPDATE EMPLOYMENT RECORD
            // ============================================================================
            $employmentData = collect($validated)->except('allocations')->toArray();
            if (! empty($employmentData)) {
                $employmentData['updated_by'] = $currentUser;
                $employment->update($employmentData);
            }

            // ============================================================================
            // SECTION 5: HANDLE FUNDING ALLOCATIONS (if provided)
            // ============================================================================
            if ($allocationsProvided) {
                // Remove existing funding allocations and their related org_funded records
                $existingAllocations = EmployeeFundingAllocation::where('employment_id', $employment->id)->get();
                $removedAllocationsCount = $existingAllocations->count();

                // Collect org_funded IDs to clean up
                $orgFundedIdsToDelete = $existingAllocations->whereNotNull('org_funded_id')->pluck('org_funded_id')->toArray();

                // Delete existing allocations
                EmployeeFundingAllocation::where('employment_id', $employment->id)->delete();

                // Delete orphaned org_funded_allocations
                if (! empty($orgFundedIdsToDelete)) {
                    OrgFundedAllocation::whereIn('id', $orgFundedIdsToDelete)->delete();
                }

                // Process new allocations
                $today = Carbon::today();
                foreach ($validated['allocations'] as $index => $allocationData) {
                    try {
                        $allocationType = $allocationData['allocation_type'];

                        // ============================================================================
                        // SECTION 5A: HANDLE GRANT ALLOCATIONS
                        // ============================================================================
                        if ($allocationType === 'grant') {
                            // Validate position slot exists
                            $positionSlot = PositionSlot::with('grantItem')->find($allocationData['position_slot_id']);
                            if (! $positionSlot) {
                                $errors[] = "Allocation #{$index}: Position slot not found";

                                continue;
                            }

                            // Check grant capacity constraints using date-based logic
                            $grantItem = $positionSlot->grantItem;
                            if ($grantItem && $grantItem->grant_position_number > 0) {
                                $currentAllocations = EmployeeFundingAllocation::whereHas('positionSlot', function ($query) use ($grantItem) {
                                    $query->where('grant_item_id', $grantItem->id);
                                })
                                    ->where('allocation_type', 'grant')
                                    ->where('employment_id', '!=', $employment->id) // Exclude current employment
                                    ->where('start_date', '<=', $today)
                                    ->where(function ($query) use ($today) {
                                        $query->whereNull('end_date')
                                            ->orWhere('end_date', '>=', $today);
                                    })
                                    ->count();

                                if ($currentAllocations >= $grantItem->grant_position_number) {
                                    $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}";

                                    continue;
                                }
                            }

                            // Create grant funding allocation
                            $fundingAllocation = EmployeeFundingAllocation::create([
                                'employee_id' => $employment->employee_id,
                                'employment_id' => $employment->id,
                                'position_slot_id' => $allocationData['position_slot_id'],
                                'org_funded_id' => null,
                                'level_of_effort' => $allocationData['level_of_effort'] / 100, // Convert percentage to decimal
                                'allocation_type' => 'grant',
                                'allocated_amount' => $allocationData['allocated_amount'] ?? null,
                                'start_date' => $employment->start_date,
                                'end_date' => $employment->end_date ?? null,
                                'created_by' => $currentUser,
                                'updated_by' => $currentUser,
                            ]);

                            $createdFundingAllocations[] = $fundingAllocation;
                        }

                        // ============================================================================
                        // SECTION 5B: HANDLE ORG FUNDED ALLOCATIONS
                        // ============================================================================
                        elseif ($allocationType === 'org_funded') {
                            // For org_funded, we need grant_id to create the OrgFundedAllocation
                            if (empty($allocationData['grant_id'])) {
                                $errors[] = "Allocation #{$index}: grant_id is required for org_funded allocations";

                                continue;
                            }

                            // First, create the org_funded_allocation record
                            $orgFundedAllocation = OrgFundedAllocation::create([
                                'grant_id' => $allocationData['grant_id'],
                                'department_position_id' => $employment->department_position_id,
                                'description' => 'Auto-created for employment ID: '.$employment->id.' (Updated)',
                                'created_by' => $currentUser,
                                'updated_by' => $currentUser,
                            ]);

                            $createdOrgFundedAllocations[] = $orgFundedAllocation;

                            // Then, create the employee funding allocation referencing the org_funded_allocation
                            $fundingAllocation = EmployeeFundingAllocation::create([
                                'employee_id' => $employment->employee_id,
                                'employment_id' => $employment->id,
                                'position_slot_id' => null,
                                'org_funded_id' => $orgFundedAllocation->id,
                                'level_of_effort' => $allocationData['level_of_effort'] / 100, // Convert percentage to decimal
                                'allocation_type' => 'org_funded',
                                'allocated_amount' => $allocationData['allocated_amount'] ?? null,
                                'start_date' => $employment->start_date,
                                'end_date' => $employment->end_date ?? null,
                                'created_by' => $currentUser,
                                'updated_by' => $currentUser,
                            ]);

                            $createdFundingAllocations[] = $fundingAllocation;
                        }

                    } catch (\Exception $e) {
                        $errors[] = "Allocation #{$index}: ".$e->getMessage();
                    }
                }
            }

            // ============================================================================
            // SECTION 6: HANDLE ERRORS AND ROLLBACK IF NECESSARY
            // ============================================================================
            if ($allocationsProvided && empty($createdFundingAllocations) && ! empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update employment and allocations',
                    'errors' => $errors,
                ], 422);
            }

            // If some allocations failed but others succeeded, add warnings
            if (! empty($errors)) {
                $warnings = array_merge($warnings, $errors);
            }

            // ============================================================================
            // SECTION 7: COMMIT TRANSACTION AND PREPARE RESPONSE
            // ============================================================================
            DB::commit();

            // Load the updated records with their relationships
            $employmentWithRelations = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'departmentPosition:id,department,position',
                'workLocation:id,name',
                'employeeFundingAllocations',
            ])->find($employment->id);

            $responseData = [
                'employment' => $employmentWithRelations,
            ];

            // Include allocation data if allocations were updated
            if ($allocationsProvided) {
                $fundingAllocationsWithRelations = EmployeeFundingAllocation::with([
                    'employee:id,staff_id,first_name_en,last_name_en',
                    'employment:id,employment_type,start_date',
                    'positionSlot.grantItem.grant:id,name,code',
                    'positionSlot.budgetLine:id,budget_line_code,description',
                    'orgFunded.grant:id,name,code',
                    'orgFunded.departmentPosition:id,department,position',
                ])->whereIn('id', collect($createdFundingAllocations)->pluck('id'))->get();

                $orgFundedAllocationsWithRelations = OrgFundedAllocation::with([
                    'grant:id,name,code',
                    'departmentPosition:id,department,position',
                ])->whereIn('id', collect($createdOrgFundedAllocations)->pluck('id'))->get();

                $responseData['funding_allocations'] = $fundingAllocationsWithRelations;
                $responseData['org_funded_allocations'] = $orgFundedAllocationsWithRelations;
            }

            // ============================================================================
            // SECTION 8: RETURN SUCCESS RESPONSE
            // ============================================================================
            $response = [
                'success' => true,
                'message' => $allocationsProvided
                    ? 'Employment and funding allocations updated successfully'
                    : 'Employment updated successfully',
                'data' => $responseData,
                'summary' => [
                    'employment_updated' => true,
                    'allocations_updated' => $allocationsProvided,
                    'old_allocations_removed' => $removedAllocationsCount,
                    'org_funded_created' => count($createdOrgFundedAllocations),
                    'funding_allocations_created' => count($createdFundingAllocations),
                ],
            ];

            if (! empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            // Invalidate employment-related caches after successful update
            $this->invalidateCacheAfterWrite($employment);

            return response()->json($response, 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment record not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment and funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified employment record.
     *
     * @OA\Delete(
     *     path="/employments/{id}",
     *     summary="Delete an employment record",
     *     description="Deletes an employment record by ID",
     *     operationId="deleteEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to delete",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employment deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employment deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employment not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $employment = Employment::findOrFail($id);
            $employment->delete();

            // Invalidate employment-related caches after successful deletion
            $this->invalidateCacheAfterWrite($employment);

            return response()->json([
                'success' => true,
                'message' => 'Employment deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employment: '.$e->getMessage(),
            ], 500);
        }
    }
}
