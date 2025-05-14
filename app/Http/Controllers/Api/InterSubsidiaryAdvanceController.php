<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInterSubsidiaryAdvanceRequest;
use App\Http\Requests\UpdateInterSubsidiaryAdvanceRequest;
use App\Http\Resources\InterSubsidiaryAdvanceResource;
use App\Models\InterSubsidiaryAdvance;

class InterSubsidiaryAdvanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/inter-subsidiary-advances",
     *     summary="Get all inter-subsidiary advances",
     *     tags={"Inter-Subsidiary Advances"},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/InterSubsidiaryAdvanceResource"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $items = InterSubsidiaryAdvance::with('viaGrant')->get();
        return response()->json([
            'success' => true,
            'data'    => InterSubsidiaryAdvanceResource::collection($items)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/inter-subsidiary-advances",
     *     summary="Create a new inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreInterSubsidiaryAdvanceRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advance recorded."),
     *             @OA\Property(property="data", ref="#/components/schemas/InterSubsidiaryAdvanceResource")
     *         )
     *     )
     * )
     */
    public function store(StoreInterSubsidiaryAdvanceRequest $request)
    {
        $data = $request->validated() + [
          'created_by' => auth()->user()->username ?? null
        ];
        $item = InterSubsidiaryAdvance::create($data);
        return response()->json([
            'success' => true,
            'message' => 'Advance recorded.',
            'data'    => new InterSubsidiaryAdvanceResource($item->load('viaGrant'))
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/inter-subsidiary-advances/{id}",
     *     summary="Get a specific inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the advance",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/InterSubsidiaryAdvanceResource")
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
        $item = InterSubsidiaryAdvance::with('viaGrant')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data'    => new InterSubsidiaryAdvanceResource($item)
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/inter-subsidiary-advances/{id}",
     *     summary="Update an inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the advance",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateInterSubsidiaryAdvanceRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advance updated."),
     *             @OA\Property(property="data", ref="#/components/schemas/InterSubsidiaryAdvanceResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function update(UpdateInterSubsidiaryAdvanceRequest $request, $id)
    {
        $item = InterSubsidiaryAdvance::findOrFail($id);
        $item->update($request->validated() + [
          'updated_by' => auth()->user()->username ?? null
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Advance updated.',
            'data'    => new InterSubsidiaryAdvanceResource($item->load('viaGrant'))
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/inter-subsidiary-advances/{id}",
     *     summary="Delete an inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the advance",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advance deleted.")
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
        $item = InterSubsidiaryAdvance::findOrFail($id);
        $item->delete();
        return response()->json([
            'success' => true,
            'message' => 'Advance deleted.'
        ]);
    }
}
