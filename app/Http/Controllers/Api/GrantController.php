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
use App\Models\PositionSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
     *                 @OA\Property(property="subsidiary", type="string", example="Main Branch"),
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
                ->first(['id', 'code', 'name', 'subsidiary', 'description', 'end_date']);

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
     *                 @OA\Property(property="subsidiary", type="string", example="Main Campus"),
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
                    )->with([
                        'positionSlots' => function ($slotQ) {
                            $slotQ->select(
                                'id',
                                'grant_item_id',
                                'slot_number',
                                'created_at',
                                'updated_at'
                            );
                        },
                    ]);
                },
            ])
                ->select(
                    'id', 'code', 'name', 'subsidiary', 'description', 'end_date', 'created_at', 'updated_at'
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
     *     description="Upload an Excel file with multiple sheets containing grant header and item records. Duplicate grant items (same position + budget line code within a grant) will be rejected with detailed error messages.",
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
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheets = $spreadsheet->getAllSheets();

            $processedGrants = 0;
            $errors = [];
            $skippedGrants = [];

            DB::transaction(function () use ($sheets, &$processedGrants, &$errors, &$skippedGrants) {
                foreach ($sheets as $sheet) {
                    $data = $sheet->toArray(null, true, true, true);
                    $sheetName = $sheet->getTitle();

                    // Validate minimum rows
                    if (count($data) < 5) {
                        $errors[] = "Sheet '$sheetName' skipped: Insufficient data rows (minimum 5 required)";

                        continue;
                    }

                    // Process grant
                    $grant = $this->createGrant($data, $sheetName, $errors);

                    if (! $grant) {
                        continue;
                    } // Error already recorded

                    // Check if grant already exists and wasn't just created
                    if (! $grant->wasRecentlyCreated) {
                        $errors[] = "Sheet '$sheetName': Grant '{$grant->code}' already exists - items skipped";
                        $skippedGrants[] = $grant->code;

                        continue;
                    }

                    // Process items
                    try {
                        $itemsProcessed = $this->processGrantItems($data, $grant, $sheetName, $errors);
                        if ($itemsProcessed > 0) {
                            $processedGrants++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Sheet '$sheetName': Error processing items - ".$e->getMessage();
                    }
                }
            });

            $responseData = [
                'processed_grants' => $processedGrants,
            ];

            if (! empty($errors)) {
                $responseData['warnings'] = $errors;
            }

            if (! empty($skippedGrants)) {
                $responseData['skipped_grants'] = $skippedGrants;
            }

            $message = 'Grant data import completed';
            if (! empty($skippedGrants)) {
                $message = 'Grant data import completed with skipped grants';
            }

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
     *     description="Returns a paginated list of grants with their associated items. Supports filtering by subsidiary and sorting by name/code with standard Laravel pagination parameters (page, per_page).",
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
     *         name="filter_subsidiary",
     *         in="query",
     *         description="Filter grants by subsidiary (comma-separated for multiple values)",
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
     *                     @OA\Property(property="subsidiary", type="string", example="Main Campus"),
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
     *                     @OA\Property(property="subsidiary", type="array", @OA\Items(type="string"), example={"SMRU"})
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
                'filter_subsidiary' => 'string|nullable',
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

            // Apply subsidiary filter if provided
            if (! empty($validated['filter_subsidiary'])) {
                $query->bySubsidiary($validated['filter_subsidiary']);
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
            if (! empty($validated['filter_subsidiary'])) {
                $appliedFilters['subsidiary'] = explode(',', $validated['filter_subsidiary']);
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
     *                     @OA\Property(property="subsidiary", type="string", example="SMRU"),
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

    private function createGrant(array $data, string $sheetName, array &$errors)
    {
        try {
            $grantName = trim(str_replace('Grant name -', '', $data[1]['A'] ?? ''));
            $grantCode = trim(str_replace('Grant code -', '', $data[2]['A'] ?? ''));
            $subsidiary = trim(str_replace('Subsidiary -', '', $data[3]['A'] ?? ''));
            $endDate = null;

            // Try to extract end date if available
            if (isset($data[4]['A'])) {
                $endDateStr = trim(str_replace('End date -', '', $data[4]['A'] ?? ''));
                if (! empty($endDateStr)) {
                    try {
                        $endDate = \Carbon\Carbon::parse($endDateStr)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Sheet '$sheetName': Invalid end date format - ".$endDateStr;
                    }
                }
            }

            $description = trim(str_replace('Description -', '', $data[5]['A'] ?? ''));

            // Validate required fields
            if (empty($grantCode)) {
                $errors[] = "Sheet '$sheetName': Missing grant code";

                return null;
            }

            if (empty($grantName)) {
                $errors[] = "Sheet '$sheetName': Missing grant name";

                return null;
            }

            return Grant::firstOrCreate(
                ['code' => $grantCode],
                [
                    'name' => $grantName,
                    'end_date' => $endDate,
                    'subsidiary' => $subsidiary,
                    'description' => $description,
                    'created_by' => auth()->user()->name ?? 'system',
                    'updated_by' => auth()->user()->name ?? 'system',
                ]
            );
        } catch (\Exception $e) {
            $errors[] = "Sheet '$sheetName': Error creating grant - ".$e->getMessage();

            return null;
        }
    }

    /**
     * Process grant items from Excel data
     *
     * @param  array  $data  Array of Excel row data
     * @param  \App\Models\Grant  $grant  Grant model instance
     * @param  string  $sheetName  Name of Excel sheet being processed
     * @param  array  $errors  Array to store error messages
     * @return int Number of items processed
     *
     * @throws \Exception
     */
    private function processGrantItems(array $data, Grant $grant, string $sheetName, array &$errors): int
    {
        $itemsProcessed = 0;
        $grantItems = [];
        $createdBudgetLines = [];
        $createdGrantItems = [];

        try {
            // Skip header rows (1-6) and start processing from row 7
            $headerRowsCount = 7;

            for ($i = $headerRowsCount + 1; $i <= count($data); $i++) {
                $row = $data[$i];

                // Use column B as the first required field (grant_position)
                $grantPosition = trim($row['B'] ?? '');
                $bgLineCode = trim($row['A'] ?? '');

                // Skip empty rows or non-data rows
                if (empty($grantPosition)) {
                    continue;
                }

                // --- 1. Budget Line ---
                if ($bgLineCode === '') {
                    $errors[] = "Sheet '$sheetName' row $i: Missing budget line code (column A)";

                    continue;
                }

                $createdBudgetLines[$bgLineCode] = true;

                // --- 2. Grant Item ---
                // Create unique key using grant_id, grant_position, and budgetline_code
                $itemKey = $grant->id.'|'.$grantPosition.'|'.$bgLineCode;

                if (! isset($createdGrantItems[$itemKey])) {
                    // Check if this combination already exists in database
                    $existingItem = GrantItem::where('grant_id', $grant->id)
                        ->where('grant_position', $grantPosition)
                        ->where('budgetline_code', $bgLineCode)
                        ->first();

                    if ($existingItem) {
                        $errors[] = "Sheet '$sheetName' row $i: Duplicate grant item - Position '$grantPosition' with budget line code '$bgLineCode' already exists for this grant";

                        continue;
                    }

                    $grantItem = GrantItem::create([
                        'grant_id' => $grant->id,
                        'grant_position' => $grantPosition,
                        'grant_salary' => isset($row['C']) && $row['C'] !== '' ? $this->toFloat($row['C']) : null,
                        'grant_benefit' => isset($row['D']) && $row['D'] !== '' ? $this->toFloat($row['D']) : null,
                        'grant_level_of_effort' => isset($row['E']) && $row['E'] !== '' ?
                            (float) trim(str_replace('%', '', $row['E'])) / 100 : null,
                        'grant_position_number' => isset($row['F']) && $row['F'] !== '' ? (int) $row['F'] : 1,
                        'budgetline_code' => $bgLineCode, // Moved from position_slots to grant_items
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $createdGrantItems[$itemKey] = $grantItem;
                } else {
                    $grantItem = $createdGrantItems[$itemKey];
                }

                // --- 3. Position Slots (One per position number, budget line code now in grant_item) ---
                $positionCount = isset($row['F']) && $row['F'] !== '' ? (int) $row['F'] : 1;
                for ($slot = 1; $slot <= $positionCount; $slot++) {
                    PositionSlot::create([
                        'grant_item_id' => $grantItem->id,
                        'slot_number' => $slot,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                    ]);
                }

                $itemsProcessed++;
            }
        } catch (\Exception $e) {
            $errors[] = "Sheet '$sheetName': Error processing items - ".$e->getMessage();
            throw $e;
        }

        return $itemsProcessed;
    }

    /**
     * Validate uploaded file
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Added max file size
        ]);
    }

    /**
     * Convert string value to float
     *
     * @param  mixed  $value  Value to convert
     * @return float|null Converted float value or null if input is null
     */
    private function toFloat($value): ?float
    {
        if (is_null($value)) {
            return null;
        }

        return floatval(preg_replace('/[^0-9.-]/', '', $value));
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
     *             required={"code", "name", "subsidiary"},
     *
     *             @OA\Property(property="code", type="string", example="GR-2023-001"),
     *             @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *             @OA\Property(property="subsidiary", type="string", example="Main Branch"),
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
                'subsidiary' => 'required|string|max:255',
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

            // Create position slots automatically
            $positionSlots = $this->createPositionSlots($grantItem);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grant item created successfully with '.count($positionSlots).' position slots',
                'data' => [
                    'grant_item' => $grantItem,
                    'position_slots_created' => count($positionSlots),
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

            // Handle position slot changes if position number changed
            $positionSlotChanges = ['added' => 0, 'removed' => 0, 'warnings' => []];
            $newPositionNumber = $grantItem->grant_position_number ?? 1;

            if ($oldPositionNumber !== $newPositionNumber) {
                $positionSlotChanges = $this->updatePositionSlots($grantItem, $oldPositionNumber);
            }

            DB::commit();

            $message = 'Grant item updated successfully';
            if ($positionSlotChanges['added'] > 0 || $positionSlotChanges['removed'] > 0) {
                $message .= '. Position slots: '.$positionSlotChanges['added'].' added, '.$positionSlotChanges['removed'].' removed';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'grant_item' => $grantItem,
                    'position_slots_added' => $positionSlotChanges['added'],
                    'position_slots_removed' => $positionSlotChanges['removed'],
                    'warnings' => $positionSlotChanges['warnings'],
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

            // Use a transaction to ensure all related items are deleted
            DB::beginTransaction();

            // Delete all related grant items first
            $grant->grantItems()->delete();

            // Delete the grant
            $grant->delete();

            DB::commit();

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

            DB::transaction(function () use ($grantItem, $grant) {
                $grantItem->delete();
                if ($grant && $grant->grantItems()->count() === 0) {
                    $grant->delete();
                }
            });

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
            // Eager-load grantItems -> positionSlots
            $grants = \App\Models\Grant::with([
                'grantItems.positionSlots',
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

                    // For each position slot under this grant item
                    $slotAllocations = 0;
                    foreach ($item->positionSlots as $slot) {
                        // Count active "grant" allocations for this slot
                        $allocationQuery = \App\Models\EmployeeFundingAllocation::query()
                            ->where('position_slot_id', $slot->id)
                            ->where('allocation_type', 'grant');

                        // If 'active' column exists, filter on it. Otherwise, use end_date logic.
                        if (Schema::hasColumn('employee_funding_allocations', 'active')) {
                            $allocationQuery->where('active', true);
                        } else {
                            $allocationQuery->where(function ($q) {
                                $q->whereNull('end_date')->orWhere('end_date', '>', now());
                            });
                        }

                        $activeAllocations = $allocationQuery->count();
                        $slotAllocations += $activeAllocations;
                    }

                    $totalPositions += $manpower;
                    $recruitedPositions += $slotAllocations;
                    $openPositions += ($manpower - $slotAllocations);

                    $grantPositions[] = [
                        'id' => $item->id,
                        'position' => $positionTitle,
                        'budgetline_code' => $budgetlineCode,
                        'manpower' => $manpower,
                        'recruited' => $slotAllocations,
                        'finding' => max(0, $manpower - $slotAllocations),
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
     * Create position slots for a grant item
     *
     * @param  \App\Models\GrantItem  $grantItem  The grant item to create slots for
     * @return array Array of created position slots
     */
    private function createPositionSlots(GrantItem $grantItem): array
    {
        $positionNumber = $grantItem->grant_position_number ?? 1;
        $createdSlots = [];
        $currentUser = auth()->user()->name ?? 'system';

        for ($slot = 1; $slot <= $positionNumber; $slot++) {
            $positionSlot = PositionSlot::create([
                'grant_item_id' => $grantItem->id,
                'slot_number' => $slot,
                'created_by' => $currentUser,
                'updated_by' => $currentUser,
            ]);
            $createdSlots[] = $positionSlot;
        }

        return $createdSlots;
    }

    /**
     * Update position slots when grant item position number changes
     *
     * @param  \App\Models\GrantItem  $grantItem  The grant item being updated
     * @param  int  $oldPositionNumber  The previous position number
     * @return array Array with counts of added, removed slots and any warnings
     */
    private function updatePositionSlots(GrantItem $grantItem, int $oldPositionNumber): array
    {
        $newPositionNumber = $grantItem->grant_position_number ?? 1;
        $added = 0;
        $removed = 0;
        $warnings = [];
        $currentUser = auth()->user()->name ?? 'system';

        if ($newPositionNumber > $oldPositionNumber) {
            // Create additional slots
            for ($slot = $oldPositionNumber + 1; $slot <= $newPositionNumber; $slot++) {
                PositionSlot::create([
                    'grant_item_id' => $grantItem->id,
                    'slot_number' => $slot,
                    'created_by' => $currentUser,
                    'updated_by' => $currentUser,
                ]);
                $added++;
            }
        } elseif ($newPositionNumber < $oldPositionNumber) {
            // Remove excess slots, but only if they don't have active allocations
            $excessSlots = PositionSlot::where('grant_item_id', $grantItem->id)
                ->where('slot_number', '>', $newPositionNumber)
                ->get();

            foreach ($excessSlots as $slot) {
                // Check if this slot has active employee funding allocations
                $hasActiveAllocations = \App\Models\EmployeeFundingAllocation::where('position_slot_id', $slot->id)
                    ->where('allocation_type', 'grant')
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>', now());
                    })
                    ->exists();

                if (! $hasActiveAllocations) {
                    $slot->delete();
                    $removed++;
                } else {
                    $warnings[] = "Position slot {$slot->slot_number} could not be removed due to active employee funding allocations";
                }
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'warnings' => $warnings,
        ];
    }
}
