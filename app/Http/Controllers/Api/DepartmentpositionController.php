<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepartmentPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Department Positions",
 *     description="API Endpoints for Department Position management"
 * )
 */
class DepartmentpositionController extends Controller
{
    /**
     * Get all department positions
     *
     * @OA\Get(
     *     path="/department-positions",
     *     summary="Get all department positions",
     *     description="Returns a list of all department positions",
     *     operationId="getDepartmentPositions",
     *     tags={"Department Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department positions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/DepartmentPosition")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $departmentPositions = DepartmentPosition::all();

        return response()->json([
            'success' => true,
            'message' => 'Department positions retrieved successfully',
            'data' => $departmentPositions,
        ]);
    }

    /**
     * Store a new department position
     *
     * @OA\Post(
     *     path="/department-positions",
     *     summary="Create a new department position",
     *     description="Creates a new department position and returns it",
     *     operationId="storeDepartmentPosition",
     *     tags={"Department Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"department", "position"},
     *
     *             @OA\Property(property="department", type="string", example="Human Resources"),
     *             @OA\Property(property="position", type="string", example="HR Manager"),
     *             @OA\Property(property="report_to", type="string", example="CEO", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Department position created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department position created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/DepartmentPosition")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'report_to' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $departmentPosition = new DepartmentPosition;
        $departmentPosition->department = $request->department;
        $departmentPosition->position = $request->position;
        $departmentPosition->report_to = $request->report_to;
        $departmentPosition->created_by = Auth::id() ?? 'system';
        $departmentPosition->save();

        return response()->json([
            'success' => true,
            'message' => 'Department position created successfully',
            'data' => $departmentPosition,
        ], 201);
    }

    /**
     * Get a specific department position
     *
     * @OA\Get(
     *     path="/department-positions/{id}",
     *     summary="Get a specific department position",
     *     description="Returns a specific department position by ID",
     *     operationId="getDepartmentPosition",
     *     tags={"Department Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department position to return",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department position retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/DepartmentPosition")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department position not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Department position not found")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $departmentPosition = DepartmentPosition::find($id);

        if (! $departmentPosition) {
            return response()->json([
                'success' => false,
                'message' => 'Department position not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Department position retrieved successfully',
            'data' => $departmentPosition,
        ]);
    }

    /**
     * Update a department position
     *
     * @OA\Put(
     *     path="/department-positions/{id}",
     *     summary="Update a department position",
     *     description="Updates a department position and returns it",
     *     operationId="updateDepartmentPosition",
     *     tags={"Department Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department position to update",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="department", type="string", example="Human Resources"),
     *             @OA\Property(property="position", type="string", example="HR Director"),
     *             @OA\Property(property="report_to", type="string", example="CEO", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Department position updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department position updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/DepartmentPosition")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department position not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Department position not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $departmentPosition = DepartmentPosition::find($id);

        if (! $departmentPosition) {
            return response()->json([
                'success' => false,
                'message' => 'Department position not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'report_to' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $departmentPosition->department = $request->department;
        $departmentPosition->position = $request->position;
        $departmentPosition->report_to = $request->report_to;
        $departmentPosition->updated_by = Auth::id() ?? 'system';
        $departmentPosition->save();

        return response()->json([
            'success' => true,
            'message' => 'Department position updated successfully',
            'data' => $departmentPosition,
        ]);
    }

    /**
     * Delete a department position
     *
     * @OA\Delete(
     *     path="/department-positions/{id}",
     *     summary="Delete a department position",
     *     description="Deletes a department position",
     *     operationId="deleteDepartmentPosition",
     *     tags={"Department Positions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of department position to delete",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Department position deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Department position deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Department position not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Department position not found")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $departmentPosition = DepartmentPosition::find($id);

        if (! $departmentPosition) {
            return response()->json([
                'success' => false,
                'message' => 'Department position not found',
            ], 404);
        }

        $departmentPosition->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department position deleted successfully',
        ]);
    }
}
