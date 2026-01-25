<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGrantItemRequest;
use App\Http\Requests\UpdateGrantItemRequest;
use App\Http\Resources\GrantItemResource;
use App\Models\GrantItem;
use App\Notifications\GrantItemActionNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * GrantItemController
 *
 * Manages grant item CRUD operations.
 *
 * Standard RESTful Methods:
 * - index()   : List all grant items
 * - show()    : Get single grant item by ID
 * - store()   : Create new grant item
 * - update()  : Update grant item data
 * - destroy() : Delete grant item
 *
 * Related Controllers:
 * - GrantController : For managing parent grants
 *
 * @version 1.0
 *
 * @since 2026-01-24
 */
#[OA\Tag(name: 'Grant Items', description: 'API Endpoints for managing grant items')]
class GrantItemController extends Controller
{
    /**
     * Display a listing of grant items
     *
     * @OA\Get(
     *     path="/grant-items",
     *     operationId="getGrantItems",
     *     summary="List all grant items",
     *     description="Returns a list of all grant items across all grants",
     *     tags={"Grant Items"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant items retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant items retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="grant_id", type="integer", example=1),
     *                     @OA\Property(property="grant_code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="grant_name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                     @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                     @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                     @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                     @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position"),
     *                     @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=10)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access grant items"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant items"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $grantItems = GrantItem::with(['grant:id,code,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Grant items retrieved successfully',
                'data' => GrantItemResource::collection($grantItems),
                'count' => $grantItems->count(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified grant item
     *
     * @OA\Get(
     *     path="/grant-items/{id}",
     *     operationId="showGrantItem",
     *     summary="Get a specific grant item by ID",
     *     description="Returns a specific grant item by ID with its associated grant details",
     *     tags={"Grant Items"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of grant item to return",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="grant_id", type="integer", example=1),
     *                 @OA\Property(property="grant", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="organization", type="string", example="SMRU"),
     *                     @OA\Property(property="description", type="string", example="Grant for health initiatives"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2023-12-31")
     *                 ),
     *                 @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                 @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                 @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                 @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                 @OA\Property(property="grant_position_number", type="integer", example=2),
     *                 @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
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
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant item"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $grantItem = GrantItem::with('grant')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Grant item retrieved successfully',
                'data' => $grantItem,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant item not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created grant item
     *
     * @OA\Post(
     *     path="/grant-items",
     *     operationId="storeGrantItem",
     *     summary="Store a new grant item",
     *     description="Creates a new grant item associated with an existing grant",
     *     tags={"Grant Items"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"grant_id"},
     *
     *             @OA\Property(property="grant_id", type="integer", example=1, description="ID of the existing grant"),
     *             @OA\Property(property="grant_position", type="string", example="Project Manager", description="Position title - must be unique within grant when combined with budget line code"),
     *             @OA\Property(property="grant_salary", type="number", format="float", example=75000, description="Salary amount"),
     *             @OA\Property(property="grant_benefit", type="number", format="float", example=15000, description="Benefits amount"),
     *             @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75, description="Level of effort (0-1)"),
     *             @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position"),
     *             @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding - must be unique within grant when combined with position"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Grant item created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="grant_item", ref="#/components/schemas/GrantItem"),
     *                 @OA\Property(property="capacity", type="integer", example=2)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or duplicate item",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create grant item"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(StoreGrantItemRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Get validated data
            $data = $request->validated();

            // Add user info
            $data['created_by'] = auth()->user()->name ?? 'system';
            $data['updated_by'] = auth()->user()->name ?? 'system';

            // Create the grant item
            $grantItem = GrantItem::create($data);

            // Refresh to ensure all relationships are loaded
            $grantItem->refresh();

            // Load the grant relationship for notification
            $grantItem->load('grant');

            DB::commit();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy && $grantItem->grant) {
                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantItemActionNotification('created', $grantItem, $grantItem->grant, $performedBy, 'grants_list'),
                    'created'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant item created successfully',
                'data' => [
                    'grant_item' => $grantItem,
                    'capacity' => $grantItem->grant_position_number ?? 1,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified grant item
     *
     * @OA\Put(
     *     path="/grant-items/{id}",
     *     operationId="updateGrantItem",
     *     summary="Update a grant item",
     *     description="Update an existing grant item by ID",
     *     tags={"Grant Items"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant item ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="grant_id", type="integer", example=1, description="ID of the grant - changing this may affect uniqueness validation"),
     *             @OA\Property(property="grant_position", type="string", example="Project Manager", description="Position title - must be unique within grant when combined with budget line code"),
     *             @OA\Property(property="grant_salary", type="number", example=5000, description="Salary amount"),
     *             @OA\Property(property="grant_benefit", type="number", example=1000, description="Benefits amount"),
     *             @OA\Property(property="grant_level_of_effort", type="number", example=0.75, description="Level of effort (0-1)"),
     *             @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position"),
     *             @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding - must be unique within grant when combined with position"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant item updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="grant_item", ref="#/components/schemas/GrantItem"),
     *                 @OA\Property(property="capacity", type="integer", example=2),
     *                 @OA\Property(property="active_allocations", type="integer", example=1),
     *                 @OA\Property(property="available_capacity", type="integer", example=1)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or duplicate item",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     )
     * )
     */
    public function update(UpdateGrantItemRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Find the grant item or return 404
            $grantItem = GrantItem::findOrFail($id);

            // Get validated data
            $validated = $request->validated();

            // Add user info
            $validated['updated_by'] = auth()->user()->name ?? 'system';

            // Update the grant item
            $grantItem->update($validated);

            // Reload grant relationship for notification
            $grantItem->load('grant');

            DB::commit();

            // Get current active allocations count for this grant item
            $activeAllocationsCount = \App\Models\EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
                ->where('status', 'active')
                ->count();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy && $grantItem->grant) {
                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantItemActionNotification('updated', $grantItem, $grantItem->grant, $performedBy, 'grants_list'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant item updated successfully',
                'data' => [
                    'grant_item' => $grantItem,
                    'capacity' => $grantItem->grant_position_number ?? 1,
                    'active_allocations' => $activeAllocationsCount,
                    'available_capacity' => max(0, ($grantItem->grant_position_number ?? 1) - $activeAllocationsCount),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Grant item not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified grant item
     *
     * @OA\Delete(
     *     path="/grant-items/{id}",
     *     operationId="destroyGrantItem",
     *     summary="Delete a grant item",
     *     description="Delete a specific grant item by ID",
     *     tags={"Grant Items"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant item ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant item deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete grant item"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            $grantItem = GrantItem::findOrFail($id);
            $grant = $grantItem->grant;

            // Store grant item and grant data before deletion for notification
            $grantItemData = $grantItem->toArray();
            $grantData = $grant ? $grant->toArray() : null;

            DB::transaction(function () use ($grantItem) {
                $grantItem->delete();
            });

            // Send notification to all users
            $performedBy = auth()->user();
            if ($performedBy && $grantData) {
                $grantItemForNotification = (object) [
                    'id' => $grantItemData['id'] ?? null,
                    'grant_position' => $grantItemData['grant_position'] ?? 'Unknown Position',
                ];

                $grantForNotification = (object) [
                    'id' => $grantData['id'] ?? null,
                    'name' => $grantData['name'] ?? 'Unknown Grant',
                    'code' => $grantData['code'] ?? 'N/A',
                ];

                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantItemActionNotification('deleted', $grantItemForNotification, $grantForNotification, $performedBy, 'grants_list'),
                    'deleted'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant item deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant item not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
