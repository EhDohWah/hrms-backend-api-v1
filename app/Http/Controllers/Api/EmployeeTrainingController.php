<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTraining;
use App\Models\Training;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Trainings",
 *     description="API Endpoints for managing trainings"
 * )
 * @OA\Tag(
 *     name="Employee Trainings",
 *     description="API Endpoints for managing employee training records"
 * )
 */
class EmployeeTrainingController extends Controller
{
    // Trainings Section

    /**
     * @OA\Get(
     *     path="/trainings",
     *     summary="List all trainings",
     *     description="Returns a list of all trainings",
     *     operationId="listTrainings",
     *     tags={"Trainings"},
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
     *             @OA\Property(property="message", type="string", example="Trainings retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Training"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function listTrainings()
    {
        try {
            $trainings = Training::all();

            return response()->json([
                'success' => true,
                'message' => 'Trainings retrieved successfully',
                'data' => $trainings,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trainings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/trainings",
     *     summary="Create a new training",
     *     description="Creates a new training record",
     *     operationId="storeTraining",
     *     tags={"Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title", "organizer", "start_date", "end_date"},
     *
     *             @OA\Property(property="title", type="string", example="Leadership Training"),
     *             @OA\Property(property="organizer", type="string", example="HR Department"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-01-05"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Training created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Training created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Training")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function storeTraining(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:200',
                'organizer' => 'required|string|max:100',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            $training = Training::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Training created successfully',
                'data' => $training,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create training',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/trainings/{id}",
     *     summary="Get a specific training",
     *     description="Returns a specific training by ID",
     *     operationId="showTraining",
     *     tags={"Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the training to retrieve",
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
     *             @OA\Property(property="message", type="string", example="Training retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Training")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Training not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function showTraining($id)
    {
        try {
            $training = Training::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Training retrieved successfully',
                'data' => $training,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve training',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/trainings/{id}",
     *     summary="Update a training",
     *     description="Updates an existing training record",
     *     operationId="updateTraining",
     *     tags={"Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the training to update",
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
     *             @OA\Property(property="title", type="string", example="Updated Leadership Training"),
     *             @OA\Property(property="organizer", type="string", example="HR Department"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-01-05"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Training updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Training updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Training")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Training not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateTraining(Request $request, $id)
    {
        try {
            $training = Training::findOrFail($id);

            $validatedData = $request->validate([
                'title' => 'sometimes|required|string|max:200',
                'organizer' => 'sometimes|required|string|max:100',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            $training->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Training updated successfully',
                'data' => $training,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update training',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/trainings/{id}",
     *     summary="Delete a training",
     *     description="Deletes a training record",
     *     operationId="destroyTraining",
     *     tags={"Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the training to delete",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Training deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Training deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Training not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroyTraining($id)
    {
        try {
            $training = Training::findOrFail($id);
            $training->delete();

            return response()->json([
                'success' => true,
                'message' => 'Training deleted successfully',
                'data' => null,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete training',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Employee Trainings Section

    /**
     * @OA\Get(
     *     path="/employee-trainings",
     *     summary="List all employee training records",
     *     description="Returns a list of all employee training records with training details",
     *     operationId="indexEmployeeTrainings",
     *     tags={"Employee Trainings"},
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
     *             @OA\Property(property="message", type="string", example="Employee training records retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeTraining"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        try {
            $employeeTrainings = EmployeeTraining::with('training')->get();

            return response()->json([
                'success' => true,
                'message' => 'Employee training records retrieved successfully',
                'data' => $employeeTrainings,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee training records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee-trainings",
     *     summary="Create a new employee training record",
     *     description="Creates a new employee training record",
     *     operationId="storeEmployeeTraining",
     *     tags={"Employee Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "training_id", "status"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="training_id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", example="Completed"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Employee training record created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee training record created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeTraining")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'employee_id' => 'required|integer',
                'training_id' => 'required|integer|exists:trainings,id',
                'status' => 'required|string|max:50',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            $employeeTraining = EmployeeTraining::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Employee training record created successfully',
                'data' => $employeeTraining,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee training record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-trainings/{id}",
     *     summary="Get a specific employee training record",
     *     description="Returns a specific employee training record by ID with training details",
     *     operationId="showEmployeeTraining",
     *     tags={"Employee Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee training record to retrieve",
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
     *             @OA\Property(property="message", type="string", example="Employee training record retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeTraining")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employee training record not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show($id)
    {
        try {
            $employeeTraining = EmployeeTraining::with('training')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Employee training record retrieved successfully',
                'data' => $employeeTraining,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee training record not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee training record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/employee-trainings/{id}",
     *     summary="Update an employee training record",
     *     description="Updates an existing employee training record",
     *     operationId="updateEmployeeTraining",
     *     tags={"Employee Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee training record to update",
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
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="training_id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", example="In Progress"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee training record updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee training record updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeTraining")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employee training record not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $employeeTraining = EmployeeTraining::findOrFail($id);

            $validatedData = $request->validate([
                'employee_id' => 'sometimes|required|integer',
                'training_id' => 'sometimes|required|integer|exists:trainings,id',
                'status' => 'sometimes|required|string|max:50',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            $employeeTraining->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Employee training record updated successfully',
                'data' => $employeeTraining,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee training record not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee training record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/employee-trainings/{id}",
     *     summary="Delete an employee training record",
     *     description="Deletes an employee training record",
     *     operationId="destroyEmployeeTraining",
     *     tags={"Employee Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee training record to delete",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee training record deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee training record deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employee training record not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($id)
    {
        try {
            $employeeTraining = EmployeeTraining::findOrFail($id);
            $employeeTraining->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee training record deleted successfully',
                'data' => null,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee training record not found',
                'error' => 'Resource with ID '.$id.' not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee training record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
