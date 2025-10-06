<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Trainings",
 *     description="API Endpoints for managing training programs"
 * )
 */
class TrainingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/trainings",
     *     summary="List all training programs",
     *     description="Returns a paginated list of all training programs with filtering and sorting capabilities",
     *     operationId="indexTrainings",
     *     tags={"Trainings"},
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
     *         name="filter_organizer",
     *         in="query",
     *         description="Filter by organizer (partial match)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="HR Department")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_title",
     *         in="query",
     *         description="Filter by training title (partial match)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Leadership")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"title", "organizer", "start_date", "end_date", "created_at"}, example="start_date")
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
     *             @OA\Property(property="message", type="string", example="Trainings retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Training")),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(property="applied_filters", type="object",
     *                     @OA\Property(property="organizer", type="string", example="HR Department"),
     *                     @OA\Property(property="title", type="string", example="Leadership")
     *                 )
     *             )
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
     *             @OA\Property(property="message", type="string", example="Failed to retrieve trainings"),
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
                'filter_organizer' => 'string|nullable',
                'filter_title' => 'string|nullable',
                'sort_by' => 'string|nullable|in:title,organizer,start_date,end_date,created_at',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query
            $query = Training::query();

            // Apply filters if provided
            if (! empty($validated['filter_organizer'])) {
                $query->where('organizer', 'like', '%'.$validated['filter_organizer'].'%');
            }

            if (! empty($validated['filter_title'])) {
                $query->where('title', 'like', '%'.$validated['filter_title'].'%');
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Execute pagination
            $trainings = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_organizer'])) {
                $appliedFilters['organizer'] = $validated['filter_organizer'];
            }
            if (! empty($validated['filter_title'])) {
                $appliedFilters['title'] = $validated['filter_title'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Trainings retrieved successfully',
                'data' => $trainings->items(),
                'pagination' => [
                    'current_page' => $trainings->currentPage(),
                    'per_page' => $trainings->perPage(),
                    'total' => $trainings->total(),
                    'last_page' => $trainings->lastPage(),
                    'from' => $trainings->firstItem(),
                    'to' => $trainings->lastItem(),
                    'has_more_pages' => $trainings->hasMorePages(),
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
                'message' => 'Failed to retrieve trainings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/trainings",
     *     summary="Create a new training program",
     *     description="Creates a new training program record",
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
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:200',
                'organizer' => 'required|string|max:100',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            // Add audit fields if not provided
            $validatedData['created_by'] = $validatedData['created_by'] ?? auth()->user()->name ?? 'system';
            $validatedData['updated_by'] = $validatedData['updated_by'] ?? auth()->user()->name ?? 'system';

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
     *     summary="Get a specific training program",
     *     description="Returns a specific training program by ID",
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
    public function show($id)
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
     *     summary="Update a training program",
     *     description="Updates an existing training program record",
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
    public function update(Request $request, $id)
    {
        try {
            $training = Training::findOrFail($id);

            $validatedData = $request->validate([
                'title' => 'sometimes|required|string|max:200',
                'organizer' => 'sometimes|required|string|max:100',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'created_by' => 'nullable|string|max:100',
                'updated_by' => 'nullable|string|max:100',
            ]);

            // Always update the updated_by field
            $validatedData['updated_by'] = auth()->user()->name ?? 'system';

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
     *     summary="Delete a training program",
     *     description="Deletes a training program record",
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
    public function destroy($id)
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
}
