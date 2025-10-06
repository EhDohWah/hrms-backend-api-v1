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
 *     name="Employee Trainings",
 *     description="API Endpoints for managing employee training records"
 * )
 */
class EmployeeTrainingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee-trainings",
     *     summary="List all employee training records with advanced filtering and pagination",
     *     description="Returns a paginated list of employee training records with comprehensive filtering, sorting capabilities and statistics",
     *     operationId="indexEmployeeTrainings",
     *     tags={"Employee Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_training_id",
     *         in="query",
     *         description="Filter by training ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_employee_id",
     *         in="query",
     *         description="Filter by employee ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_status",
     *         in="query",
     *         description="Filter by training status (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Completed,In Progress,Pending")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_training_title",
     *         in="query",
     *         description="Filter by training title (partial match)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Leadership")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_organizer",
     *         in="query",
     *         description="Filter by training organizer (partial match)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="HR Department")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"created_at", "training_title", "status", "employee_name", "start_date", "end_date"}, example="created_at")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
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
     *             @OA\Property(property="message", type="string", example="Employee training records retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="employee_id", type="integer", example=1),
     *                     @OA\Property(property="training_id", type="integer", example=1),
     *                     @OA\Property(property="status", type="string", example="Completed"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                         @OA\Property(property="first_name_en", type="string", example="John"),
     *                         @OA\Property(property="last_name_en", type="string", example="Doe"),
     *                         @OA\Property(property="subsidiary", type="string", example="SMRU")
     *                     ),
     *                     @OA\Property(
     *                         property="training",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Leadership Training"),
     *                         @OA\Property(property="organizer", type="string", example="HR Department"),
     *                         @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *                         @OA\Property(property="end_date", type="string", format="date", example="2023-01-05")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(property="applied_filters", type="object",
     *                     @OA\Property(property="training_id", type="integer", example=1),
     *                     @OA\Property(property="employee_id", type="integer", example=1),
     *                     @OA\Property(property="status", type="array", @OA\Items(type="string"), example={"Completed"}),
     *                     @OA\Property(property="training_title", type="string", example="Leadership"),
     *                     @OA\Property(property="organizer", type="string", example="HR Department")
     *                 )
     *             )
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
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve employee training records"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_training_id' => 'integer|nullable',
                'filter_employee_id' => 'integer|nullable',
                'filter_status' => 'string|nullable',
                'filter_training_title' => 'string|nullable',
                'filter_organizer' => 'string|nullable',
                'sort_by' => 'string|nullable|in:created_at,training_title,status,employee_name,start_date,end_date',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query with relationships
            $query = EmployeeTraining::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
                'training:id,title,organizer,start_date,end_date',
            ]);

            // Apply filters if provided
            if (! empty($validated['filter_training_id'])) {
                $query->where('training_id', $validated['filter_training_id']);
            }

            if (! empty($validated['filter_employee_id'])) {
                $query->where('employee_id', $validated['filter_employee_id']);
            }

            if (! empty($validated['filter_status'])) {
                $statuses = explode(',', $validated['filter_status']);
                $query->whereIn('status', $statuses);
            }

            if (! empty($validated['filter_training_title'])) {
                $query->whereHas('training', function ($q) use ($validated) {
                    $q->where('title', 'like', '%'.$validated['filter_training_title'].'%');
                });
            }

            if (! empty($validated['filter_organizer'])) {
                $query->whereHas('training', function ($q) use ($validated) {
                    $q->where('organizer', 'like', '%'.$validated['filter_organizer'].'%');
                });
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            switch ($sortBy) {
                case 'training_title':
                    $query->join('trainings', 'employee_trainings.training_id', '=', 'trainings.id')
                        ->orderBy('trainings.title', $sortOrder)
                        ->select('employee_trainings.*');
                    break;
                case 'employee_name':
                    $query->join('employees', 'employee_trainings.employee_id', '=', 'employees.id')
                        ->orderBy('employees.first_name_en', $sortOrder)
                        ->select('employee_trainings.*');
                    break;
                case 'start_date':
                case 'end_date':
                    $query->join('trainings', 'employee_trainings.training_id', '=', 'trainings.id')
                        ->orderBy("trainings.{$sortBy}", $sortOrder)
                        ->select('employee_trainings.*');
                    break;
                case 'status':
                    $query->orderBy('employee_trainings.status', $sortOrder);
                    break;
                default:
                    $query->orderBy('employee_trainings.created_at', $sortOrder);
                    break;
            }

            // Execute pagination
            $employeeTrainings = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_training_id'])) {
                $appliedFilters['training_id'] = $validated['filter_training_id'];
            }
            if (! empty($validated['filter_employee_id'])) {
                $appliedFilters['employee_id'] = $validated['filter_employee_id'];
            }
            if (! empty($validated['filter_status'])) {
                $appliedFilters['status'] = explode(',', $validated['filter_status']);
            }
            if (! empty($validated['filter_training_title'])) {
                $appliedFilters['training_title'] = $validated['filter_training_title'];
            }
            if (! empty($validated['filter_organizer'])) {
                $appliedFilters['organizer'] = $validated['filter_organizer'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee training records retrieved successfully',
                'data' => $employeeTrainings->items(),
                'pagination' => [
                    'current_page' => $employeeTrainings->currentPage(),
                    'per_page' => $employeeTrainings->perPage(),
                    'total' => $employeeTrainings->total(),
                    'last_page' => $employeeTrainings->lastPage(),
                    'from' => $employeeTrainings->firstItem(),
                    'to' => $employeeTrainings->lastItem(),
                    'has_more_pages' => $employeeTrainings->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
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

    /**
     * @OA\Get(
     *     path="/employee-trainings/employee/{employee_id}/summary",
     *     summary="Get individual employee training summary report",
     *     description="Returns a comprehensive training summary for a specific employee including all training records, attendance details, and statistics",
     *     operationId="getEmployeeTrainingSummary",
     *     tags={"Employee Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="path",
     *         description="Employee ID to get training summary for",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter trainings from this date (YYYY-MM-DD)",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2023-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter trainings to this date (YYYY-MM-DD)",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2023-12-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee training summary retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee training summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                     @OA\Property(property="first_name_en", type="string", example="John"),
     *                     @OA\Property(property="last_name_en", type="string", example="Doe"),
     *                     @OA\Property(property="subsidiary", type="string", example="SMRU"),
     *                     @OA\Property(property="site", type="string", example="Main Office"),
     *                     @OA\Property(property="department", type="string", example="IT Department")
     *                 ),
     *                 @OA\Property(
     *                     property="training_summary",
     *                     type="object",
     *                     @OA\Property(property="total_trainings", type="integer", example=5),
     *                     @OA\Property(property="completed_trainings", type="integer", example=3),
     *                     @OA\Property(property="in_progress_trainings", type="integer", example=1),
     *                     @OA\Property(property="pending_trainings", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="trainings",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="training_title", type="string", example="Leadership Training"),
     *                         @OA\Property(property="organizer_details", type="string", example="HR Department"),
     *                         @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *                         @OA\Property(property="end_date", type="string", format="date", example="2023-01-05"),
     *                         @OA\Property(property="status", type="string", example="Completed"),
     *                         @OA\Property(property="attendance_date", type="string", format="date-time", example="2023-01-01T09:00:00Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee not found")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve employee training summary"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getEmployeeTrainingSummary(Request $request, $employee_id)
    {
        try {
            // Validate query parameters
            $validated = $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            // Find the employee with basic information
            $employee = \App\Models\Employee::with([
                'employment.workLocation',
                'employment.departmentPosition.department',
            ])
                ->select('id', 'staff_id', 'first_name_en', 'last_name_en', 'subsidiary')
                ->findOrFail($employee_id);

            // Build training query
            $trainingQuery = EmployeeTraining::with([
                'training:id,title,organizer,start_date,end_date',
            ])
                ->where('employee_id', $employee_id);

            // Apply date filters if provided
            if (! empty($validated['date_from'])) {
                $trainingQuery->whereHas('training', function ($q) use ($validated) {
                    $q->where('start_date', '>=', $validated['date_from']);
                });
            }

            if (! empty($validated['date_to'])) {
                $trainingQuery->whereHas('training', function ($q) use ($validated) {
                    $q->where('end_date', '<=', $validated['date_to']);
                });
            }

            // Get trainings ordered by start date
            $employeeTrainings = $trainingQuery->orderByDesc('created_at')->get();

            // Calculate statistics
            $totalTrainings = $employeeTrainings->count();
            $completedTrainings = $employeeTrainings->where('status', 'Completed')->count();
            $inProgressTrainings = $employeeTrainings->where('status', 'In Progress')->count();
            $pendingTrainings = $employeeTrainings->where('status', 'Pending')->count();

            // Format training data
            $trainings = $employeeTrainings->map(function ($empTraining) {
                return [
                    'id' => $empTraining->id,
                    'training_title' => $empTraining->training->title ?? 'N/A',
                    'organizer_details' => $empTraining->training->organizer ?? 'N/A',
                    'start_date' => $empTraining->training->start_date ?? null,
                    'end_date' => $empTraining->training->end_date ?? null,
                    'status' => $empTraining->status,
                    'attendance_date' => $empTraining->created_at,
                ];
            });

            // Get additional employee details
            $site = $employee->employment->first()?->workLocation?->name ?? 'N/A';
            $department = $employee->employment->first()?->departmentPosition?->department?->name ?? 'N/A';

            return response()->json([
                'success' => true,
                'message' => 'Employee training summary retrieved successfully',
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'staff_id' => $employee->staff_id,
                        'first_name_en' => $employee->first_name_en,
                        'last_name_en' => $employee->last_name_en,
                        'subsidiary' => $employee->subsidiary,
                        'site' => $site,
                        'department' => $department,
                    ],
                    'training_summary' => [
                        'total_trainings' => $totalTrainings,
                        'completed_trainings' => $completedTrainings,
                        'in_progress_trainings' => $inProgressTrainings,
                        'pending_trainings' => $pendingTrainings,
                    ],
                    'trainings' => $trainings,
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee training summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-trainings/training/{training_id}/attendance",
     *     summary="Get training attendance list report",
     *     description="Returns a list of all employees enrolled in a specific training with their attendance status and details",
     *     operationId="getTrainingAttendanceList",
     *     tags={"Employee Trainings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="training_id",
     *         in="path",
     *         description="Training ID to get attendance list for",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="status_filter",
     *         in="query",
     *         description="Filter by attendance status",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"Completed", "In Progress", "Pending"}, example="Completed")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Training attendance list retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Training attendance list retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="training",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Leadership Training"),
     *                     @OA\Property(property="organizer", type="string", example="HR Department"),
     *                     @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2023-01-05")
     *                 ),
     *                 @OA\Property(
     *                     property="attendance_summary",
     *                     type="object",
     *                     @OA\Property(property="total_enrolled", type="integer", example=10),
     *                     @OA\Property(property="completed", type="integer", example=7),
     *                     @OA\Property(property="in_progress", type="integer", example=2),
     *                     @OA\Property(property="pending", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="attendees",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="staff_name", type="string", example="John Doe"),
     *                         @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                         @OA\Property(property="organizer_details", type="string", example="HR Department"),
     *                         @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *                         @OA\Property(property="end_date", type="string", format="date", example="2023-01-05"),
     *                         @OA\Property(property="status", type="string", example="Completed"),
     *                         @OA\Property(property="enrollment_date", type="string", format="date-time", example="2023-01-01T09:00:00Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Training not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Training not found")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve training attendance list"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getTrainingAttendanceList(Request $request, $training_id)
    {
        try {
            // Validate query parameters
            $validated = $request->validate([
                'status_filter' => 'nullable|string|in:Completed,In Progress,Pending',
            ]);

            // Find the training
            $training = Training::select('id', 'title', 'organizer', 'start_date', 'end_date')
                ->findOrFail($training_id);

            // Build attendance query
            $attendanceQuery = EmployeeTraining::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
            ])
                ->where('training_id', $training_id);

            // Apply status filter if provided
            if (! empty($validated['status_filter'])) {
                $attendanceQuery->where('status', $validated['status_filter']);
            }

            // Get attendance records ordered by employee name
            $attendanceRecords = $attendanceQuery->get();

            // Calculate statistics
            $totalEnrolled = $attendanceRecords->count();
            $completed = $attendanceRecords->where('status', 'Completed')->count();
            $inProgress = $attendanceRecords->where('status', 'In Progress')->count();
            $pending = $attendanceRecords->where('status', 'Pending')->count();

            // Format attendee data to match the screenshot structure
            $attendees = $attendanceRecords->map(function ($record) use ($training) {
                $fullName = $record->employee->first_name_en;
                if ($record->employee->last_name_en && $record->employee->last_name_en !== '-') {
                    $fullName .= ' '.$record->employee->last_name_en;
                }

                return [
                    'id' => $record->id,
                    'staff_name' => $fullName,
                    'staff_id' => $record->employee->staff_id,
                    'organizer_details' => $training->organizer,
                    'start_date' => $training->start_date,
                    'end_date' => $training->end_date,
                    'status' => $record->status,
                    'enrollment_date' => $record->created_at,
                ];
            })->sortBy('staff_name')->values();

            return response()->json([
                'success' => true,
                'message' => 'Training attendance list retrieved successfully',
                'data' => [
                    'training' => [
                        'id' => $training->id,
                        'title' => $training->title,
                        'organizer' => $training->organizer,
                        'start_date' => $training->start_date,
                        'end_date' => $training->end_date,
                    ],
                    'attendance_summary' => [
                        'total_enrolled' => $totalEnrolled,
                        'completed' => $completed,
                        'in_progress' => $inProgress,
                        'pending' => $pending,
                    ],
                    'attendees' => $attendees,
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve training attendance list',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
