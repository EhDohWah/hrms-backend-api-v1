<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGrantItemRequest;
use App\Http\Requests\UpdateGrantItemRequest;
use App\Http\Resources\GrantItemResource;
use App\Http\Resources\GrantResource;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\Position;
use App\Models\User;
use App\Notifications\GrantActionNotification;
use App\Notifications\GrantItemActionNotification;
use App\Notifications\ImportedCompletedNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Grants",
 *     description="API Endpoints for managing grants"
 * )
 */
class GrantController extends Controller
{
    /**
     * @OA\Get(
     *     path="/grants/by-code/{code}",
     *     operationId="getGrantByCode",
     *     summary="Get a specific grant with its items by grant code",
     *     description="Returns a specific grant and its associated items by grant code.",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Grant code",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant with items retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="GR-2023-001"),
     *                 @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *                 @OA\Property(property="organization", type="string", example="Main Branch"),
     *                 @OA\Property(property="description", type="string", example="Funding for health initiatives", nullable=true),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true),
     *                 @OA\Property(
     *                     property="grant_items",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="grant_id", type="integer", example=1),
     *                         @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                         @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                         @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                         @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                         @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
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
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getGrantByCode($code)
    {
        try {
            $grant = Grant::with([
                'grantItems' => function ($query) {
                    $query->select('id', 'grant_id', 'grant_position', 'grant_salary', 'grant_benefit', 'grant_level_of_effort', 'grant_position_number');
                },
            ])
                ->where('code', $code)
                ->first(['id', 'code', 'name', 'organization', 'description', 'end_date']);

            if (! $grant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grant not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant retrieved successfully',
                'data' => $grant,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/grants/by-id/{id}",
     *     operationId="getGrantById",
     *     summary="Get a specific grant with its items by grant ID",
     *     description="Returns a specific grant and its associated items with position slots by grant ID. Budget line codes are included in grant items.",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant with items retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="GR-2023-001"),
     *                 @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *                 @OA\Property(property="organization", type="string", example="Main Campus"),
     *                 @OA\Property(property="description", type="string", example="Funding for health initiatives", nullable=true),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true),
     *                 @OA\Property(
     *                     property="grant_items",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="grant_id", type="integer", example=1),
     *                         @OA\Property(property="grant_code", type="string", example="GR-2023-001"),
     *                         @OA\Property(property="grant_name", type="string", example="Health Initiative Grant"),
     *                         @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                         @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                         @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                         @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                         @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position"),
     *                         @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding"),
     *                         @OA\Property(
     *                             property="position_slots",
     *                             type="array",
     *
     *                             @OA\Items(
     *
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="grant_item_id", type="integer", example=1),
     *                                 @OA\Property(property="slot_number", type="integer", example=1, description="Slot number")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-06-25T15:38:59Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-06-25T15:38:59Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access grants"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $grant = Grant::with([
                'grantItems' => function ($q) {
                    $q->select(
                        'id',
                        'grant_id',
                        'grant_position',
                        'grant_salary',
                        'grant_benefit',
                        'grant_level_of_effort',
                        'grant_position_number',
                        'budgetline_code',
                        'created_at',
                        'updated_at'
                    )->withCount(['employeeFundingAllocations as active_allocations_count' => function ($query) {
                        $query->where('status', 'active');
                    }]);
                },
            ])
                ->select(
                    'id', 'code', 'name', 'organization', 'description', 'end_date', 'created_at', 'updated_at'
                )
                ->where('id', $id)
                ->first();

            if (! $grant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grant not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant retrieved successfully',
                'data' => new GrantResource($grant),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/grants/upload",
     *     summary="Upload grant data from Excel file",
     *     description="Upload an Excel file with multiple sheets containing grant header and item records. Each sheet represents one grant. Duplicate grant items (same position + budget line code within a grant) will be rejected with detailed error messages.",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file to upload (xlsx, xls, csv)"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant data imported successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant data import completed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="processed_grants", type="integer", example=2),
     *                 @OA\Property(property="processed_items", type="integer", example=15),
     *                 @OA\Property(
     *                     property="warnings",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="Sheet 'Grant1' row 8: Duplicate grant item - Position 'Project Manager' with budget line code 'BL001' already exists for this grant"
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="skipped_grants", type="array", @OA\Items(type="string"))
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
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Import failed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to import grant data"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function upload(Request $request)
    {
        try {
            $this->validateFile($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $file = $request->file('file');

        try {
            // Use GrantsImport class for processing
            $importId = uniqid('grant_import_');
            $userId = auth()->id();

            $grantsImport = new \App\Imports\GrantsImport($importId, $userId);

            // Load spreadsheet and process each sheet using GrantSheetImport
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheets = $spreadsheet->getAllSheets();

            $sheetImport = new \App\Imports\GrantSheetImport($grantsImport);

            foreach ($sheets as $sheet) {
                $sheetImport->processSheet($sheet);
            }

            // Get results from the import
            $processedGrants = $grantsImport->getProcessedGrants();
            $processedItems = $grantsImport->getProcessedItems();
            $errors = $grantsImport->getErrors();
            $skippedGrants = $grantsImport->getSkippedGrants();

            $responseData = [
                'processed_grants' => $processedGrants,
                'processed_items' => $processedItems,
            ];

            if (! empty($errors)) {
                $responseData['errors'] = $errors;
            }

            if (! empty($skippedGrants)) {
                $responseData['skipped_grants'] = $skippedGrants;
            }

            $message = 'Grant data import completed';
            if (! empty($errors)) {
                $message = 'Grant data import completed with errors';
            } elseif (! empty($skippedGrants)) {
                $message = 'Grant data import completed with skipped grants';
            }

            // Send completion notification to user
            $grantsImport->sendCompletionNotification();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $responseData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import grant data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/grants",
     *     operationId="getGrants",
     *     summary="List all grants with pagination and filtering",
     *     description="Returns a paginated list of grants with their associated items. Supports filtering by organization and sorting by name/code with standard Laravel pagination parameters (page, per_page).",
     *     tags={"Grants"},
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
     *         name="filter_organization",
     *         in="query",
     *         description="Filter grants by organization (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="SMRU,BHF")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field (name or code)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"name", "code"}, example="name")
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
     *         description="List of grants with items retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grants retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="organization", type="string", example="Main Campus"),
     *                     @OA\Property(property="description", type="string", example="Funding for health initiatives", nullable=true),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true),
     *                     @OA\Property(
     *                         property="grant_items",
     *                         type="array",
     *
     *                         @OA\Items(
     *
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="grant_id", type="integer", example=1),
     *                             @OA\Property(property="grant_code", type="string", example="GR-2023-001"),
     *                             @OA\Property(property="grant_name", type="string", example="Health Initiative Grant"),
     *                             @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                             @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                             @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                             @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                             @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position"),
     *                             @OA\Property(
     *                                 property="position_slots",
     *                                 type="array",
     *
     *                                 @OA\Items(
     *
     *                                     @OA\Property(property="id", type="integer", example=1),
     *                                     @OA\Property(property="grant_item_id", type="integer", example=1),
     *                                     @OA\Property(property="slot_number", type="string", example="SLOT-001"),
     *                                     @OA\Property(property="budget_line_id", type="integer", example=1)
     *                                 )
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-06-25T15:38:59Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-06-25T15:38:59Z")
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
     *                 @OA\Property(property="applied_filters", type="object",
     *                     @OA\Property(property="organization", type="array", @OA\Items(type="string"), example={"SMRU"})
     *                 )
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
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object", example={"per_page": {"The per page must be between 1 and 100."}})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access grants"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grants"),
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
                'filter_organization' => 'string|nullable',
                'sort_by' => 'string|nullable|in:name,code',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query using model scopes for optimization
            $query = Grant::forPagination()
                ->withItemsCount()
                ->withOptimizedItems();

            // Apply organization filter if provided
            if (! empty($validated['filter_organization'])) {
                $query->byOrganization($validated['filter_organization']);
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Validate sort field and apply sorting
            if (in_array($sortBy, ['name', 'code'])) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Execute pagination
            $grants = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_organization'])) {
                $appliedFilters['organization'] = explode(',', $validated['filter_organization']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grants retrieved successfully',
                'data' => GrantResource::collection($grants->items()),
                'pagination' => [
                    'current_page' => $grants->currentPage(),
                    'per_page' => $grants->perPage(),
                    'total' => $grants->total(),
                    'last_page' => $grants->lastPage(),
                    'from' => $grants->firstItem(),
                    'to' => $grants->lastItem(),
                    'has_more_pages' => $grants->hasMorePages(),
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grants',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/grants/items",
     *     operationId="getGrantItems",
     *     summary="List all grant items",
     *     description="Returns a list of all grant items across all grants",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant items retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant items retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="grant_id", type="integer", example=1),
     *                     @OA\Property(property="grant_code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="grant_name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                     @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                     @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                     @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                     @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position"),
     *                     @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=10)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access grant items"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant items"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getGrantItems()
    {
        try {
            $grantItems = GrantItem::with(['grant:id,code,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Grant items retrieved successfully',
                'data' => GrantItemResource::collection($grantItems),
                'count' => $grantItems->count(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/grants/items/{id}",
     *     operationId="getGrantItem",
     *     summary="Get a specific grant item by ID",
     *     description="Returns a specific grant item by ID with its associated grant details",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of grant item to return",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="grant_id", type="integer", example=1),
     *                 @OA\Property(property="grant", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="organization", type="string", example="SMRU"),
     *                     @OA\Property(property="description", type="string", example="Grant for health initiatives"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2023-12-31")
     *                 ),
     *                 @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                 @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                 @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                 @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                 @OA\Property(property="grant_position_number", type="integer", example=2),
     *                 @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant item"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getGrantItem($id)
    {
        try {
            $grantItem = GrantItem::with('grant')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Grant item retrieved successfully',
                'data' => $grantItem,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant item not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/grants/download-template",
     *     operationId="downloadGrantTemplate",
     *     summary="Download Grant Import Excel Template",
     *     description="Downloads an Excel template file with headers and validation rules for grant bulk import",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Excel template file download",
     *
     *         @OA\MediaType(
     *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     *
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed to generate template",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to generate template"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;

            // Remove default sheet and create first grant sheet
            $spreadsheet->removeSheetByIndex(0);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Grant Template');

            // Set column widths for better readability
            // New structure: A=Labels, B=Values, C-H=Validation instructions (merged)
            $sheet->getColumnDimension('A')->setWidth(20);
            $sheet->getColumnDimension('B')->setWidth(35);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(18);
            $sheet->getColumnDimension('F')->setWidth(15);
            $sheet->getColumnDimension('G')->setWidth(10);
            $sheet->getColumnDimension('H')->setWidth(10);

            // Label styling (Column A, rows 1-5) - Bold, light blue background, right-aligned
            $labelStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => '000000'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D6EAF8'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ];

            // Value styling (Column B, rows 1-5) - White background, left-aligned, with borders
            $valueStyle = [
                'font' => [
                    'size' => 11,
                    'color' => ['rgb' => '000000'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '333333'],
                    ],
                ],
            ];

            // Validation instruction styling (Column C-H merged, rows 1-5) - Italic, small font, yellow background
            $validationStyle = [
                'font' => [
                    'italic' => true,
                    'size' => 9,
                    'color' => ['rgb' => '666666'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF9E6'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ];

            // Column header styling for grant items
            $columnHeaderStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 10,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];

            // Spacer row styling
            $spacerStyle = [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0'],
                ],
            ];

            // Row 1: Grant Name - Label in A, Value in B, Validation in C-H (merged)
            $sheet->setCellValue('A1', 'Grant Name');
            $sheet->setCellValue('B1', '');
            $sheet->setCellValue('C1', 'REQUIRED - String - Min 3 chars, Max 255 chars');
            $sheet->mergeCells('C1:H1');
            $sheet->getStyle('A1')->applyFromArray($labelStyle);
            $sheet->getStyle('B1')->applyFromArray($valueStyle);
            $sheet->getStyle('C1:H1')->applyFromArray($validationStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Row 2: Grant Code - Label in A, Value in B, Validation in C-H (merged)
            $sheet->setCellValue('A2', 'Grant Code');
            $sheet->setCellValue('B2', '');
            $sheet->setCellValue('C2', 'REQUIRED - String - Max 50 chars - Must be unique - Alphanumeric, dots, dashes, underscores only');
            $sheet->mergeCells('C2:H2');
            $sheet->getStyle('A2')->applyFromArray($labelStyle);
            $sheet->getStyle('B2')->applyFromArray($valueStyle);
            $sheet->getStyle('C2:H2')->applyFromArray($validationStyle);
            $sheet->getRowDimension(2)->setRowHeight(25);

            // Row 3: Subsidiary/Organization - Label in A, Value in B (with dropdown), Validation in C-H (merged)
            $sheet->setCellValue('A3', 'Subsidiary');
            $sheet->setCellValue('B3', '');
            $sheet->setCellValue('C3', 'REQUIRED - Must be SMRU or BHF (use dropdown)');
            $sheet->mergeCells('C3:H3');
            $sheet->getStyle('A3')->applyFromArray($labelStyle);
            $sheet->getStyle('B3')->applyFromArray($valueStyle);
            $sheet->getStyle('C3:H3')->applyFromArray($validationStyle);
            $sheet->getRowDimension(3)->setRowHeight(25);

            // Add dropdown validation to B3 for Subsidiary field
            $subsidiaryValidation = $sheet->getCell('B3')->getDataValidation();
            $subsidiaryValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $subsidiaryValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $subsidiaryValidation->setAllowBlank(false);
            $subsidiaryValidation->setShowDropDown(true);
            $subsidiaryValidation->setShowInputMessage(true);
            $subsidiaryValidation->setShowErrorMessage(true);
            $subsidiaryValidation->setErrorTitle('Invalid Organization');
            $subsidiaryValidation->setError('Please select SMRU or BHF from the dropdown list.');
            $subsidiaryValidation->setPromptTitle('Select Organization');
            $subsidiaryValidation->setPrompt('Choose the organization: SMRU or BHF');
            $subsidiaryValidation->setFormula1('"SMRU,BHF"');

            // Row 4: End Date - Label in A, Value in B, Validation in C-H (merged)
            $sheet->setCellValue('A4', 'End Date');
            $sheet->setCellValue('B4', '');
            $sheet->setCellValue('C4', 'OPTIONAL - Format: YYYY-MM-DD');
            $sheet->mergeCells('C4:H4');
            $sheet->getStyle('A4')->applyFromArray($labelStyle);
            $sheet->getStyle('B4')->applyFromArray($valueStyle);
            $sheet->getStyle('C4:H4')->applyFromArray($validationStyle);
            $sheet->getRowDimension(4)->setRowHeight(25);

            // Row 5: Description - Label in A, Value in B, Validation in C-H (merged)
            $sheet->setCellValue('A5', 'Description');
            $sheet->setCellValue('B5', '');
            $sheet->setCellValue('C5', 'OPTIONAL - Max 1000 chars');
            $sheet->mergeCells('C5:H5');
            $sheet->getStyle('A5')->applyFromArray($labelStyle);
            $sheet->getStyle('B5')->applyFromArray($valueStyle);
            $sheet->getStyle('C5:H5')->applyFromArray($validationStyle);
            $sheet->getRowDimension(5)->setRowHeight(25);

            // Row 6: Empty spacer with gray background
            $sheet->getRowDimension(6)->setRowHeight(8);
            $sheet->getStyle('A6:H6')->applyFromArray($spacerStyle);

            // Row 7: Column Headers for Grant Items
            $sheet->setCellValue('A7', 'Budget Line Code');
            $sheet->setCellValue('B7', 'Position');
            $sheet->setCellValue('C7', 'Salary');
            $sheet->setCellValue('D7', 'Benefit');
            $sheet->setCellValue('E7', 'Level of Effort (%)');
            $sheet->setCellValue('F7', 'Position Number');
            $sheet->getStyle('A7:F7')->applyFromArray($columnHeaderStyle);
            $sheet->getRowDimension(7)->setRowHeight(25);

            // Row 8: Validation rules for each grant item column (reference row)
            $itemValidationStyle = [
                'font' => [
                    'italic' => true,
                    'size' => 8,
                    'color' => ['rgb' => '888888'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F5F5F5'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ];
            $sheet->setCellValue('A8', 'OPTIONAL (Max 50 chars)');
            $sheet->setCellValue('B8', 'REQUIRED (Min 2, Max 255 chars)');
            $sheet->setCellValue('C8', 'OPTIONAL (0 - 99,999,999.99)');
            $sheet->setCellValue('D8', 'OPTIONAL (0 - 99,999,999.99)');
            $sheet->setCellValue('E8', 'OPTIONAL (0-100 or 0-1)');
            $sheet->setCellValue('F8', 'OPTIONAL (Default: 1, Range: 1-1000)');
            $sheet->getStyle('A8:F8')->applyFromArray($itemValidationStyle);
            $sheet->getRowDimension(8)->setRowHeight(35);

            // Add borders to the grant items header area
            $sheet->getStyle('A7:F8')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Apply cell protection - Lock Column A rows 1-5 (read-only labels)
            $sheet->getProtection()->setSheet(true);
            $sheet->getProtection()->setPassword('grant_template');

            // Unlock value cells (B1-B5) for editing
            for ($row = 1; $row <= 5; $row++) {
                $sheet->getStyle('B'.$row)->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
            }

            // Unlock entire grant items area (rows 7+) for editing
            $sheet->getStyle('A7:F1000')->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);

            // Add instructions sheet
            $instructionsSheet = $spreadsheet->createSheet();
            $instructionsSheet->setTitle('Instructions');
            $instructionsSheet->getColumnDimension('A')->setWidth(80);

            $instructions = [
                ['GRANT IMPORT TEMPLATE - INSTRUCTIONS'],
                [''],
                ['IMPORTANT: Each sheet represents ONE grant with its grant items.'],
                [''],
                ['NEW TEMPLATE STRUCTURE:'],
                ['This template uses a separate column layout for easier data entry:'],
                ['- Column A: Field labels (read-only)'],
                ['- Column B: Your input values'],
                ['- Column C+: Validation instructions'],
                [''],
                ['FILE STRUCTURE:'],
                ['1. You can have multiple sheets in one Excel file'],
                ['2. Each sheet will create a separate grant'],
                ['3. Sheet name can be anything (it will not be imported)'],
                [''],
                ['SHEET STRUCTURE (Rows 1-6: Grant Information):'],
                ['Row 1: Column A shows "Grant Name", Column B is where you enter the grant name'],
                ['Row 2: Column A shows "Grant Code", Column B is where you enter the unique code'],
                ['Row 3: Column A shows "Subsidiary", Column B has a DROPDOWN - select SMRU or BHF'],
                ['Row 4: Column A shows "End Date", Column B is for date in YYYY-MM-DD format (optional)'],
                ['Row 5: Column A shows "Description", Column B is for grant description (optional)'],
                ['Row 6: Spacer row (do not modify)'],
                [''],
                ['SHEET STRUCTURE (Row 7+: Grant Items):'],
                ['Row 7: Column headers for grant items'],
                ['Row 8: Validation rules (reference row - you can keep or delete)'],
                ['Row 9+: Your grant item data - enter data starting from row 9'],
                [''],
                ['GRANT HEADER FIELD DETAILS:'],
                [''],
                ['Grant Name (Cell B1):'],
                ['- REQUIRED - Cannot be empty'],
                ['- Minimum 3 characters, Maximum 255 characters'],
                [''],
                ['Grant Code (Cell B2):'],
                ['- REQUIRED - Cannot be empty'],
                ['- Maximum 50 characters'],
                ['- Must be unique across all grants'],
                ['- Only alphanumeric characters, dots (.), dashes (-), underscores (_) allowed'],
                [''],
                ['Subsidiary/Organization (Cell B3):'],
                ['- REQUIRED - Must select from dropdown'],
                ['- Valid values: SMRU or BHF only'],
                ['- The system will suggest corrections for typos (e.g., "SMEU" will suggest "SMRU")'],
                [''],
                ['End Date (Cell B4):'],
                ['- OPTIONAL - Can be left empty'],
                ['- Format: YYYY-MM-DD (e.g., 2024-12-31)'],
                [''],
                ['Description (Cell B5):'],
                ['- OPTIONAL - Can be left empty'],
                ['- Maximum 1000 characters'],
                [''],
                ['GRANT ITEM COLUMN DETAILS:'],
                [''],
                ['A. Budget Line Code - OPTIONAL'],
                ['   - Can be empty for General Fund/Hub grants'],
                ['   - Maximum 50 characters'],
                ['   - Examples: 1.2.2.1, BL-001, A.B.C, CODE_123'],
                [''],
                ['B. Position - REQUIRED'],
                ['   - Minimum 2 characters, Maximum 255 characters'],
                ['   - Examples: Project Manager, Senior Researcher, Field Officer'],
                [''],
                ['C. Salary - OPTIONAL'],
                ['   - Numeric value, range: 0 to 99,999,999.99'],
                ['   - Examples: 75000, 75000.50'],
                [''],
                ['D. Benefit - OPTIONAL'],
                ['   - Numeric value, range: 0 to 99,999,999.99'],
                ['   - Examples: 15000, 15000.00'],
                [''],
                ['E. Level of Effort - OPTIONAL'],
                ['   - Accepts multiple formats: 75, 75%, or 0.75 (all mean 75%)'],
                ['   - Range: 0 to 100 (or 0 to 1 in decimal)'],
                [''],
                ['F. Position Number - OPTIONAL'],
                ['   - Default value: 1'],
                ['   - Range: 1 to 1000'],
                ['   - Must be a whole number'],
                [''],
                ['VALIDATION RULES:'],
                ['1. Grant Name must be at least 3 characters'],
                ['2. Grant Code must be unique and use only alphanumeric, dots, dashes, underscores'],
                ['3. Organization MUST be SMRU or BHF (typos will be detected with suggestions)'],
                ['4. For Project Grants: Position + Budget Line Code must be unique within each grant'],
                ['5. For General Fund: Budget Line Code can be empty, duplicate positions allowed'],
                ['6. Position field is always required for grant items'],
                [''],
                ['ERROR MESSAGES:'],
                ['The import system provides detailed error messages with cell references:'],
                ['- "Grant name is required (Cell B1)" - Missing grant name'],
                ['- "Invalid organization: \'SMEU\'. Did you mean \'SMRU\'? (Cell B3)" - Typo detected'],
                ['- "Sheet \'Grant ABC\' Row 9: Grant salary cannot be negative" - Invalid item data'],
                [''],
                ['DUPLICATE HANDLING:'],
                ['- If grant code exists: entire sheet is skipped, no data imported'],
                ['- If grant item exists (same position + budget code): item is skipped with error'],
                ['- General Fund items (empty budget code) can have duplicate positions'],
                [''],
                ['TRANSACTION SAFETY:'],
                ['- Each sheet is processed as an atomic transaction'],
                ['- If ANY validation fails, NO data from that sheet is saved'],
                ['- This ensures data integrity - no partial imports'],
                [''],
                ['EXAMPLE - Project Grant (using new template):'],
                ['Cell A1: "Grant Name"     Cell B1: "Health Initiative Grant"'],
                ['Cell A2: "Grant Code"     Cell B2: "GR-2024-001"'],
                ['Cell A3: "Subsidiary"     Cell B3: "SMRU" (selected from dropdown)'],
                ['Cell A4: "End Date"       Cell B4: "2024-12-31"'],
                ['Cell A5: "Description"    Cell B5: "Funding for health initiatives"'],
                ['Row 7: Budget Line Code | Position | Salary | Benefit | LOE | Position Number'],
                ['Row 9: 1.2.2.1 | Project Manager | 75000 | 15000 | 75 | 2'],
                ['Row 10: 1.2.1.2 | Senior Researcher | 60000 | 12000 | 100 | 3'],
                [''],
                ['EXAMPLE - General Fund (using new template):'],
                ['Cell A1: "Grant Name"     Cell B1: "General Fund"'],
                ['Cell A2: "Grant Code"     Cell B2: "S22001"'],
                ['Cell A3: "Subsidiary"     Cell B3: "BHF" (selected from dropdown)'],
                ['Cell A4: "End Date"       Cell B4: (empty)'],
                ['Cell A5: "Description"    Cell B5: "BHF hub grant"'],
                ['Row 7: Budget Line Code | Position | Salary | Benefit | LOE | Position Number'],
                ['Row 9: (empty) | Manager | 75000 | 15000 | 100 | 2'],
                ['Row 10: (empty) | Field Officer | 45000 | 9000 | 100 | 3'],
                ['Row 11: (empty) | Manager | 60000 | 12000 | 75 | 1  (duplicate position OK!)'],
                [''],
                ['TIPS:'],
                ['- Use the dropdown in Cell B3 to avoid organization typos'],
                ['- Data entry starts from Row 9 (Row 8 contains validation reference)'],
                ['- Test with a small file first (1-2 grants)'],
                ['- Keep a backup of your original data'],
                ['- Check the import notification for detailed results'],
                ['- Review any error messages carefully - they include cell/row references'],
                [''],
                ['FILE REQUIREMENTS:'],
                ['- File format: .xlsx, .xls, or .csv'],
                ['- Maximum file size: 10MB'],
                ['- Each sheet must have at least 7 rows'],
                [''],
                ['For more information, refer to the API documentation or contact your system administrator.'],
            ];

            $row = 1;
            foreach ($instructions as $instruction) {
                $instructionsSheet->setCellValue('A'.$row, $instruction[0]);
                if ($row === 1) {
                    $instructionsSheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
                }
                $row++;
            }

            $instructionsSheet->getStyle('A1:A'.$row)->getAlignment()->setWrapText(true);

            // Set active sheet to template
            $spreadsheet->setActiveSheetIndex(0);

            // Generate filename with timestamp
            $filename = 'grant_import_template_'.date('Y-m-d_His').'.xlsx';

            // Create writer and save to temporary file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'grant_template_');
            $writer->save($tempFile);

            // Return file download response with proper CORS headers
            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate uploaded file
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/grants",
     *     operationId="storeGrant",
     *     summary="Create a new grant",
     *     description="Store a new grant with the provided details",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"code", "name", "organization"},
     *
     *             @OA\Property(property="code", type="string", example="GR-2023-001"),
     *             @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *             @OA\Property(property="organization", type="string", example="Main Branch"),
     *             @OA\Property(property="description", type="string", example="Funding for health initiatives", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Grant created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/Grant"
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
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create grant"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function storeGrant(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|unique:grants,code',
                'name' => 'required|string|max:255',
                'organization' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
                'end_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['created_by'] = Auth::user()->name ?? 'system';
            $data['updated_by'] = Auth::user()->name ?? 'system';

            $grant = Grant::create($data);

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantActionNotification('created', $grant, $performedBy, 'grants_list'),
                    'created'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant created successfully',
                'data' => $grant,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create grant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/grants/items",
     *     operationId="storeGrantItem",
     *     summary="Store a new grant item",
     *     description="Creates a new grant item associated with an existing grant and automatically creates position slots",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"grant_id"},
     *
     *             @OA\Property(property="grant_id", type="integer", example=1, description="ID of the existing grant"),
     *             @OA\Property(property="grant_position", type="string", example="Project Manager", description="Position title - must be unique within grant when combined with budget line code"),
     *             @OA\Property(property="grant_salary", type="number", format="float", example=75000, description="Salary amount"),
     *             @OA\Property(property="grant_benefit", type="number", format="float", example=15000, description="Benefits amount"),
     *             @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75, description="Level of effort (0-1)"),
     *             @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position - will create this many position slots"),
     *             @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding - must be unique within grant when combined with position"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Grant item created successfully with position slots",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item created successfully with 2 position slots"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="grant_item", ref="#/components/schemas/GrantItem"),
     *                 @OA\Property(property="position_slots_created", type="integer", example=2)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or duplicate item",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="grant_position",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="The combination of grant position ""Project Manager"" and budget line code ""BL001"" already exists for this grant."
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="budgetline_code",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="The combination of grant position ""Project Manager"" and budget line code ""BL001"" already exists for this grant."
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create grant item"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function storeGrantItem(StoreGrantItemRequest $request)
    {
        try {
            DB::beginTransaction();

            // Get validated data
            $data = $request->validated();

            // Add user info
            $data['created_by'] = auth()->user()->name ?? 'system';
            $data['updated_by'] = auth()->user()->name ?? 'system';

            // Create the grant item
            $grantItem = GrantItem::create($data);

            // Refresh to ensure all relationships are loaded
            $grantItem->refresh();

            // Load the grant relationship for notification
            $grantItem->load('grant');

            // Position slots removed - allocations now link directly to grant_items

            DB::commit();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy && $grantItem->grant) {
                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantItemActionNotification('created', $grantItem, $grantItem->grant, $performedBy, 'grants_list'),
                    'created'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant item created successfully',
                'data' => [
                    'grant_item' => $grantItem,
                    'capacity' => $grantItem->grant_position_number ?? 1,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/grant-items/{id}",
     *     summary="Update a grant item",
     *     description="Update an existing grant item by ID and automatically manage position slots",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant item ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="grant_id", type="integer", example=1, description="ID of the grant - changing this may affect uniqueness validation"),
     *             @OA\Property(property="grant_position", type="string", example="Project Manager", description="Position title - must be unique within grant when combined with budget line code"),
     *             @OA\Property(property="grant_salary", type="number", example=5000, description="Salary amount"),
     *             @OA\Property(property="grant_benefit", type="number", example=1000, description="Benefits amount"),
     *             @OA\Property(property="grant_level_of_effort", type="number", example=0.75, description="Level of effort (0-1)"),
     *             @OA\Property(property="grant_position_number", type="integer", example=2, description="Number of people for this position - will adjust position slots automatically"),
     *             @OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding - must be unique within grant when combined with position"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant item updated successfully with position slot changes",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item updated successfully. Position slots: 1 added, 0 removed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="grant_item", ref="#/components/schemas/GrantItem"),
     *                 @OA\Property(property="position_slots_added", type="integer", example=1),
     *                 @OA\Property(property="position_slots_removed", type="integer", example=0),
     *                 @OA\Property(property="warnings", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or duplicate item",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="grant_position",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="The combination of grant position ""Senior Developer"" and budget line code ""BL002"" already exists for this grant."
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="budgetline_code",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="Budget line code cannot exceed 255 characters."
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="grant_id",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="The selected grant does not exist."
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     )
     * )
     */
    public function updateGrantItem(UpdateGrantItemRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            // Find the grant item or return 404
            $grantItem = GrantItem::findOrFail($id);

            // Store old position number for comparison
            $oldPositionNumber = $grantItem->grant_position_number ?? 1;

            // Get validated data
            $validated = $request->validated();

            // Add user info
            $validated['updated_by'] = auth()->user()->name ?? 'system';

            // Update the grant item
            $grantItem->update($validated);

            // Reload grant relationship for notification
            $grantItem->load('grant');

            // Position slots removed - allocations now link directly to grant_items
            // Capacity is now tracked via grant_position_number field

            DB::commit();

            // Get current active allocations count for this grant item
            $activeAllocationsCount = \App\Models\EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
                ->where('status', 'active')
                ->count();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy && $grantItem->grant) {
                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantItemActionNotification('updated', $grantItem, $grantItem->grant, $performedBy, 'grants_list'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant item updated successfully',
                'data' => [
                    'grant_item' => $grantItem,
                    'capacity' => $grantItem->grant_position_number ?? 1,
                    'active_allocations' => $activeAllocationsCount,
                    'available_capacity' => max(0, ($grantItem->grant_position_number ?? 1) - $activeAllocationsCount),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Grant item not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/grants/{id}",
     *     summary="Delete a grant",
     *     description="Delete a grant and all its associated items",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete grant"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function deleteGrant($id)
    {
        try {
            // Find the grant or return 404
            $grant = Grant::findOrFail($id);

            // Store grant data before deletion for notification
            $grantData = $grant->toArray();

            // Use a transaction to ensure all related items are deleted
            DB::beginTransaction();

            // Delete all related grant items first
            $grant->grantItems()->delete();

            // Delete the grant
            $grant->delete();

            DB::commit();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                $grantForNotification = (object) [
                    'id' => $grantData['id'] ?? null,
                    'name' => $grantData['name'] ?? 'Unknown Grant',
                    'code' => $grantData['code'] ?? 'N/A',
                ];

                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantActionNotification('deleted', $grantForNotification, $performedBy, 'grants_list'),
                    'deleted'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/grants/items/{id}",
     *     summary="Delete a grant item",
     *     description="Delete a specific grant item by ID",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant item ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant item deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete grant item"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function deleteGrantItem($id)
    {
        try {
            $grantItem = GrantItem::findOrFail($id);
            $grant = $grantItem->grant;

            // Store grant item and grant data before deletion for notification
            $grantItemData = $grantItem->toArray();
            $grantData = $grant ? $grant->toArray() : null;

            DB::transaction(function () use ($grantItem) {
                $grantItem->delete();
                // Grant should remain even if it has no items - allow users to add items later
            });

            // Send notification to all users
            $performedBy = auth()->user();
            if ($performedBy && $grantData) {
                $grantItemForNotification = (object) [
                    'id' => $grantItemData['id'] ?? null,
                    'grant_position' => $grantItemData['grant_position'] ?? 'Unknown Position',
                ];

                $grantForNotification = (object) [
                    'id' => $grantData['id'] ?? null,
                    'name' => $grantData['name'] ?? 'Unknown Grant',
                    'code' => $grantData['code'] ?? 'N/A',
                ];

                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantItemActionNotification('deleted', $grantItemForNotification, $grantForNotification, $performedBy, 'grants_list'),
                    'deleted'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant item deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant item not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/grants/{id}",
     *     operationId="updateGrant",
     *     summary="Update a grant",
     *     description="Updates an existing grant with the provided data",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the grant to update",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Updated Grant Name"),
     *             @OA\Property(property="code", type="string", example="GR-2023-002"),
     *             @OA\Property(property="description", type="string", example="Updated grant description"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-12-31")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grant updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Grant")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update grant"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function updateGrant(Request $request, $id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'end_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find the grant
            $grant = Grant::findOrFail($id);

            // Update the grant
            $grant->update($request->all());
            $grant->updated_by = auth()->user()->name ?? 'system';
            $grant->save();

            // Send notification using NotificationService
            $performedBy = auth()->user();
            if ($performedBy) {
                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantActionNotification('updated', $grant, $performedBy, 'grants_list'),
                    'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant updated successfully',
                'data' => $grant,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update grant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/grants/grant-positions",
     *     operationId="getGrantStatistics",
     *     summary="Get grant statistics with position recruitment status",
     *     description="Retrieves statistics for all grants including position recruitment status using new funding allocation schema",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="grant_id", type="integer", example=1),
     *                     @OA\Property(property="grant_code", type="string", example="GR-001"),
     *                     @OA\Property(property="grant_name", type="string", example="Research Grant"),
     *                     @OA\Property(
     *                         property="positions",
     *                         type="array",
     *
     *                         @OA\Items(
     *
     *                             @OA\Property(property="id", type="integer", example=10),
     *                             @OA\Property(property="position", type="string", example="Researcher"),
     *                             @OA\Property(property="manpower", type="integer", example=3),
     *                             @OA\Property(property="recruited", type="integer", example=2),
     *                             @OA\Property(property="finding", type="integer", example=1)
     *                         )
     *                     ),
     *                     @OA\Property(property="total_manpower", type="integer", example=5),
     *                     @OA\Property(property="total_recruited", type="integer", example=3),
     *                     @OA\Property(property="total_finding", type="integer", example=2),
     *                     @OA\Property(property="status", type="string", example="Active", enum={"Completed", "Active", "Pending"})
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant statistics"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getGrantPositions(Request $request)
    {
        try {
            // Eager-load grantItems with allocation counts
            $grants = \App\Models\Grant::with([
                'grantItems' => function ($q) {
                    $q->withCount(['employeeFundingAllocations as active_allocations_count' => function ($query) {
                        $query->where('status', 'active');
                    }]);
                },
            ])->orderBy('created_at', 'desc')->get();

            if ($grants->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No grants found',
                    'data' => [],
                ], 200);
            }

            $grantStats = [];

            foreach ($grants as $grant) {
                $totalPositions = 0;
                $recruitedPositions = 0;
                $openPositions = 0;
                $grantPositions = [];

                foreach ($grant->grantItems as $item) {
                    $positionTitle = $item->grant_position;
                    // get budgetline code
                    $budgetlineCode = $item->budgetline_code;
                    $manpower = (int) ($item->grant_position_number ?? 0);

                    // Count active allocations directly linked to this grant item
                    $activeAllocations = $item->active_allocations_count ?? 0;

                    $totalPositions += $manpower;
                    $recruitedPositions += $activeAllocations;
                    $openPositions += max(0, $manpower - $activeAllocations);

                    $grantPositions[] = [
                        'id' => $item->id,
                        'position' => $positionTitle,
                        'budgetline_code' => $budgetlineCode,
                        'manpower' => $manpower,
                        'recruited' => $activeAllocations,
                        'finding' => max(0, $manpower - $activeAllocations),
                    ];
                }

                $status = 'Active';
                if ($grant->end_date && $grant->end_date < now()) {
                    $status = 'Completed';
                } elseif ($recruitedPositions == $totalPositions && $totalPositions > 0) {
                    $status = 'Completed';
                } elseif ($recruitedPositions == 0 && $totalPositions > 0) {
                    $status = 'Pending';
                }

                $grantStats[] = [
                    'grant_id' => $grant->id,
                    'grant_code' => $grant->code,
                    'grant_name' => $grant->name,
                    'positions' => $grantPositions,
                    'total_manpower' => $totalPositions,
                    'total_recruited' => $recruitedPositions,
                    'total_finding' => $openPositions,
                    'status' => $status,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant statistics retrieved successfully',
                'data' => $grantStats,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant statistics',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/grants/delete-selected",
     *     operationId="deleteSelectedGrants",
     *     summary="Delete multiple grants",
     *     description="Delete multiple grants and their associated grant items by IDs. This is a bulk delete operation.",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"ids"},
     *
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 description="Array of grant IDs to delete",
     *
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Grants deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="5 grant(s) deleted successfully"),
     *             @OA\Property(property="count", type="integer", example=5)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete grants"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function deleteSelectedGrants(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:grants,id',
        ]);
        $ids = $validated['ids'];

        try {
            DB::beginTransaction();

            // Store grant data before deletion for notifications
            $grants = Grant::whereIn('id', $ids)->get();

            // Delete related grant items first (they have foreign key to grants)
            GrantItem::whereIn('grant_id', $ids)->delete();

            // Delete the grants
            $count = Grant::whereIn('id', $ids)->delete();

            DB::commit();

            // Send notification using NotificationService about the bulk delete
            $performedBy = auth()->user();
            if ($performedBy && $grants->isNotEmpty()) {
                $grantNames = $grants->pluck('name')->take(3)->implode(', ');
                $message = $count > 3
                    ? "{$grantNames} and ".($count - 3).' more'
                    : $grantNames;

                $grantForNotification = (object) [
                    'id' => null,
                    'name' => "Bulk delete: {$message}",
                    'code' => "{$count} grants",
                ];

                app(NotificationService::class)->notifyByModule(
                    'grants_list',
                    new GrantActionNotification('deleted', $grantForNotification, $performedBy, 'grants_list'),
                    'deleted'
                );
            }

            return response()->json([
                'success' => true,
                'message' => $count.' grant(s) deleted successfully',
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grants',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send import completion notification to the authenticated user
     *
     * @param  int  $processedGrants  Number of grants processed
     * @param  int  $processedItems  Number of grant items processed
     * @param  array  $errors  Array of error messages
     * @param  array  $skippedGrants  Array of skipped grant codes
     */
    private function sendImportNotification(int $processedGrants, int $processedItems, array $errors, array $skippedGrants): void
    {
        try {
            $message = "Grant import finished! Processed: {$processedGrants} grants, {$processedItems} grant items";

            if (count($errors) > 0) {
                $message .= ', Warnings: '.count($errors);
            }

            if (count($skippedGrants) > 0) {
                $message .= ', Skipped: '.count($skippedGrants);
            }

            $user = User::find(auth()->id());
            if ($user) {
                app(NotificationService::class)->notifyUser(
                    $user,
                    new ImportedCompletedNotification($message, 'import')
                );
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the import response
            \Log::error('Failed to send grant import notification', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
        }
    }
}
