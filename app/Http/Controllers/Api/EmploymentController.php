<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employment;
use App\Models\GrantItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


/**
 * @OA\Tag(
 *     name="Employments",
 *     description="API Endpoints for managing employee employment records"
 * )
 */

class EmploymentController extends Controller
{
    /**
     * Display a listing of employments.
     *
     * @OA\Get(
     *     path="/employments",
     *     summary="Get all employment records",
     *     description="Returns a list of all employment records",
     *     operationId="getEmployments",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Employment")),
     *             @OA\Property(property="message", type="string", example="Employments retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        try {
            $employments = Employment::all();

            return response()->json([
                'success' => true,
                'data'    => $employments,
                'message' => 'Employments retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created employment record.
     *
     * @OA\Post(
     *     path="/employments",
     *     summary="Create a new employment record",
     *     description="Creates a new employment record with the provided data",
     *     operationId="storeEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "employment_type_id", "start_date", "position_id", "department_id", "work_location_id", "position_salary", "grant_items_id"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="employment_type_id", type="integer", example=1),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-15"),
     *             @OA\Property(property="probation_end_date", type="string", format="date", example="2023-04-15", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-01-15", nullable=true),
     *             @OA\Property(property="position_id", type="integer", example=1),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="work_location_id", type="integer", example=1),
     *             @OA\Property(property="position_salary", type="number", format="float", example=50000),
     *             @OA\Property(property="probation_salary", type="number", format="float", example=45000, nullable=true),
     *             @OA\Property(property="supervisor_id", type="integer", example=2, nullable=true),
     *             @OA\Property(property="employee_tax", type="number", format="float", example=7, nullable=true),
     *             @OA\Property(property="fte", type="number", format="float", example=1.0, nullable=true),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="health_welfare", type="boolean", example=true),
     *             @OA\Property(property="pvd", type="boolean", example=false),
     *             @OA\Property(property="saving_fund", type="boolean", example=false),
     *             @OA\Property(property="social_security_id", type="string", example="SSN12345", nullable=true),
     *             @OA\Property(property="grant_items_id", type="integer", example=1, description="Reference to the grant item for position limit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Employment"),
     *             @OA\Property(property="message", type="string", example="Employment created successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error or position full")
     * )
     */
    public function store(Request $request)
    {
        // Validate the incoming request data.
        $validator = Validator::make($request->all(), [
            'employee_id'         => 'required|exists:employees,id',
            'employment_type_id'  => 'required|exists:employment_types,id',
            'start_date'          => 'required|date',
            'probation_end_date'  => 'nullable|date',
            'end_date'            => 'nullable|date',
            'position_id'         => 'required|exists:positions,id',
            'department_id'       => 'required|exists:departments,id',
            'work_location_id'    => 'required|exists:work_locations,id',
            'position_salary'     => 'required|numeric',
            'probation_salary'    => 'nullable|numeric',
            'supervisor_id'       => 'nullable|exists:employees,id',
            'employee_tax'        => 'nullable|numeric',
            'fte'                 => 'nullable|numeric',
            'active'              => 'boolean',
            'health_welfare'      => 'boolean',
            'pvd'                 => 'boolean',
            'saving_fund'         => 'boolean',
            'social_security_id'  => 'nullable|string',
            'grant_items_id'      => 'required|exists:grant_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Check if the grant position is already full.
        try {
            $grantItem = GrantItem::findOrFail($request->grant_items_id);

            // Count active employments: active if end_date is null or greater than today.
            $activeCount = Employment::where('grant_items_id', $grantItem->id)
                ->where(function ($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>', Carbon::now());
                })
                ->count();

            // Compare with the maximum allowed (cast if necessary).
            if ($activeCount >= (int) $grantItem->grant_position_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create employment: the position is already full.'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking position limit: ' . $e->getMessage()
            ], 500);
        }

        // Create the employment record.
        try {
            $data = $request->all();
            $data['created_by'] = Auth::user()->name ?? 'System';

            $employment = Employment::create($data);

            return response()->json([
                'success' => true,
                'data'    => $employment,
                'message' => 'Employment created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified employment record.
     *
     * @OA\Get(
     *     path="/employments/{id}",
     *     summary="Get employment record by ID",
     *     description="Returns a specific employment record by ID",
     *     operationId="getEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Employment"),
     *             @OA\Property(property="message", type="string", example="Employment retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Employment not found")
     * )
     */
    public function show($id)
    {
        try {
            $employment = Employment::findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => $employment,
                'message' => 'Employment retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found'
            ], 404);
        }
    }

    /**
     * Update the specified employment record.
     *
     * @OA\Put(
     *     path="/employments/{id}",
     *     summary="Update an existing employment record",
     *     description="Updates an employment record with the provided data",
     *     operationId="updateEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to update",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="employment_type_id", type="integer", example=1),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-15"),
     *             @OA\Property(property="probation_end_date", type="string", format="date", example="2023-04-15", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-01-15", nullable=true),
     *             @OA\Property(property="position_id", type="integer", example=1),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="work_location_id", type="integer", example=1),
     *             @OA\Property(property="position_salary", type="number", format="float", example=50000),
     *             @OA\Property(property="probation_salary", type="number", format="float", example=45000, nullable=true),
     *             @OA\Property(property="supervisor_id", type="integer", example=2, nullable=true),
     *             @OA\Property(property="employee_tax", type="number", format="float", example=7, nullable=true),
     *             @OA\Property(property="fte", type="number", format="float", example=1.0, nullable=true),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="health_welfare", type="boolean", example=true),
     *             @OA\Property(property="pvd", type="boolean", example=false),
     *             @OA\Property(property="saving_fund", type="boolean", example=false),
     *             @OA\Property(property="social_security_id", type="string", example="SSN12345", nullable=true),
     *             @OA\Property(property="grant_items_id", type="integer", example=1, description="Reference to the grant item")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employment updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Employment"),
     *             @OA\Property(property="message", type="string", example="Employment updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Employment not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id'         => 'exists:employees,id',
            'employment_type_id'  => 'exists:employment_types,id',
            'start_date'          => 'date',
            'probation_end_date'  => 'nullable|date',
            'end_date'            => 'nullable|date',
            'position_id'         => 'exists:positions,id',
            'department_id'       => 'exists:departments,id',
            'work_location_id'    => 'exists:work_locations,id',
            'position_salary'     => 'numeric',
            'probation_salary'    => 'nullable|numeric',
            'supervisor_id'       => 'nullable|exists:employees,id',
            'employee_tax'        => 'nullable|numeric',
            'fte'                 => 'nullable|numeric',
            'active'              => 'boolean',
            'health_welfare'      => 'boolean',
            'pvd'                 => 'boolean',
            'saving_fund'         => 'boolean',
            'social_security_id'  => 'nullable|string',
            'grant_items_id'      => 'exists:grant_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $employment = Employment::findOrFail($id);
            $data = $request->all();
            $data['updated_by'] = Auth::user()->name ?? 'System';

            // If grant_items_id is being updated, check the position limit.
            if (isset($data['grant_items_id']) && $data['grant_items_id'] != $employment->grant_items_id) {
                $grantItem = GrantItem::findOrFail($data['grant_items_id']);
                $activeCount = Employment::where('grant_items_id', $grantItem->id)
                    ->where(function ($query) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>', Carbon::now());
                    })
                    ->count();

                if ($activeCount >= (int) $grantItem->grant_position_number) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot update employment: the position is already full.'
                    ], 422);
                }
            }

            $employment->update($data);

            return response()->json([
                'success' => true,
                'data'    => $employment,
                'message' => 'Employment updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified employment record.
     *
     * @OA\Delete(
     *     path="/employments/{id}",
     *     summary="Delete an employment record",
     *     description="Deletes an employment record by ID",
     *     operationId="deleteEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employment deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employment deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Employment not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $employment = Employment::findOrFail($id);
            $employment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employment deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employment: ' . $e->getMessage()
            ], 500);
        }
    }
}
