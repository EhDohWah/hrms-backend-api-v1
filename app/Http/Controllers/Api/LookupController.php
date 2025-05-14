<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lookup;

/**
 * @OA\Tag(
 *     name="Lookups",
 *     description="API Endpoints for system lookup values"
 * )
 */
class LookupController extends Controller
{
    /**
     * Get all lookups organized by category
     *
     * @OA\Get(
     *     path="/lookups",
     *     summary="Get all lookup values organized by category",
     *     description="Returns all system lookup values grouped by their respective categories",
     *     operationId="getLookups",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="gender", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of gender options"),
     *             @OA\Property(property="subsidiary", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of subsidiary options"),
     *             @OA\Property(property="employee_status", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of employee status options"),
     *             @OA\Property(property="nationality", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of nationality options"),
     *             @OA\Property(property="religion", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of religion options"),
     *             @OA\Property(property="marital_status", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of marital status options"),
     *             @OA\Property(property="site", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of site options"),
     *             @OA\Property(property="user_status", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of user status options"),
     *             @OA\Property(property="interview_mode", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of interview mode options"),
     *             @OA\Property(property="interview_status", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of interview status options"),
     *             @OA\Property(property="identification_types", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of identification type options"),
     *             @OA\Property(property="employment_type", type="array", @OA\Items(ref="#/components/schemas/Lookup"), description="List of employment type options")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $lookupTypes = [
            'gender', 'subsidiary', 'employee_status', 'nationality',
            'religion', 'marital_status', 'site', 'user_status',
            'interview_mode', 'interview_status', 'identification_types',
            'employment_type', 'employee_language', 'employee_education', 'employee_initial_en', 'employee_initial_th'
        ];

        $result = [];

        foreach ($lookupTypes as $type) {
            $result[$type] = Lookup::getByType($type);
        }

        return response()->json($result);
    }

    /**
     * Store a new lookup value
     *
     * @OA\Post(
     *     path="/lookups",
     *     summary="Create a new lookup value",
     *     description="Stores a new lookup value in the system",
     *     operationId="storeLookup",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "value"},
     *             @OA\Property(property="type", type="string", example="gender", description="Lookup type"),
     *             @OA\Property(property="value", type="string", example="Non-binary", description="Display value")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lookup created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lookup created successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Lookup")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'value' => 'required|string|max:255',
        ]);

        try {
            $lookup = new Lookup();
            $lookup->type = $validated['type'];
            $lookup->value = $validated['value'];
            $lookup->created_by = auth()->user() ? auth()->user()->username : null;
            $lookup->save();

            return response()->json([
                'success' => true,
                'message' => 'Lookup created successfully',
                'data' => $lookup
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating lookup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing lookup value
     *
     * @OA\Put(
     *     path="/lookups/{id}",
     *     summary="Update a lookup value",
     *     description="Updates an existing lookup value in the system",
     *     operationId="updateLookup",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Lookup ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", example="gender", description="Lookup type"),
     *             @OA\Property(property="value", type="string", example="Updated Value", description="Display value")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lookup updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lookup updated successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Lookup")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lookup not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:255',
        ]);

        try {
            $lookup = Lookup::findOrFail($id);

            if (isset($validated['type'])) {
                $lookup->type = $validated['type'];
            }

            if (isset($validated['value'])) {
                $lookup->value = $validated['value'];
            }

            $lookup->updated_by = auth()->user() ? auth()->user()->username : null;
            $lookup->save();

            return response()->json([
                'success' => true,
                'message' => 'Lookup updated successfully',
                'data' => $lookup
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating lookup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a lookup value
     *
     * @OA\Delete(
     *     path="/lookups/{id}",
     *     summary="Delete a lookup value",
     *     description="Removes a lookup value from the system",
     *     operationId="deleteLookup",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Lookup ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lookup deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lookup deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lookup not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $lookup = Lookup::findOrFail($id);
            $lookup->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lookup deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting lookup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific lookup value
     *
     * @OA\Get(
     *     path="/lookups/{id}",
     *     summary="Get a specific lookup value",
     *     description="Returns details for a specific lookup value",
     *     operationId="showLookup",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Lookup ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Lookup")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lookup not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $lookup = Lookup::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $lookup
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lookup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get lookup values by type
     *
     * @OA\Get(
     *     path="/lookups/type/{type}",
     *     summary="Get lookup values by type",
     *     description="Returns all lookup values for a specific type",
     *     operationId="getLookupsByType",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Lookup type (e.g., gender, nationality)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Lookup")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No lookups found for this type"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @param string $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByType($type)
    {
        try {
            $lookups = Lookup::getByType($type);

            if ($lookups->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No lookups found for this type'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $lookups
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lookups',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
