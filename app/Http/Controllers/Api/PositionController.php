<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPositionRequest;
use App\Http\Requests\ListPositionOptionsRequest;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Http\Resources\PositionDetailResource;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Positions",
 *     description="API Endpoints for Position management"
 * )
 */
class PositionController extends Controller
{
    /**
     * Lightweight list for dropdowns
     *
     * @OA\Get(
     *     path="/positions/options",
     *     summary="Get position options (lightweight)",
     *     description="Returns minimal position list for dropdowns, optionally filtered by department",
     *     operationId="getPositionOptions",
     *     tags={"Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="department_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=1000, default=200)),
     *     @OA\Parameter(name="order_by", in="query", required=false, @OA\Schema(type="string", enum={"title","level","created_at"}, default="title")),
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
     *             @OA\Property(property="message", type="string", example="Position options retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="title", type="string", example="IT Specialist"),
     *                     @OA\Property(property="department_id", type="integer", example=8),
     *                     @OA\Property(property="department_name", type="string", example="IT")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function options(ListPositionOptionsRequest $request)
    {
        $validated = $request->validated();

        $query = Position::query()->with('department');

        if (isset($validated['department_id'])) {
            $query->inDepartment($validated['department_id']);
        }

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        if (isset($validated['is_active'])) {
            $validated['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        $orderBy = $validated['order_by'] ?? 'title';
        $direction = $validated['order_direction'] ?? 'asc';

        $positions = $query
            ->orderBy($orderBy, $direction)
            ->limit($validated['limit'] ?? 200)
            ->get(['id', 'title', 'department_id']);

        $data = $positions->map(function ($p) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'department_id' => $p->department_id,
                'department_name' => $p->department?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Position options retrieved successfully',
            'data' => $data,
        ]);
    }

    /**
     * Get all positions with optional filtering and pagination
     *
     * @OA\Get(
     *     path="/positions",
     *     summary="Get all positions",
     *     description="Returns a paginated list of positions with optional filtering and search",
     *     operationId="getPositions",
     *     tags={"Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for position title or department name",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="department_id",
     *         in="query",
     *         description="Filter by department ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
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
     *     @OA\Parameter(
     *         name="level",
     *         in="query",
     *         description="Filter by hierarchy level",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"title", "level", "created_at", "subordinates_count"})
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
     *             @OA\Property(property="message", type="string", example="Positions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Position")),
     *                 @OA\Property(property="meta", type="object"),
     *                 @OA\Property(property="links", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(IndexPositionRequest $request)
    {
        $validated = $request->validated();

        $query = Position::with(['department', 'manager'])->withDirectReportsCount();

        // Apply search filter
        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        // Apply department filter
        if (isset($validated['department_id'])) {
            $query->inDepartment($validated['department_id']);
        }

        // Apply active status filter
        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Apply manager status filter
        if (isset($validated['is_manager'])) {
            if ($validated['is_manager']) {
                $query->managers();
            } else {
                $query->where('is_manager', false);
            }
        }

        // Apply level filter
        if (isset($validated['level'])) {
            $query->atLevel($validated['level']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'title';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        if ($sortBy === 'direct_reports_count') {
            $query->orderBy('direct_reports_count', $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Add secondary sort by title for consistency
        if ($sortBy !== 'title') {
            $query->orderBy('title', 'asc');
        }

        // Paginate results
        $perPage = $validated['per_page'] ?? 20;
        $positions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Positions retrieved successfully',
            'data' => PositionResource::collection($positions)->response()->getData(),
        ]);
    }

    /**
     * Store a new position
     *
     * @OA\Post(
     *     path="/positions",
     *     summary="Create a new position",
     *     description="Creates a new position and returns it",
     *     operationId="storePosition",
     *     tags={"Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title", "department_id"},
     *
     *             @OA\Property(property="title", type="string", maxLength=255, example="Senior Software Engineer"),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="reports_to_position_id", type="integer", nullable=true, example=5),
     *             @OA\Property(property="level", type="integer", minimum=1, default=1, example=2),
     *             @OA\Property(property="is_manager", type="boolean", default=false, example=false),
     *             @OA\Property(property="is_active", type="boolean", default=true, example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Position created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Position created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Position")
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
    public function store(StorePositionRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id() ?? 'system';

        try {
            $position = Position::create($validated);
            $position->load(['department', 'reportsTo']);

            return response()->json([
                'success' => true,
                'message' => 'Position created successfully',
                'data' => new PositionResource($position),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => ['reports_to_position_id' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Get a specific position
     *
     * @OA\Get(
     *     path="/positions/{id}",
     *     summary="Get a specific position",
     *     description="Returns a specific position by ID with detailed information",
     *     operationId="getPosition",
     *     tags={"Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of position to return",
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
     *             @OA\Property(property="message", type="string", example="Position retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Position")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Position not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Position not found")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $position = Position::with(['department', 'manager', 'directReports'])
            ->withDirectReportsCount()
            ->find($id);

        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Position retrieved successfully',
            'data' => new PositionDetailResource($position),
        ]);
    }

    /**
     * Update a position
     *
     * @OA\Put(
     *     path="/positions/{id}",
     *     summary="Update a position",
     *     description="Updates a position and returns it",
     *     operationId="updatePosition",
     *     tags={"Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of position to update",
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
     *             @OA\Property(property="title", type="string", maxLength=255, example="Lead Software Engineer"),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="reports_to_position_id", type="integer", nullable=true, example=5),
     *             @OA\Property(property="level", type="integer", minimum=1, example=2),
     *             @OA\Property(property="is_manager", type="boolean", example=true),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Position updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Position updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Position")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Position not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdatePositionRequest $request, $id)
    {
        $position = Position::find($id);

        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found',
            ], 404);
        }

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id() ?? 'system';

        try {
            $position->update($validated);
            $position->load(['department', 'reportsTo']);

            return response()->json([
                'success' => true,
                'message' => 'Position updated successfully',
                'data' => new PositionResource($position->fresh(['department', 'reportsTo'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => ['reports_to_position_id' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Delete a position
     *
     * @OA\Delete(
     *     path="/positions/{id}",
     *     summary="Delete a position",
     *     description="Deletes a position (deactivates if has subordinates, hard delete if empty)",
     *     operationId="deletePosition",
     *     tags={"Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of position to delete",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Position deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Position deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Position not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete position with active subordinates"
     *     )
     * )
     */
    public function destroy($id)
    {
        $position = Position::find($id);

        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found',
            ], 404);
        }

        // Check if position has active subordinates
        $activeSubordinatesCount = $position->activeSubordinates()->count();

        if ($activeSubordinatesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete position with {$activeSubordinatesCount} active subordinates. Please reassign subordinates first.",
            ], 422);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully',
        ]);
    }

    /**
     * Get direct reports for a manager position
     *
     * @OA\Get(
     *     path="/positions/{id}/direct-reports",
     *     summary="Get direct reports of a manager position",
     *     description="Returns all positions that directly report to this manager",
     *     operationId="getPositionDirectReports",
     *     tags={"Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of manager position",
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
     *             @OA\Property(property="message", type="string", example="Direct reports retrieved successfully"),
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
     *         description="Position not found"
     *     )
     * )
     */
    public function directReports($id)
    {
        $position = Position::find($id);

        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found',
            ], 404);
        }

        if (! $position->is_manager) {
            return response()->json([
                'success' => false,
                'message' => 'This position is not a manager',
            ], 422);
        }

        $directReports = $position->directReports()
            ->with(['department'])
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Direct reports retrieved successfully',
            'data' => PositionResource::collection($directReports),
        ]);
    }
}
