<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Module\IndexModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * ModuleController
 *
 * Handles API requests for system modules (menus) that are stored in the database.
 * This enables dynamic menu generation based on database configuration.
 *
 * Read-only controller — module data is managed through seeders.
 */
class ModuleController extends BaseApiController
{
    public function __construct(
        private readonly ModuleService $moduleService,
    ) {}

    /**
     * Get all active modules with their children.
     */
    #[OA\Get(
        path: '/api/v1/admin/modules',
        summary: 'Get all active modules',
        description: 'Retrieve all active modules with their configuration',
        operationId: 'getModules',
        security: [['bearerAuth' => []]],
        tags: ['Modules']
    )]
    #[OA\Response(response: 200, description: 'Modules retrieved successfully')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function index(IndexModuleRequest $request): JsonResponse
    {
        $paginator = $this->moduleService->list($request->validated());

        return ModuleResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Modules retrieved successfully',
                'filters' => [
                    'applied_filters' => array_filter([
                        'category' => $request->validated('category'),
                        'search' => $request->validated('search'),
                    ]),
                ],
            ])
            ->response();
    }

    /**
     * Get modules in hierarchical tree structure.
     *
     * Returns root modules with only their active children.
     * Uses the `activeChildren` relationship, so the response key
     * is `active_children` (not `children`).
     */
    #[OA\Get(
        path: '/api/v1/admin/modules/hierarchical',
        summary: 'Get modules in tree structure',
        description: 'Retrieve modules organized as hierarchical tree',
        operationId: 'getModulesHierarchical',
        security: [['bearerAuth' => []]],
        tags: ['Modules']
    )]
    #[OA\Response(response: 200, description: 'Modules retrieved successfully')]
    public function hierarchical(): JsonResponse
    {
        $modules = $this->moduleService->hierarchical();

        return ModuleResource::collection($modules)
            ->additional([
                'success' => true,
                'message' => 'Modules retrieved successfully',
            ])
            ->response();
    }

    /**
     * Get modules grouped by category.
     */
    #[OA\Get(
        path: '/api/v1/admin/modules/by-category',
        summary: 'Get modules grouped by category',
        description: 'Retrieve modules organized by category',
        operationId: 'getModulesByCategory',
        security: [['bearerAuth' => []]],
        tags: ['Modules']
    )]
    #[OA\Response(response: 200, description: 'Modules retrieved successfully')]
    public function byCategory(): JsonResponse
    {
        $grouped = $this->moduleService->byCategory();

        $transformed = $grouped->map(function ($modules) {
            return ModuleResource::collection($modules);
        });

        return $this->successResponse($transformed, 'Modules grouped by category');
    }

    /**
     * Get a single module by ID.
     */
    #[OA\Get(
        path: '/api/v1/admin/modules/{module}',
        summary: 'Get single module',
        description: 'Retrieve a specific module by ID',
        operationId: 'getModule',
        security: [['bearerAuth' => []]],
        tags: ['Modules']
    )]
    #[OA\Parameter(name: 'module', in: 'path', required: true, description: 'Module ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Module retrieved successfully')]
    #[OA\Response(response: 404, description: 'Module not found')]
    public function show(Module $module): JsonResponse
    {
        $module->load('parent', 'children');

        return ModuleResource::make($module)
            ->additional(['success' => true, 'message' => 'Module retrieved successfully'])
            ->response();
    }

    /**
     * Get all permissions from all modules.
     */
    #[OA\Get(
        path: '/api/v1/admin/modules/permissions',
        summary: 'Get all module permissions',
        description: 'Retrieve all unique permissions from all modules',
        operationId: 'getModulePermissions',
        security: [['bearerAuth' => []]],
        tags: ['Modules']
    )]
    #[OA\Response(response: 200, description: 'Permissions retrieved successfully')]
    public function permissions(): JsonResponse
    {
        $permissions = $this->moduleService->permissions();

        return $this->successResponse($permissions, 'Permissions retrieved successfully');
    }
}
