<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Work Locations', description: 'API Endpoints for managing work locations')]
class WorklocationController extends Controller
{
    /**
     * Get all work locations
     */
    #[OA\Get(
        path: '/worklocations',
        summary: 'Get all work locations',
        description: 'Returns a list of all work locations',
        operationId: 'getWorkLocations',
        security: [['bearerAuth' => []]],
        tags: ['Work Locations']
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function index()
    {
        $worklocations = Site::all();

        return response()->json([
            'status' => 'success',
            'data' => $worklocations,
        ]);
    }

    /**
     * Create a new work location
     */
    #[OA\Post(
        path: '/worklocations',
        summary: 'Create a new work location',
        description: 'Creates a new work location record',
        operationId: 'storeWorkLocation',
        security: [['bearerAuth' => []]],
        tags: ['Work Locations']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'type'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'type', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Work location created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
     * Get a specific work location
     */
    #[OA\Get(
        path: '/worklocations/{id}',
        summary: 'Get a specific work location',
        description: 'Returns a specific work location by ID',
        operationId: 'getWorkLocation',
        security: [['bearerAuth' => []]],
        tags: ['Work Locations']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Work location not found')]
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
     * Update a work location
     */
    #[OA\Put(
        path: '/worklocations/{id}',
        summary: 'Update a work location',
        description: 'Updates an existing work location',
        operationId: 'updateWorkLocation',
        security: [['bearerAuth' => []]],
        tags: ['Work Locations']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'type', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Work location updated successfully')]
    #[OA\Response(response: 404, description: 'Work location not found')]
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
     * Delete a work location
     */
    #[OA\Delete(
        path: '/worklocations/{id}',
        summary: 'Delete a work location',
        description: 'Deletes a work location',
        operationId: 'deleteWorkLocation',
        security: [['bearerAuth' => []]],
        tags: ['Work Locations']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Work location deleted successfully')]
    #[OA\Response(response: 404, description: 'Work location not found')]
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
