<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     *
     * Returns modules in a flat structure with parent-child relationships.
     * Frontend can use this to build hierarchical menus.
     *
     * @OA\Get(
     *     path="/api/v1/admin/modules",
     *     summary="Get all active modules",
     *     description="Retrieve all active modules with their configuration",
     *     operationId="getModules",
     *     tags={"Modules"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Modules retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="user_management"),
     *                     @OA\Property(property="display_name", type="string", example="User Management"),
     *                     @OA\Property(property="description", type="string", example="Manage system users"),
     *                     @OA\Property(property="icon", type="string", example="users"),
     *                     @OA\Property(property="category", type="string", example="Administration"),
     *                     @OA\Property(property="route", type="string", example="/user-management/users"),
     *                     @OA\Property(property="read_permission", type="string", example="user.read"),
     *                     @OA\Property(property="edit_permissions", type="array",
     *
     *                         @OA\Items(type="string", example="user.create")
     *                     ),
     *
     *                     @OA\Property(property="order", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
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
     *
     * Returns modules organized as a tree with nested children.
     * Useful for rendering nested menus directly.
     *
     * @OA\Get(
     *     path="/api/v1/admin/modules/hierarchical",
     *     summary="Get modules in tree structure",
     *     description="Retrieve modules organized as hierarchical tree",
     *     operationId="getModulesHierarchical",
     *     tags={"Modules"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Modules retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     )
     * )
     */
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
     *
     * Returns modules organized by their category for easier UI grouping.
     * Useful for permission management interfaces.
     *
     * @OA\Get(
     *     path="/api/v1/admin/modules/by-category",
     *     summary="Get modules grouped by category",
     *     description="Retrieve modules organized by category",
     *     operationId="getModulesByCategory",
     *     tags={"Modules"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Modules retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="Administration", type="array",
     *
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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
     *
     * @OA\Get(
     *     path="/api/v1/admin/modules/{id}",
     *     summary="Get single module",
     *     description="Retrieve a specific module by ID",
     *     operationId="getModule",
     *     tags={"Modules"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Module ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Module retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Module not found"
     *     )
     * )
     */
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
     *
     * Returns a flat list of all unique permissions defined in modules.
     * Useful for permission management and validation.
     *
     * @OA\Get(
     *     path="/api/v1/admin/modules/permissions",
     *     summary="Get all module permissions",
     *     description="Retrieve all unique permissions from all modules",
     *     operationId="getModulePermissions",
     *     tags={"Modules"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Permissions retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(type="string", example="user.read")
     *             )
     *         )
     *     )
     * )
     */
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
