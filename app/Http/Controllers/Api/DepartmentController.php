<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexDepartmentRequest;
use App\Http\Requests\ListDepartmentOptionsRequest;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentDetailResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\PositionResource;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Departments",
 *     description="API Endpoints for Department management"
 * )
 */
class DepartmentController extends Controller
{
    /**
     * Lightweight list for dropdowns
     *
     * @OA\Get(
     *     path="/departments/options",
     *     summary="Get department options (lightweight)",
     *     description="Returns minimal department list for dropdowns",
     *     operationId="getDepartmentOptions",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=1000, default=200)),
     *     @OA\Parameter(name="order_by", in="query", required=false, @OA\Schema(type="string", enum={"name","created_at"}, default="name")),
     *     @OA\Parameter(name="order_direction", in="query", required=false, @OA\Schema(type="string", enum={"asc","desc"}, default="asc")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department options retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=8),
     *                     @OA\Property(property="name", type="string", example="IT")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function options(ListDepartmentOptionsRequest $request)
    {
        $validated = $request->validated();

        $query = Department::query();

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        if (isset($validated['is_active'])) {
            $validated['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        $orderBy = $validated['order_by'] ?? 'name';
        $direction = $validated['order_direction'] ?? 'asc';

        $departments = $query
            ->orderBy($orderBy, $direction)
            ->limit($validated['limit'] ?? 200)
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'Department options retrieved successfully',
            'data' => $departments,
        ]);
    }

    /**
     * Get all departments with optional filtering and pagination
     *
     * @OA\Get(
     *     path="/departments",
     *     summary="Get all departments",
     *     description="Returns a paginated list of departments with optional filtering and search",
     *     operationId="getDepartments",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for department name or description",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"name", "created_at", "positions_count"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
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
     *             @OA\Property(property="message", type="string", example="Departments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Department")),
     *                 @OA\Property(property="meta", type="object"),
     *                 @OA\Property(property="links", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(IndexDepartmentRequest $request)
    {
        $validated = $request->validated();

        $query = Department::withPositionsCount();

        // Apply search filter
        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        // Apply active status filter
        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        if ($sortBy === 'positions_count') {
            $query->orderBy('positions_count', $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Paginate results
        $perPage = $validated['per_page'] ?? 20;
        $departments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully',
            'data' => DepartmentResource::collection($departments)->response()->getData(),
        ]);
    }

    /**
     * Store a new department
     *
     * @OA\Post(
     *     path="/departments",
     *     summary="Create a new department",
     *     description="Creates a new department and returns it",
     *     operationId="storeDepartment",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", maxLength=255, example="Information Technology"),
     *             @OA\Property(property="description", type="string", nullable=true, example="IT department responsible for technology infrastructure"),
     *             @OA\Property(property="is_active", type="boolean", default=true, example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Department created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Department")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(StoreDepartmentRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id() ?? 'system';

        $department = Department::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => new DepartmentResource($department),
        ], 201);
    }

    /**
     * Get a specific department
     *
     * @OA\Get(
     *     path="/departments/{id}",
     *     summary="Get a specific department",
     *     description="Returns a specific department by ID with detailed information",
     *     operationId="getDepartment",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department to return",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
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
     *             @OA\Property(property="message", type="string", example="Department retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Department")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Department not found")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $department = Department::withPositionsCount()
            ->with(['positions' => function ($query) {
                $query->active()->with('reportsTo');
            }])
            ->find($id);

        if (! $department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Department retrieved successfully',
            'data' => new DepartmentDetailResource($department),
        ]);
    }

    /**
     * Update a department
     *
     * @OA\Put(
     *     path="/departments/{id}",
     *     summary="Update a department",
     *     description="Updates a department and returns it",
     *     operationId="updateDepartment",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department to update",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", maxLength=255, example="Information Technology"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated IT department description"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Department updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Department")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdateDepartmentRequest $request, $id)
    {
        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id() ?? 'system';

        $department->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully',
            'data' => new DepartmentResource($department->fresh()),
        ]);
    }

    /**
     * Delete a department
     *
     * @OA\Delete(
     *     path="/departments/{id}",
     *     summary="Delete a department",
     *     description="Deletes a department (soft delete if has positions, hard delete if empty)",
     *     operationId="deleteDepartment",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department to delete",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Department deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete department with active positions"
     *     )
     * )
     */
    public function destroy($id)
    {
        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        // Check if department has active positions
        $activePositionsCount = $department->activePositions()->count();

        if ($activePositionsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete department with {$activePositionsCount} active positions. Please reassign or deactivate positions first.",
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ]);
    }

    /**
     * Get positions in a specific department
     *
     * @OA\Get(
     *     path="/departments/{id}/positions",
     *     summary="Get all positions in a department",
     *     description="Returns all positions within a specific department",
     *     operationId="getDepartmentPositions",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="is_manager",
     *         in="query",
     *         description="Filter by manager status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
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
     *             @OA\Property(property="message", type="string", example="Department positions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Position")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department not found"
     *     )
     * )
     */
    public function positions(Request $request, $id)
    {
        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $query = $department->positions()->with(['reportsTo', 'subordinates'])->withSubordinatesCount();

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_manager')) {
            $query->where('is_manager', $request->boolean('is_manager'));
        }

        // Order by hierarchy level and then by title
        $positions = $query->orderBy('level')
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Department positions retrieved successfully',
            'data' => PositionResource::collection($positions),
        ]);
    }

    /**
     * Get managers in a specific department
     *
     * @OA\Get(
     *     path="/departments/{id}/managers",
     *     summary="Get all managers in a department",
     *     description="Returns all manager positions within a specific department",
     *     operationId="getDepartmentManagers",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
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
     *             @OA\Property(property="message", type="string", example="Department managers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Position")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department not found"
     *     )
     * )
     */
    public function managers($id)
    {
        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $managers = $department->managerPositions()
            ->with(['reportsTo', 'subordinates'])
            ->withSubordinatesCount()
            ->orderBy('level')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Department managers retrieved successfully',
            'data' => PositionResource::collection($managers),
        ]);
    }
}
