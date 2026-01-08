<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSectionDepartmentRequest;
use App\Http\Requests\ListSectionDepartmentOptionsRequest;
use App\Http\Requests\StoreSectionDepartmentRequest;
use App\Http\Requests\UpdateSectionDepartmentRequest;
use App\Http\Resources\SectionDepartmentResource;
use App\Models\SectionDepartment;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Section Departments', description: 'API Endpoints for Section Department management')]
class SectionDepartmentController extends Controller
{
    /**
     * Lightweight list for dropdowns
     */
    #[OA\Get(
        path: '/section-departments/options',
        summary: 'Get section department options (lightweight)',
        description: 'Returns minimal section department list for dropdowns',
        operationId: 'getSectionDepartmentOptions',
        security: [['bearerAuth' => []]],
        tags: ['Section Departments']
    )]
    #[OA\Parameter(name: 'department_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function options(ListSectionDepartmentOptionsRequest $request)
    {
        $validated = $request->validated();

        $query = SectionDepartment::with('department');

        if (isset($validated['department_id'])) {
            $query->byDepartment($validated['department_id']);
        }

        if (isset($validated['search'])) {
            $query->where('name', 'like', "%{$validated['search']}%");
        }

        if (isset($validated['is_active'])) {
            $validated['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        $sections = $query
            ->orderBy('name', 'asc')
            ->limit($validated['limit'] ?? 200)
            ->get(['id', 'name', 'department_id']);

        $data = $sections->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'department_id' => $s->department_id,
                'department_name' => $s->department?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Section department options retrieved successfully',
            'data' => $data,
        ]);
    }

    /**
     * Get all section departments with optional filtering and pagination
     */
    #[OA\Get(
        path: '/section-departments',
        summary: 'Get all section departments',
        description: 'Returns a paginated list of section departments with optional filtering',
        operationId: 'getSectionDepartments',
        security: [['bearerAuth' => []]],
        tags: ['Section Departments']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'department_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index(IndexSectionDepartmentRequest $request)
    {
        $validated = $request->validated();

        $query = SectionDepartment::with('department')->withCounts();

        // Apply search filter
        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', "%{$validated['search']}%")
                    ->orWhere('description', 'like', "%{$validated['search']}%")
                    ->orWhereHas('department', function ($dq) use ($validated) {
                        $dq->where('name', 'like', "%{$validated['search']}%");
                    });
            });
        }

        // Apply department filter
        if (isset($validated['department_id'])) {
            $query->byDepartment($validated['department_id']);
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
        $query->orderBy($sortBy, $sortDirection);

        // Paginate results
        $perPage = $validated['per_page'] ?? 20;
        $sectionDepartments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Section departments retrieved successfully',
            'data' => SectionDepartmentResource::collection($sectionDepartments)->response()->getData(),
        ]);
    }

    /**
     * Get section departments by department ID
     */
    #[OA\Get(
        path: '/section-departments/by-department/{departmentId}',
        summary: 'Get section departments by department',
        description: 'Returns all section departments for a specific department',
        operationId: 'getSectionDepartmentsByDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Section Departments']
    )]
    #[OA\Parameter(name: 'departmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function getByDepartment($departmentId)
    {
        $sectionDepartments = SectionDepartment::with('department')
            ->withCounts()
            ->byDepartment($departmentId)
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Section departments retrieved successfully',
            'data' => SectionDepartmentResource::collection($sectionDepartments),
        ]);
    }

    /**
     * Store a new section department
     */
    #[OA\Post(
        path: '/section-departments',
        summary: 'Create a new section department',
        description: 'Creates a new section department and returns it',
        operationId: 'storeSectionDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Section Departments']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'department_id'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'department_id', type: 'integer'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', default: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Section department created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreSectionDepartmentRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id() ?? 'system';

        $sectionDepartment = SectionDepartment::create($validated);
        $sectionDepartment->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Section department created successfully',
            'data' => new SectionDepartmentResource($sectionDepartment),
        ], 201);
    }

    /**
     * Get a specific section department
     */
    #[OA\Get(
        path: '/section-departments/{id}',
        summary: 'Get a specific section department',
        description: 'Returns a specific section department by ID',
        operationId: 'getSectionDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Section Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Section department not found')]
    public function show($id)
    {
        $sectionDepartment = SectionDepartment::with('department')
            ->withCounts()
            ->find($id);

        if (! $sectionDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Section department not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Section department retrieved successfully',
            'data' => new SectionDepartmentResource($sectionDepartment),
        ]);
    }

    /**
     * Update a section department
     */
    #[OA\Put(
        path: '/section-departments/{id}',
        summary: 'Update a section department',
        description: 'Updates a section department and returns it',
        operationId: 'updateSectionDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Section Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'department_id', type: 'integer'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Section department updated successfully')]
    #[OA\Response(response: 404, description: 'Section department not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateSectionDepartmentRequest $request, $id)
    {
        $sectionDepartment = SectionDepartment::find($id);

        if (! $sectionDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Section department not found',
            ], 404);
        }

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id() ?? 'system';

        $sectionDepartment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Section department updated successfully',
            'data' => new SectionDepartmentResource($sectionDepartment->fresh(['department'])),
        ]);
    }

    /**
     * Delete a section department
     */
    #[OA\Delete(
        path: '/section-departments/{id}',
        summary: 'Delete a section department',
        description: 'Soft deletes a section department',
        operationId: 'deleteSectionDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Section Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Section department deleted successfully')]
    #[OA\Response(response: 404, description: 'Section department not found')]
    #[OA\Response(response: 422, description: 'Cannot delete section department with active employments')]
    public function destroy($id)
    {
        $sectionDepartment = SectionDepartment::withCounts()->find($id);

        if (! $sectionDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Section department not found',
            ], 404);
        }

        // Check if section department has active employments
        if ($sectionDepartment->active_employments_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete section department with {$sectionDepartment->active_employments_count} active employments. Please reassign employments first.",
            ], 422);
        }

        $sectionDepartment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Section department deleted successfully',
        ]);
    }
}
