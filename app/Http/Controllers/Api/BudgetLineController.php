<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetLineResource;
use App\Models\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Budget Lines",
 *     description="Budget line management endpoints"
 * )
 */
class BudgetLineController extends Controller
{
    /**
     * @OA\Get(
     *     path="/budget-lines",
     *     tags={"Budget Lines"},
     *     summary="List all budget lines with pagination and sorting",
     *     description="Retrieve a paginated list of budget lines with sorting capabilities. Supports pagination parameters (page, per_page) and sorting by budget_line_code.",
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
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field (only budget_line_code allowed)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"budget_line_code"}, example="budget_line_code")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Budget lines retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget lines retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="budget_line_code", type="string", example="BL001"),
     *                     @OA\Property(property="description", type="string", example="Description"),
     *                     @OA\Property(property="created_by", type="string", example="Admin User"),
     *                     @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
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
     *                 @OA\Property(property="applied_filters", type="array", @OA\Items(type="string"), example={})
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid parameters provided",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"per_page": {"The per page must be between 1 and 100."}})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve budget lines"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Validate incoming parameters
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'sort_by' => 'string|nullable|in:budget_line_code',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // Set defaults
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 10;
            $sortBy = $validated['sort_by'] ?? 'budget_line_code';
            $sortOrder = $validated['sort_order'] ?? 'asc';

            // Build optimized query pipeline
            $budgetLines = BudgetLine::orderBy($sortBy, $sortOrder)
                ->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array (empty for now since no filters implemented)
            $appliedFilters = [];

            return response()->json([
                'success' => true,
                'message' => 'Budget lines retrieved successfully',
                'data' => BudgetLineResource::collection($budgetLines->items()),
                'pagination' => [
                    'current_page' => $budgetLines->currentPage(),
                    'per_page' => $budgetLines->perPage(),
                    'total' => $budgetLines->total(),
                    'last_page' => $budgetLines->lastPage(),
                    'from' => $budgetLines->firstItem(),
                    'to' => $budgetLines->lastItem(),
                    'has_more_pages' => $budgetLines->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve budget lines',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/budget-lines/by-code/{code}",
     *     tags={"Budget Lines"},
     *     summary="Get a budget line by code",
     *     description="Retrieve a specific budget line by its budget line code with associated position slots",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Budget line code",
     *
     *         @OA\Schema(type="string", example="RD001")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Budget line retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget line retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_line_code", type="string", example="RD001"),
     *                 @OA\Property(property="description", type="string", example="Marine Research - Scientific Personnel"),
     *                 @OA\Property(property="created_by", type="string", example="system"),
     *                 @OA\Property(property="updated_by", type="string", example="system"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="position_slots",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="grant_item_id", type="integer", example=1),
     *                         @OA\Property(property="slot_number", type="string", example="1"),
     *                         @OA\Property(property="budget_line_id", type="integer", example=1),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Budget line not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Budget line not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve budget line"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getBudgetLineByCode(string $code): \Illuminate\Http\JsonResponse
    {
        try {
            $budgetLine = BudgetLine::with([
                'positionSlots' => function ($query) {
                    $query->select('id', 'grant_item_id', 'slot_number', 'budget_line_id', 'created_at', 'updated_at');
                },
            ])
                ->where('budget_line_code', $code)
                ->first(['id', 'budget_line_code', 'description', 'created_by', 'updated_by', 'created_at', 'updated_at']);

            if (! $budgetLine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Budget line not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Budget line retrieved successfully',
                'data' => new BudgetLineResource($budgetLine),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve budget line',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/budget-lines",
     *     tags={"Budget Lines"},
     *     summary="Create a new budget line",
     *     description="Create a new budget line with a unique budget line code",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="budget_line_code", type="string", example="BL001", description="Unique budget line code")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Budget line created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_line_code", type="string", example="BL001"),
     *                 @OA\Property(property="description", type="string", example="Description"),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 @OA\Property(
     *                     property="budget_line_code",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The budget line code has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'budget_line_code' => 'required|string|max:255|unique:budget_lines,budget_line_code',
            'description' => 'required|string|max:255',
        ]);
        $line = BudgetLine::create([
            'budget_line_code' => $validated['budget_line_code'],
            'description' => $validated['description'],
            'created_by' => $request->user()?->name ?? 'system',
        ]);

        return response()->json(['success' => true, 'data' => $line], 201);
    }

    /**
     * @OA\Get(
     *     path="/budget-lines/{id}",
     *     tags={"Budget Lines"},
     *     summary="Show a single budget line",
     *     description="Retrieve details of a specific budget line by ID",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Budget line ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_line_code", type="string", example="BL001"),
     *                 @OA\Property(property="description", type="string", example="Description"),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Budget line not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\BudgetLine] 1")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $line = BudgetLine::findOrFail($id);

        return response()->json(['success' => true, 'data' => $line]);
    }

    /**
     * @OA\Put(
     *     path="/budget-lines/{id}",
     *     tags={"Budget Lines"},
     *     summary="Update a budget line",
     *     description="Update an existing budget line's budget line code",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Budget line ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="budget_line_code", type="string", example="BL001-UPDATED", description="Updated budget line code")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Budget line updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_line_code", type="string", example="BL001-UPDATED"),
     *                 @OA\Property(property="description", type="string", example="Description"),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Budget line not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\BudgetLine] 1")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 @OA\Property(
     *                     property="budget_line_code",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The budget line code has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $line = BudgetLine::findOrFail($id);
        $validated = $request->validate([
            'budget_line_code' => [
                'required', 'string', 'max:255',
                Rule::unique('budget_lines')->ignore($line->id),
            ],
            'description' => 'required|string|max:255',
        ]);
        $line->budget_line_code = $validated['budget_line_code'];
        $line->description = $validated['description'];
        $line->updated_by = $request->user()?->name ?? 'system';
        $line->save();

        return response()->json(['success' => true, 'data' => $line]);
    }

    /**
     * @OA\Delete(
     *     path="/budget-lines/{id}",
     *     tags={"Budget Lines"},
     *     summary="Delete a budget line",
     *     description="Delete a specific budget line by ID",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Budget line ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Budget line deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Budget line not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\BudgetLine] 1")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $line = BudgetLine::findOrFail($id);
        $line->delete();

        return response()->json(['success' => true]);
    }
}
