<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * ModuleController
 *
 * Handles API requests for system modules (menus) that are stored in the database.
 * This enables dynamic menu generation based on database configuration.
 */
class ModuleController extends Controller
{
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
    public function index(Request $request): JsonResponse
    {
        $modules = Module::active()
            ->ordered()
            ->with('children')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    /**
     * Get modules in hierarchical tree structure.
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
    public function hierarchical(Request $request): JsonResponse
    {
        $modules = Module::active()
            ->ordered()
            ->rootModules()
            ->with('activeChildren')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
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
    public function byCategory(Request $request): JsonResponse
    {
        $modules = Module::active()
            ->ordered()
            ->get()
            ->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    /**
     * Get a single module by ID.
     */
    #[OA\Get(
        path: '/api/v1/admin/modules/{id}',
        summary: 'Get single module',
        description: 'Retrieve a specific module by ID',
        operationId: 'getModule',
        security: [['bearerAuth' => []]],
        tags: ['Modules']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Module ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Module retrieved successfully')]
    #[OA\Response(response: 404, description: 'Module not found')]
    public function show(int $id): JsonResponse
    {
        $module = Module::with('parent', 'children')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $module,
        ]);
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
    public function permissions(Request $request): JsonResponse
    {
        $modules = Module::active()->get();
        $permissions = [];

        foreach ($modules as $module) {
            $permissions = array_merge($permissions, $module->getAllPermissions());
        }

        $permissions = array_values(array_unique($permissions));

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}
