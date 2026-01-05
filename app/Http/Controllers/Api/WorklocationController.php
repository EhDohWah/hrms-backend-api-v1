<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Work Locations",
 *     description="API Endpoints for managing work locations"
 * )
 */
class WorklocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/worklocations",
     *     summary="Get all work locations",
     *     description="Returns a list of all work locations",
     *     operationId="getWorkLocations",
     *     tags={"Work Locations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Site"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *     )
     * )
     */
    public function index()
    {
        $worklocations = Site::all();

        return response()->json([
            'status' => 'success',
            'data' => $worklocations,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/worklocations",
     *     summary="Create a new work location",
     *     description="Creates a new work location record",
     *     operationId="storeWorkLocation",
     *     tags={"Work Locations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "type"},
     *
     *             @OA\Property(property="name", type="string", example="MKT"),
     *             @OA\Property(property="type", type="string", example="Site")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Work location created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Site"),
     *             @OA\Property(property="message", type="string", example="Work location created successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $worklocation = Site::create([
            'name' => $request->name,
            'type' => $request->type,
            'created_by' => Auth::user()->name,
            'updated_by' => Auth::user()->name,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $worklocation,
            'message' => 'Work location created successfully',
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/worklocations/{id}",
     *     summary="Get a specific work location",
     *     description="Returns a specific work location by ID",
     *     operationId="getWorkLocation",
     *     tags={"Work Locations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the work location",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Site")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Work location not found"
     *     )
     * )
     */
    public function show($id)
    {
        $worklocation = Site::find($id);

        if (! $worklocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work location not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $worklocation,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/worklocations/{id}",
     *     summary="Update a work location",
     *     description="Updates an existing work location",
     *     operationId="updateWorkLocation",
     *     tags={"Work Locations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the work location",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="WPA"),
     *             @OA\Property(property="type", type="string", example="Site")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Work location updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Site"),
     *             @OA\Property(property="message", type="string", example="Work location updated successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Work location not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $worklocation = Site::find($id);

        if (! $worklocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work location not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'type' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $worklocation->update([
            'name' => $request->name ?? $worklocation->name,
            'type' => $request->type ?? $worklocation->type,
            'updated_by' => Auth::user()->name,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $worklocation,
            'message' => 'Work location updated successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/worklocations/{id}",
     *     summary="Delete a work location",
     *     description="Deletes a work location",
     *     operationId="deleteWorkLocation",
     *     tags={"Work Locations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the work location",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Work location deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Work location deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Work location not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $worklocation = Site::find($id);

        if (! $worklocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work location not found',
            ], 404);
        }

        $worklocation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Work location deleted successfully',
        ]);
    }
}
