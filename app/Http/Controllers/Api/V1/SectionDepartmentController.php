<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\IndexSectionDepartmentRequest;
use App\Http\Requests\OptionsSectionDepartmentRequest;
use App\Http\Requests\StoreSectionDepartmentRequest;
use App\Http\Requests\UpdateSectionDepartmentRequest;
use App\Http\Resources\SectionDepartmentResource;
use App\Models\SectionDepartment;
use App\Services\SectionDepartmentService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Handles CRUD operations for section departments within organizational departments.
 */
#[OA\Tag(name: 'Section Departments', description: 'API Endpoints for Section Department management')]
class SectionDepartmentController extends BaseApiController
{
    public function __construct(
        private readonly SectionDepartmentService $sectionDepartmentService,
    ) {}

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
    public function options(OptionsSectionDepartmentRequest $request): JsonResponse
    {
        $sections = $this->sectionDepartmentService->options($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Section department options retrieved successfully',
            'data' => $sections,
        ]);
    }

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
    public function index(IndexSectionDepartmentRequest $request): JsonResponse
    {
        $paginator = $this->sectionDepartmentService->list($request->validated());

        return SectionDepartmentResource::collection($paginator)
            ->additional(['success' => true, 'message' => 'Section departments retrieved successfully'])
            ->response();
    }

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
    public function byDepartment(int $departmentId): JsonResponse
    {
        $sectionDepartments = $this->sectionDepartmentService->byDepartment($departmentId);

        return response()->json([
            'success' => true,
            'message' => 'Section departments retrieved successfully',
            'data' => SectionDepartmentResource::collection($sectionDepartments),
        ]);
    }

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
    public function store(StoreSectionDepartmentRequest $request): JsonResponse
    {
        $sectionDepartment = $this->sectionDepartmentService->create($request->validated());

        return SectionDepartmentResource::make($sectionDepartment)
            ->additional(['success' => true, 'message' => 'Section department created successfully'])
            ->response()
            ->setStatusCode(201);
    }

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
    public function show(SectionDepartment $sectionDepartment): JsonResponse
    {
        $sectionDepartment = $this->sectionDepartmentService->show($sectionDepartment);

        return SectionDepartmentResource::make($sectionDepartment)
            ->additional(['success' => true, 'message' => 'Section department retrieved successfully'])
            ->response();
    }

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
    public function update(UpdateSectionDepartmentRequest $request, SectionDepartment $sectionDepartment): JsonResponse
    {
        $sectionDepartment = $this->sectionDepartmentService->update($sectionDepartment, $request->validated());

        return SectionDepartmentResource::make($sectionDepartment)
            ->additional(['success' => true, 'message' => 'Section department updated successfully'])
            ->response();
    }

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
    public function destroy(SectionDepartment $sectionDepartment): JsonResponse
    {
        $this->sectionDepartmentService->delete($sectionDepartment);

        return $this->successResponse(null, 'Section department deleted successfully');
    }
}
