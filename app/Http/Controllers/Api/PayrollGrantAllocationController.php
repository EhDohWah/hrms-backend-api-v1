<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollGrantAllocationRequest;
use App\Http\Requests\UpdatePayrollGrantAllocationRequest;
use App\Http\Resources\PayrollGrantAllocationResource;
use App\Models\PayrollGrantAllocation;

class PayrollGrantAllocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/payroll-grant-allocations",
     *     summary="Get all payroll grant allocations",
     *     tags={"Payroll Grant Allocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PayrollGrantAllocationResource"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $items = PayrollGrantAllocation::with('employeeGrantAllocation')->get();
        return response()->json([
            'success' => true,
            'data'    => PayrollGrantAllocationResource::collection($items)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/payroll-grant-allocations",
     *     summary="Create a new payroll grant allocation",
     *     tags={"Payroll Grant Allocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StorePayrollGrantAllocationRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Allocation created."),
     *             @OA\Property(property="data", ref="#/components/schemas/PayrollGrantAllocationResource")
     *         )
     *     )
     * )
     */
    public function store(StorePayrollGrantAllocationRequest $request)
    {
        $data = $request->validated() + [
          'created_by' => auth()->user()->username ?? null
        ];
        $item = PayrollGrantAllocation::create($data);
        return response()->json([
            'success' => true,
            'message' => 'Allocation created.',
            'data'    => new PayrollGrantAllocationResource($item->load('employeeGrantAllocation'))
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/payroll-grant-allocations/{id}",
     *     summary="Get a specific payroll grant allocation",
     *     tags={"Payroll Grant Allocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PayrollGrantAllocationResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function show($id)
    {
        $item = PayrollGrantAllocation::with('employeeGrantAllocation')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data'    => new PayrollGrantAllocationResource($item)
        ]);
    }

    /**
     * @OA\Put(
     *     path="/payroll-grant-allocations/{id}",
     *     summary="Update a payroll grant allocation",
     *     tags={"Payroll Grant Allocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdatePayrollGrantAllocationRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Allocation updated."),
     *             @OA\Property(property="data", ref="#/components/schemas/PayrollGrantAllocationResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function update(UpdatePayrollGrantAllocationRequest $request, $id)
    {
        $item = PayrollGrantAllocation::findOrFail($id);
        $item->update($request->validated() + [
          'updated_by' => auth()->user()->username ?? null
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Allocation updated.',
            'data'    => new PayrollGrantAllocationResource($item->load('employeeGrantAllocation'))
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/payroll-grant-allocations/{id}",
     *     summary="Delete a payroll grant allocation",
     *     tags={"Payroll Grant Allocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Allocation deleted.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $item = PayrollGrantAllocation::findOrFail($id);
        $item->delete();
        return response()->json([
            'success' => true,
            'message' => 'Allocation deleted.'
        ]);
    }
}
