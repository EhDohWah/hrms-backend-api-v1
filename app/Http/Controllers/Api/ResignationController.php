<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResignationRequest;
use App\Http\Requests\UpdateResignationRequest;
use App\Models\Employee;
use App\Models\Resignation;
use App\Traits\HasCacheManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Resignations",
 *     description="Employee resignation management endpoints for handling resignation submissions, approvals, and workflow management"
 * )
 */
class ResignationController extends Controller
{
    use HasCacheManagement;

    /**
     * Override model name for cache management
     */
    protected function getModelName(): string
    {
        return 'resignation';
    }

    /**
     * Display a listing of resignations with advanced filtering and pagination.
     *
     * @OA\Get(
     *     path="/api/v1/resignations",
     *     summary="Get list of resignations",
     *     description="Returns paginated list of resignations with advanced filtering, search capabilities, and statistics",
     *     operationId="getResignations",
     *     tags={"Resignations"},
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
     *         @OA\Schema(type="integer", example=15, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in employee name, staff ID, or reason",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="John Doe")
     *     ),
     *
     *     @OA\Parameter(
     *         name="acknowledgement_status",
     *         in="query",
     *         description="Filter by acknowledgement status",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"Pending", "Acknowledged", "Rejected"}, example="Pending")
     *     ),
     *
     *     @OA\Parameter(
     *         name="department_id",
     *         in="query",
     *         description="Filter by department ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Parameter(
     *         name="reason",
     *         in="query",
     *         description="Filter by resignation reason (partial match)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Career")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"resignation_date", "last_working_date", "acknowledgement_status", "created_at"}, example="resignation_date")
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
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Resignations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Resignation")
     *             ),
     *
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=5),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=72),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=15)
     *                 ),
     *                 @OA\Property(property="cached", type="boolean", example=true),
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2024-02-01T10:30:00Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Access denied.")
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
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="per_page",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The per page field must not be greater than 100.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve resignations"),
     *             @OA\Property(property="error", type="string", example="Database connection error")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'nullable|string|max:255',
                'acknowledgement_status' => 'nullable|in:Pending,Acknowledged,Rejected',
                'department_id' => 'nullable|exists:departments,id',
                'reason' => 'nullable|string|max:50',
                'sort_by' => 'nullable|in:resignation_date,last_working_date,acknowledgement_status,created_at',
                'sort_order' => 'nullable|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 15;
            $sortBy = $validated['sort_by'] ?? 'resignation_date';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Build the query with eager loading
            $query = Resignation::with([
                'employee:id,staff_id,first_name_en,last_name_en,organization',
                'acknowledgedBy:id,name',
                'department:id,department',
                'position:id,position',
            ]);

            // Apply search filter
            if (! empty($validated['search'])) {
                $query->search($validated['search']);
            }

            // Apply acknowledgement status filter
            if (! empty($validated['acknowledgement_status'])) {
                $query->where('acknowledgement_status', $validated['acknowledgement_status']);
            }

            // Apply department filter
            if (! empty($validated['department_id'])) {
                $query->where('department_id', $validated['department_id']);
            }

            // Apply reason filter
            if (! empty($validated['reason'])) {
                $query->where('reason', 'like', '%'.$validated['reason'].'%');
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Cache and paginate results
            $filters = array_filter([
                'search' => $validated['search'] ?? null,
                'acknowledgement_status' => $validated['acknowledgement_status'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]);

            $resignations = $this->cacheAndPaginate($query, $filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Resignations retrieved successfully',
                'data' => $resignations->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $resignations->currentPage(),
                        'last_page' => $resignations->lastPage(),
                        'per_page' => $resignations->perPage(),
                        'total' => $resignations->total(),
                        'from' => $resignations->firstItem(),
                        'to' => $resignations->lastItem(),
                    ],
                    'cached' => true,
                    'timestamp' => now()->toISOString(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve resignations', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve resignations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resignation.
     *
     * @OA\Post(
     *     path="/api/v1/resignations",
     *     summary="Create a new resignation",
     *     description="Creates a new resignation record with automatic employee data population and validation",
     *     operationId="storeResignation",
     *     tags={"Resignations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Resignation data",
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "resignation_date", "last_working_date", "reason"},
     *
     *             @OA\Property(
     *                 property="employee_id",
     *                 type="integer",
     *                 format="int64",
     *                 description="ID of the employee submitting resignation",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="department_id",
     *                 type="integer",
     *                 format="int64",
     *                 description="ID of employee's department (auto-populated if not provided)",
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="position_id",
     *                 type="integer",
     *                 format="int64",
     *                 description="ID of employee's position (auto-populated if not provided)",
     *                 example=12
     *             ),
     *             @OA\Property(
     *                 property="resignation_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date when resignation was submitted (cannot be future date)",
     *                 example="2024-02-01"
     *             ),
     *             @OA\Property(
     *                 property="last_working_date",
     *                 type="string",
     *                 format="date",
     *                 description="Employee's last day of work (must be on or after resignation date)",
     *                 example="2024-02-29"
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 maxLength=50,
     *                 description="Primary reason for resignation",
     *                 example="Career Advancement"
     *             ),
     *             @OA\Property(
     *                 property="reason_details",
     *                 type="string",
     *                 description="Detailed explanation of resignation reason",
     *                 example="Accepted a position with better growth opportunities and higher compensation"
     *             ),
     *             @OA\Property(
     *                 property="acknowledgement_status",
     *                 type="string",
     *                 enum={"Pending", "Acknowledged", "Rejected"},
     *                 description="Initial status (defaults to Pending if not provided)",
     *                 example="Pending"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Resignation created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Resignation created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Resignation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Access denied.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="employee_id",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="Please select an employee.")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="resignation_date",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="Resignation date cannot be in the future.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create resignation"),
     *             @OA\Property(property="error", type="string", example="Database connection error")
     *         )
     *     )
     * )
     */
    public function store(StoreResignationRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $validated['created_by'] = Auth::user()->name ?? 'system';
            $validated['acknowledgement_status'] = $validated['acknowledgement_status'] ?? 'Pending';

            $resignation = Resignation::create($validated);

            $resignation->load([
                'employee:id,staff_id,first_name_en,last_name_en,organization',
                'department:id,department',
                'position:id,position',
                'acknowledgedBy:id,name',
            ]);

            DB::commit();

            // Invalidate related caches
            $this->invalidateCacheAfterWrite($resignation);

            return response()->json([
                'success' => true,
                'message' => 'Resignation created successfully',
                'data' => $resignation,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create resignation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resignation.
     *
     * @OA\Get(
     *     path="/api/v1/resignations/{id}",
     *     summary="Get resignation by ID",
     *     description="Returns detailed information about a specific resignation including related employee, department, and acknowledger data",
     *     operationId="getResignation",
     *     tags={"Resignations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Resignation ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
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
     *             @OA\Property(property="message", type="string", example="Resignation retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Resignation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Access denied.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Resignation not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resignation not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve resignation"),
     *             @OA\Property(property="error", type="string", example="Database connection error")
     *         )
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $resignation = Resignation::with([
                'employee:id,staff_id,first_name_en,last_name_en,organization',
                'acknowledgedBy:id,name',
                'department:id,department',
                'position:id,position',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Resignation retrieved successfully',
                'data' => $resignation,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resignation not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve resignation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resignation.
     *
     * @OA\Put(
     *     path="/api/v1/resignations/{id}",
     *     summary="Update resignation",
     *     description="Updates an existing resignation record with validation and cache invalidation",
     *     operationId="updateResignation",
     *     tags={"Resignations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Resignation ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Updated resignation data",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="employee_id",
     *                 type="integer",
     *                 format="int64",
     *                 description="ID of the employee (rarely changed after creation)",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="department_id",
     *                 type="integer",
     *                 format="int64",
     *                 description="ID of employee's department",
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="position_id",
     *                 type="integer",
     *                 format="int64",
     *                 description="ID of employee's position",
     *                 example=12
     *             ),
     *             @OA\Property(
     *                 property="resignation_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date when resignation was submitted",
     *                 example="2024-02-01"
     *             ),
     *             @OA\Property(
     *                 property="last_working_date",
     *                 type="string",
     *                 format="date",
     *                 description="Employee's last day of work",
     *                 example="2024-02-29"
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 maxLength=50,
     *                 description="Primary reason for resignation",
     *                 example="Better Opportunity"
     *             ),
     *             @OA\Property(
     *                 property="reason_details",
     *                 type="string",
     *                 description="Updated detailed explanation",
     *                 example="Received an offer from a leading tech company with 40% salary increase"
     *             ),
     *             @OA\Property(
     *                 property="acknowledgement_status",
     *                 type="string",
     *                 enum={"Pending", "Acknowledged", "Rejected"},
     *                 description="Current acknowledgement status",
     *                 example="Acknowledged"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Resignation updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Resignation updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Resignation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Access denied.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Resignation not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resignation not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="last_working_date",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="Last working date must be on or after resignation date.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update resignation"),
     *             @OA\Property(property="error", type="string", example="Database connection error")
     *         )
     *     )
     * )
     */
    public function update(UpdateResignationRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $resignation = Resignation::findOrFail($id);
            $validated = $request->validated();
            $validated['updated_by'] = Auth::user()->name ?? 'system';

            $resignation->update($validated);
            $resignation->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,department',
                'position:id,position',
                'acknowledgedBy:id,name',
            ]);

            DB::commit();

            // Invalidate related caches
            $this->invalidateCacheAfterWrite($resignation);

            return response()->json([
                'success' => true,
                'message' => 'Resignation updated successfully',
                'data' => $resignation,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resignation not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update resignation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resignation.
     *
     * @OA\Delete(
     *     path="/api/v1/resignations/{id}",
     *     summary="Delete resignation",
     *     description="Soft deletes a resignation record from the system with cache invalidation",
     *     operationId="deleteResignation",
     *     tags={"Resignations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Resignation ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Resignation deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Resignation deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Access denied.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Resignation not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resignation not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete resignation"),
     *             @OA\Property(property="error", type="string", example="Database connection error")
     *         )
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            $resignation = Resignation::findOrFail($id);
            $resignation->delete();

            // Invalidate related caches
            $this->invalidateCacheAfterWrite($resignation);

            return response()->json([
                'success' => true,
                'message' => 'Resignation deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resignation not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete resignation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Acknowledge or reject a resignation.
     *
     * @OA\Put(
     *     path="/api/v1/resignations/{id}/acknowledge",
     *     summary="Acknowledge or reject resignation",
     *     description="Updates resignation status to acknowledged or rejected with user tracking and timestamp",
     *     operationId="acknowledgeResignation",
     *     tags={"Resignations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Resignation ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Acknowledgement action",
     *
     *         @OA\JsonContent(
     *             required={"action"},
     *
     *             @OA\Property(
     *                 property="action",
     *                 type="string",
     *                 enum={"acknowledge", "reject"},
     *                 description="Action to take on the resignation",
     *                 example="acknowledge"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Resignation acknowledged/rejected successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Resignation acknowledged successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Resignation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only pending resignations can be acknowledged or rejected")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Access denied.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Resignation not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resignation not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="action",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The selected action is invalid.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to acknowledge resignation"),
     *             @OA\Property(property="error", type="string", example="Database connection error")
     *         )
     *     )
     * )
     */
    public function acknowledge(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'action' => 'required|in:acknowledge,reject',
            ]);

            $resignation = Resignation::findOrFail($id);
            $user = Auth::user();

            if ($resignation->acknowledgement_status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending resignations can be acknowledged or rejected',
                ], 400);
            }

            DB::beginTransaction();

            if ($validated['action'] === 'acknowledge') {
                $resignation->acknowledge($user);
                $message = 'Resignation acknowledged successfully';
            } else {
                $resignation->reject($user);
                $message = 'Resignation rejected successfully';
            }

            $resignation->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'acknowledgedBy:id,name',
                'department:id,department',
                'position:id,position',
            ]);

            DB::commit();

            // Invalidate related caches
            $this->invalidateCacheAfterWrite($resignation);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $resignation,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resignation not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge resignation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search employees for resignation assignment.
     */
    public function searchEmployees(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'limit' => 'integer|min:1|max:50',
            ]);

            $limit = $validated['limit'] ?? 10;
            $search = $validated['search'] ?? '';

            $query = Employee::with(['employment.departmentPosition'])
                ->where(function ($q) use ($search) {
                    if (! empty($search)) {
                        $q->where('staff_id', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$search}%"]);
                    }
                })
                ->limit($limit);

            $employees = $query->get()->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'staff_id' => $employee->staff_id,
                    'full_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
                    'department' => $employee->employment->departmentPosition->department ?? null,
                    'position' => $employee->employment->departmentPosition->position ?? null,
                    'organization' => $employee->organization,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Employees found',
                'data' => $employees,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search employees',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
