<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SafeDeleteBlockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexDepartmentRequest;
use App\Http\Requests\OptionsDepartmentRequest;
use App\Http\Requests\SafeDeleteRequest;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentDetailResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\PositionResource;
use App\Models\Department;
use App\Services\SafeDeleteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Departments', description: 'API Endpoints for Department management')]
class DepartmentController extends Controller
{
    /**
     * Lightweight list for dropdowns
     */
    #[OA\Get(
        path: '/departments/options',
        summary: 'Get department options (lightweight)',
        description: 'Returns minimal department list for dropdowns',
        operationId: 'getDepartmentOptions',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000, default: 200))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function options(OptionsDepartmentRequest $request)
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
     */
    #[OA\Get(
        path: '/departments',
        summary: 'Get all departments',
        description: 'Returns a paginated list of departments with optional filtering and search',
        operationId: 'getDepartments',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(response: 200, description: 'Successful operation')]
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
     */
    #[OA\Post(
        path: '/departments',
        summary: 'Create a new department',
        description: 'Creates a new department and returns it',
        operationId: 'storeDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Information Technology'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', default: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Department created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
     */
    #[OA\Get(
        path: '/departments/{id}',
        summary: 'Get a specific department',
        description: 'Returns a specific department by ID with detailed information',
        operationId: 'getDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Department not found')]
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
     */
    #[OA\Put(
        path: '/departments/{id}',
        summary: 'Update a department',
        description: 'Updates a department and returns it',
        operationId: 'updateDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Department updated successfully')]
    #[OA\Response(response: 404, description: 'Department not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
     */
    #[OA\Delete(
        path: '/departments/{id}',
        summary: 'Delete a department',
        description: 'Deletes a department (soft delete if has positions, hard delete if empty)',
        operationId: 'deleteDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Department deleted successfully')]
    #[OA\Response(response: 404, description: 'Department not found')]
    #[OA\Response(response: 422, description: 'Cannot delete department with active positions')]
    public function destroy($id, SafeDeleteRequest $request)
    {
        try {
            $department = Department::findOrFail($id);
            $service = app(SafeDeleteService::class);

            $manifest = $service->delete($department, $request->input('reason'));

            return response()->json([
                'success' => true,
                'message' => 'Department moved to recycle bin',
                'deletion_key' => $manifest->deletion_key,
                'deleted_records_count' => $manifest->snapshot_count,
            ]);

        } catch (SafeDeleteBlockedException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department',
                'blockers' => $e->blockers,
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete department: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get positions in a specific department
     */
    #[OA\Get(
        path: '/departments/{id}/positions',
        summary: 'Get all positions in a department',
        description: 'Returns all positions within a specific department',
        operationId: 'getDepartmentPositions',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'is_manager', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Department not found')]
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
     */
    #[OA\Get(
        path: '/departments/{id}/managers',
        summary: 'Get all managers in a department',
        description: 'Returns all manager positions within a specific department',
        operationId: 'getDepartmentManagers',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Department not found')]
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
