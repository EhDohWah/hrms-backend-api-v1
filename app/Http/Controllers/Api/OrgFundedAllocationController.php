<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrgFundedAllocationRequest;
use App\Http\Requests\UpdateOrgFundedAllocationRequest;
use App\Models\OrgFundedAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Org Funded Allocations",
 *     description="Operations related to organization funded allocations"
 * )
 */
class OrgFundedAllocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/org-funded-allocations",
     *     operationId="getOrgFundedAllocations",
     *     tags={"Org Funded Allocations"},
     *     summary="Get list of organization funded allocations",
     *     description="Returns paginated list of organization funded allocations with related grant, department and position data",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/OrgFundedAllocation")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $allocations = OrgFundedAllocation::with(['grant', 'department', 'position'])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($allocations);
    }

    /**
     * @OA\Post(
     *     path="/org-funded-allocations",
     *     operationId="createOrgFundedAllocation",
     *     tags={"Org Funded Allocations"},
     *     summary="Create a new organization funded allocation",
     *     description="Creates a new organization funded allocation",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"grant_id", "department_id", "position_id"},
     *
     *             @OA\Property(property="grant_id", type="integer", description="ID of the grant"),
     *             @OA\Property(property="department_id", type="integer", description="ID of the department"),
     *             @OA\Property(property="position_id", type="integer", description="ID of the position"),
     *             @OA\Property(property="description", type="string", description="Optional description")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Allocation created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/OrgFundedAllocation")
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreOrgFundedAllocationRequest $request)
    {
        $validated = $request->validated();

        $allocation = OrgFundedAllocation::create([
            ...$validated,
            'created_by' => $request->user()->name ?? 'system',
        ]);

        // Load relationships for response
        $allocation->load(['grant', 'department', 'position']);

        return response()->json($allocation, Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     path="/org-funded-allocations/{id}",
     *     operationId="getOrgFundedAllocation",
     *     tags={"Org Funded Allocations"},
     *     summary="Get organization funded allocation by ID",
     *     description="Returns a single organization funded allocation with related data",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the organization funded allocation",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/OrgFundedAllocation")
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Allocation not found")
     * )
     */
    public function show($id)
    {
        $allocation = OrgFundedAllocation::with(['grant', 'department', 'position'])->findOrFail($id);

        return response()->json($allocation);
    }

    /**
     * @OA\Put(
     *     path="/org-funded-allocations/{id}",
     *     operationId="updateOrgFundedAllocation",
     *     tags={"Org Funded Allocations"},
     *     summary="Update organization funded allocation",
     *     description="Updates an existing organization funded allocation",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the organization funded allocation",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="grant_id", type="integer", description="ID of the grant"),
     *             @OA\Property(property="department_id", type="integer", description="ID of the department"),
     *             @OA\Property(property="position_id", type="integer", description="ID of the position"),
     *             @OA\Property(property="description", type="string", description="Optional description")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Allocation updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/OrgFundedAllocation")
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Allocation not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateOrgFundedAllocationRequest $request, $id)
    {
        $allocation = OrgFundedAllocation::findOrFail($id);

        $validated = $request->validated();

        $allocation->update([
            ...$validated,
            'updated_by' => $request->user()->name ?? 'system',
        ]);

        // Load relationships for response
        $allocation->load(['grant', 'department', 'position']);

        return response()->json($allocation);
    }

    /**
     * @OA\Delete(
     *     path="/org-funded-allocations/{id}",
     *     operationId="deleteOrgFundedAllocation",
     *     tags={"Org Funded Allocations"},
     *     summary="Delete organization funded allocation",
     *     description="Deletes an organization funded allocation",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the organization funded allocation",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Allocation deleted successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Allocation not found")
     * )
     */
    public function destroy($id)
    {
        $allocation = OrgFundedAllocation::findOrFail($id);
        $allocation->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
