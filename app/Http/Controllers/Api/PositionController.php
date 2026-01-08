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
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Positions', description: 'API Endpoints for Position management')]
class PositionController extends Controller
{
    /**
     * Lightweight list for dropdowns
     */
    #[OA\Get(
        path: '/positions/options',
        summary: 'Get position options (lightweight)',
        description: 'Returns minimal position list for dropdowns, optionally filtered by department',
        operationId: 'getPositionOptions',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'department_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000, default: 200))]
    #[OA\Response(response: 200, description: 'Successful operation')]
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
     */
    #[OA\Get(
        path: '/positions',
        summary: 'Get all positions',
        description: 'Returns a paginated list of positions with optional filtering and search',
        operationId: 'getPositions',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'department_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'is_manager', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'level', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(response: 200, description: 'Successful operation')]
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
     */
    #[OA\Post(
        path: '/positions',
        summary: 'Create a new position',
        description: 'Creates a new position and returns it',
        operationId: 'storePosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'department_id'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'department_id', type: 'integer'),
                new OA\Property(property: 'reports_to_position_id', type: 'integer', nullable: true),
                new OA\Property(property: 'level', type: 'integer', minimum: 1, default: 1),
                new OA\Property(property: 'is_manager', type: 'boolean', default: false),
                new OA\Property(property: 'is_active', type: 'boolean', default: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Position created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
     */
    #[OA\Get(
        path: '/positions/{id}',
        summary: 'Get a specific position',
        description: 'Returns a specific position by ID with detailed information',
        operationId: 'getPosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Position not found')]
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
     */
    #[OA\Put(
        path: '/positions/{id}',
        summary: 'Update a position',
        description: 'Updates a position and returns it',
        operationId: 'updatePosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'department_id', type: 'integer'),
                new OA\Property(property: 'reports_to_position_id', type: 'integer', nullable: true),
                new OA\Property(property: 'level', type: 'integer', minimum: 1),
                new OA\Property(property: 'is_manager', type: 'boolean'),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Position updated successfully')]
    #[OA\Response(response: 404, description: 'Position not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
     */
    #[OA\Delete(
        path: '/positions/{id}',
        summary: 'Delete a position',
        description: 'Deletes a position (deactivates if has subordinates, hard delete if empty)',
        operationId: 'deletePosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Position deleted successfully')]
    #[OA\Response(response: 404, description: 'Position not found')]
    #[OA\Response(response: 422, description: 'Cannot delete position with active subordinates')]
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
     */
    #[OA\Get(
        path: '/positions/{id}/direct-reports',
        summary: 'Get direct reports of a manager position',
        description: 'Returns all positions that directly report to this manager',
        operationId: 'getPositionDirectReports',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Position not found')]
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
