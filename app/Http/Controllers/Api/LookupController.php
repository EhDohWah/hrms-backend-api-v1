<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use Illuminate\Http\Request;

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
     *     path="/lookups/lists",
     *     summary="Get all lookup values organized by category",
     *     description="Returns all system lookup values grouped by their respective categories",
     *     operationId="getLookupLists",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             description="Object containing lookup values grouped by type",
     *             additionalProperties=true,
     *             example={
     *                 "gender": {{"id": 1, "type": "gender", "value": "Male"}, {"id": 2, "type": "gender", "value": "Female"}},
     *                 "nationality": {{"id": 3, "type": "nationality", "value": "Thai"}, {"id": 4, "type": "nationality", "value": "American"}}
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLookupLists()
    {
        try {
            $result = Lookup::getAllLookups();

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lookup lists',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all lookups with pagination and filtering
     *
     * @OA\Get(
     *     path="/lookups",
     *     summary="Get lookup values with pagination and filtering",
     *     description="Returns lookup values with pagination, filtering by type, and search capabilities",
     *     operationId="getLookups",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_type",
     *         in="query",
     *         description="Filter by lookup type",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in lookup values",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"type", "value", "created_at", "updated_at"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="grouped",
     *         in="query",
     *         description="Return data grouped by type (legacy mode)",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
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
     *             @OA\Property(property="message", type="string", example="Lookups retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Lookup")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="has_more_pages", type="boolean")
     *             ),
     *             @OA\Property(property="filters", type="object",
     *                 @OA\Property(property="applied_filters", type="object"),
     *                 @OA\Property(property="available_types", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_type' => 'string|nullable',
                'search' => 'string|nullable|max:255',
                'sort_by' => 'string|nullable|in:type,value,created_at,updated_at',
                'sort_order' => 'string|nullable|in:asc,desc',
                'grouped' => 'boolean', // For backward compatibility
            ]);

            // If grouped is requested, return the old format
            if ($validated['grouped'] ?? false) {
                $result = Lookup::getAllLookups();

                return response()->json($result);
            }

            // Determine pagination parameters
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $sortBy = $validated['sort_by'] ?? 'type';
            $sortOrder = $validated['sort_order'] ?? 'asc';

            // Build query
            $query = Lookup::query();

            // Apply type filter if provided
            if (! empty($validated['filter_type'])) {
                $types = array_map('trim', explode(',', $validated['filter_type']));
                $query->whereIn('type', $types);
            }

            // Apply search if provided
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('type', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('value', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Execute pagination
            $lookups = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_type'])) {
                $appliedFilters['type'] = explode(',', $validated['filter_type']);
            }
            if (! empty($validated['search'])) {
                $appliedFilters['search'] = $validated['search'];
            }

            // Get available types for filtering
            $availableTypes = Lookup::getAllTypes();

            return response()->json([
                'success' => true,
                'message' => 'Lookups retrieved successfully',
                'data' => $lookups->items(),
                'pagination' => [
                    'current_page' => $lookups->currentPage(),
                    'per_page' => $lookups->perPage(),
                    'total' => $lookups->total(),
                    'last_page' => $lookups->lastPage(),
                    'from' => $lookups->firstItem(),
                    'to' => $lookups->lastItem(),
                    'has_more_pages' => $lookups->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                    'available_types' => $availableTypes,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lookups',
                'error' => $e->getMessage(),
            ], 500);
        }
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"type", "value"},
     *
     *             @OA\Property(property="type", type="string", example="gender", description="Lookup type"),
     *             @OA\Property(property="value", type="string", example="Non-binary", description="Display value")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Lookup created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lookup created successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Lookup")
     *         )
     *     ),
     *
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'value' => 'required|string|max:255',
        ]);

        try {
            $lookup = new Lookup;
            $lookup->type = $validated['type'];
            $lookup->value = $validated['value'];
            $lookup->created_by = auth()->user() ? auth()->user()->username : null;
            $lookup->save();

            return response()->json([
                'success' => true,
                'message' => 'Lookup created successfully',
                'data' => $lookup,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating lookup',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Lookup ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="type", type="string", example="gender", description="Lookup type"),
     *             @OA\Property(property="value", type="string", example="Updated Value", description="Display value")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Lookup updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lookup updated successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Lookup")
     *         )
     *     ),
     *
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
     * @param  int  $id
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
                'data' => $lookup,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating lookup',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Lookup ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Lookup deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lookup deleted successfully")
     *         )
     *     ),
     *
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
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $lookup = Lookup::findOrFail($id);
            $lookup->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lookup deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting lookup',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Lookup ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Lookup")
     *     ),
     *
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
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $lookup = Lookup::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $lookup,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lookup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search lookups with advanced filtering
     *
     * @OA\Get(
     *     path="/lookups/search",
     *     summary="Search lookup values with advanced filtering",
     *     description="Search lookup values with more flexible search criteria and filtering options",
     *     operationId="searchLookups",
     *     tags={"Lookups"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for type or value",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="types",
     *         in="query",
     *         description="Comma-separated list of lookup types to search in",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="value",
     *         in="query",
     *         description="Search specifically in values",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=50)
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"type", "value", "created_at", "updated_at"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"})
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
     *             @OA\Property(property="message", type="string", example="Search completed successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Lookup")),
     *             @OA\Property(property="pagination", type="object"),
     *             @OA\Property(property="search_info", type="object",
     *                 @OA\Property(property="search_term", type="string"),
     *                 @OA\Property(property="searched_types", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="total_found", type="integer")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No results found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'types' => 'nullable|string|max:500',
                'value' => 'nullable|string|max:255',
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:50',
                'sort_by' => 'string|nullable|in:type,value,created_at,updated_at',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // At least one search parameter is required
            if (empty($validated['search']) && empty($validated['types']) && empty($validated['value'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one search parameter (search, types, or value) is required.',
                    'errors' => ['search' => ['At least one search parameter must be provided.']],
                ], 422);
            }

            // Determine pagination parameters
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $sortBy = $validated['sort_by'] ?? 'type';
            $sortOrder = $validated['sort_order'] ?? 'asc';

            // Build search query
            $query = Lookup::query();

            // Apply type filter if provided
            $searchedTypes = [];
            if (! empty($validated['types'])) {
                $types = array_map('trim', explode(',', $validated['types']));
                $searchedTypes = $types;
                $query->whereIn('type', $types);
            }

            // Apply general search term
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('type', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('value', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Apply specific value search
            if (! empty($validated['value'])) {
                $valueTerm = trim($validated['value']);
                $query->where('value', 'LIKE', "%{$valueTerm}%");
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Execute pagination
            $lookups = $query->paginate($perPage, ['*'], 'page', $page);

            // Check if any records were found
            if ($lookups->isEmpty()) {
                $searchTerm = $validated['search'] ?? $validated['value'] ?? 'specified criteria';

                return response()->json([
                    'success' => false,
                    'message' => "No lookup records found for search: {$searchTerm}",
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => null,
                        'to' => null,
                        'has_more_pages' => false,
                    ],
                    'search_info' => [
                        'search_term' => $validated['search'] ?? null,
                        'searched_types' => $searchedTypes,
                        'total_found' => 0,
                    ],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'data' => $lookups->items(),
                'pagination' => [
                    'current_page' => $lookups->currentPage(),
                    'per_page' => $lookups->perPage(),
                    'total' => $lookups->total(),
                    'last_page' => $lookups->lastPage(),
                    'from' => $lookups->firstItem(),
                    'to' => $lookups->lastItem(),
                    'has_more_pages' => $lookups->hasMorePages(),
                ],
                'search_info' => [
                    'search_term' => $validated['search'] ?? null,
                    'searched_types' => $searchedTypes,
                    'total_found' => $lookups->total(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing search',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all available lookup types
     *
     * @OA\Get(
     *     path="/lookups/types",
     *     summary="Get all available lookup types",
     *     description="Returns a list of all available lookup types in the system",
     *     operationId="getLookupTypes",
     *     tags={"Lookups"},
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
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"), description="List of lookup types")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTypes()
    {
        try {
            $types = Lookup::getAllTypes();

            return response()->json([
                'success' => true,
                'data' => $types,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lookup types',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Lookup type (e.g., gender, nationality)",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/Lookup")
     *         )
     *     ),
     *
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
     * @param  string  $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByType($type)
    {
        try {
            // Validate that the type exists
            if (! Lookup::typeExists($type)) {
                return response()->json([
                    'success' => false,
                    'message' => "Lookup type '{$type}' does not exist",
                    'available_types' => Lookup::getAllTypes(),
                ], 404);
            }

            $lookups = Lookup::getByType($type);

            if ($lookups->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No lookup values found for type '{$type}'",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $lookups,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lookups',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
