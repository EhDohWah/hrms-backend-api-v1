<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Grant;
use App\Models\GrantItem;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Employment;
use App\Models\EmployeeGrantAllocation;
use App\Http\Resources\GrantItemResource;
use App\Models\PositionSlot;
use App\Models\BudgetLine;
use App\Http\Resources\GrantResource;
use App\Http\Resources\PositionSlotResource;
use App\Http\Resources\BudgetLineResource;
use Illuminate\Support\Facades\Schema;
use App\Models\EmployeeFundingAllocation;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Illuminate\Support\Facades\Log;

 

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
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Grant code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grant with items retrieved successfully",
     *         @OA\JsonContent(
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
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="grant_id", type="integer", example=1),
     *                         @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                         @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                         @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                         @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                         @OA\Property(property="grant_position_number", type="string", example="POS-001")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'grantItems' => function($query) {
                    $query->select('id', 'grant_id', 'grant_position', 'grant_salary', 'grant_benefit', 'grant_level_of_effort', 'grant_position_number');
                }
            ])
            ->where('code', $code)
            ->first(['id', 'code', 'name', 'subsidiary', 'description', 'end_date']);

            if (!$grant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grant not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant retrieved successfully',
                'data' => $grant
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/grants/by-id/{id}",
     *     operationId="getGrantById",
     *     summary="Get a specific grant with its items by grant ID",
     *     description="Returns a specific grant and its associated items with position slots and budget lines by grant ID.",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grant with items retrieved successfully",
     *         @OA\JsonContent(
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
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="grant_id", type="integer", example=1),
     *                         @OA\Property(property="grant_code", type="string", example="GR-2023-001"),
     *                         @OA\Property(property="grant_name", type="string", example="Health Initiative Grant"),
     *                         @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                         @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                         @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                         @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                         @OA\Property(property="grant_position_number", type="string", example="POS-001"),
     *                         @OA\Property(
     *                             property="position_slots",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="grant_item_id", type="integer", example=1),
     *                                 @OA\Property(property="slot_number", type="string", example="SLOT-001"),
     *                                 @OA\Property(property="budget_line_id", type="integer", example=1)
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-06-25T15:38:59Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-06-25T15:38:59Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
     *         )
     *     ),
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
     *         @OA\JsonContent(
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
                        'created_at',
                        'updated_at'
                    )->with([
                        'positionSlots' => function ($slotQ) {
                            $slotQ->select(
                                'id',
                                'grant_item_id',
                                'slot_number',
                                'budget_line_id',
                                'created_at',
                                'updated_at'
                            )->with([
                                'budgetLine:id,budget_line_code,description,created_by,updated_by,created_at,updated_at'
                            ]);
                        }
                    ]);
                }
            ])
            ->select(
                'id', 'code', 'name', 'subsidiary', 'description', 'end_date', 'created_at', 'updated_at'
            )
            ->where('id', $id)
            ->first();

            if (!$grant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grant not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grant retrieved successfully',
                'data' => new GrantResource($grant)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/grants/upload",
     *     summary="Upload grant data from Excel file",
     *     description="Upload an Excel file with multiple sheets containing grant header and item records",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file to upload (xlsx, xls, csv)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grant data imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Grant data import completed"),
     *             @OA\Property(property="processed_grants", type="integer", example=2),
     *             @OA\Property(property="warnings", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Import failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to import grant data"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function upload(Request $request)
    {
        $this->validateFile($request);

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

                    if (!$grant) continue; // Error already recorded

                    // Check if grant already exists and wasn't just created
                    if (!$grant->wasRecentlyCreated) {
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
                        $errors[] = "Sheet '$sheetName': Error processing items - " . $e->getMessage();
                    }
                }
            });

            $response = [
                'success' => true,
                'message' => 'Grant data import completed',
                'data' => [
                    'processed_grants' => $processedGrants
                ]
            ];

            if (!empty($errors)) {
                $response['data']['warnings'] = $errors;
            }

            if (!empty($skippedGrants)) {
                $response['data']['skipped_grants'] = $skippedGrants;
                $response['message'] = 'Grant data import completed with skipped grants';
            }

            return response()->json($response);

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
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="filter_subsidiary",
     *         in="query",
     *         description="Filter grants by subsidiary (comma-separated for multiple values)",
     *         required=false,
     *         @OA\Schema(type="string", example="SMRU,BHF")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field (name or code)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "code"}, example="name")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of grants with items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grants retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="subsidiary", type="string", example="Main Campus"),
     *                     @OA\Property(property="description", type="string", example="Funding for health initiatives", nullable=true),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true),
     *                     @OA\Property(
     *                         property="grant_items",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="grant_id", type="integer", example=1),
     *                             @OA\Property(property="grant_code", type="string", example="GR-2023-001"),
     *                             @OA\Property(property="grant_name", type="string", example="Health Initiative Grant"),
     *                             @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                             @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                             @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                             @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                             @OA\Property(property="grant_position_number", type="string", example="POS-001"),
     *                             @OA\Property(
     *                                 property="position_slots",
     *                                 type="array",
     *                                 @OA\Items(
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid parameters provided",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"per_page": {"The per page must be between 1 and 100."}})
     *         )
     *     ),
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
     *         @OA\JsonContent(
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
                'page'             => 'integer|min:1',
                'per_page'         => 'integer|min:1|max:100',
                'filter_subsidiary'=> 'string|nullable',
                'sort_by'          => 'string|nullable|in:name,code',
                'sort_order'       => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query using model scopes for optimization
            $query = Grant::forPagination()
                ->withItemsCount()
                ->withOptimizedItems();

            // Apply subsidiary filter if provided
            if (!empty($validated['filter_subsidiary'])) {
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
            if (!empty($validated['filter_subsidiary'])) {
                $appliedFilters['subsidiary'] = explode(',', $validated['filter_subsidiary']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grants retrieved successfully',
                'data'    => GrantResource::collection($grants->items()),
                'pagination' => [
                    'current_page'   => $grants->currentPage(),
                    'per_page'       => $grants->perPage(),
                    'total'          => $grants->total(),
                    'last_page'      => $grants->lastPage(),
                    'from'           => $grants->firstItem(),
                    'to'             => $grants->lastItem(),
                    'has_more_pages' => $grants->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grants',
                'error' => $e->getMessage()
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
     *     @OA\Response(
     *         response=200,
     *         description="Grant items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant items retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="grant_id", type="integer", example=1),
     *                     @OA\Property(property="grant_code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="grant_name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                     @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                     @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                     @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                     @OA\Property(property="grant_position_number", type="string", example="POS-001")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=10)
     *         )
     *     ),
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
     *         @OA\JsonContent(
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
                'count' => $grantItems->count()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant items',
                'error' => $e->getMessage()
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of grant item to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
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
     *                 @OA\Property(property="grant_position_number", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'data' => $grantItem
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant item',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    private function createGrant(array $data, string $sheetName, array &$errors)
    {
        try {
            $grantName = trim(str_replace('Grant name -', '', $data[1]['A'] ?? ''));
            $grantCode = trim(str_replace('Grant code -', '', $data[2]['A'] ?? ''));
            $subsidiary = trim(str_replace('Subsidiary -', '', $data[3]['A'] ?? ''));
            // Try to extract end date if available
            if (isset($data[4]['A'])) {
                $endDateStr = trim(str_replace('End date -', '', $data[4]['A'] ?? ''));
                if (!empty($endDateStr)) {
                    try {
                        $endDate = \Carbon\Carbon::parse($endDateStr)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Sheet '$sheetName': Invalid end date format - " . $endDateStr;
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
            $errors[] = "Sheet '$sheetName': Error creating grant - " . $e->getMessage();
            return null;
        }
    }

    /**
     * Process grant items from Excel data
     *
     * @param array $data Array of Excel row data
     * @param \App\Models\Grant $grant Grant model instance
     * @param string $sheetName Name of Excel sheet being processed
     * @param array $errors Array to store error messages
     * @return int Number of items processed
     * @throws \Exception
     */
    private function processGrantItems(array $data, Grant $grant, string $sheetName, array &$errors): int
    {
        $itemsProcessed = 0;
        $grantItems = [];
        $createdBudgetLines = [];

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
                $budgetLine = $createdBudgetLines[$bgLineCode] ?? BudgetLine::firstOrCreate([
                    'budget_line_code' => $bgLineCode,
                ], [
                    'created_by' => auth()->user()->name ?? 'system',
                    'updated_by' => auth()->user()->name ?? 'system',
                ]);
                $createdBudgetLines[$bgLineCode] = $budgetLine;

                // --- 2. Grant Item ---
                $itemKey = $grant->id . '|' . $grantPosition; // Only unique per grant+position
                if (!isset($createdGrantItems[$itemKey])) {
                    $grantItem = GrantItem::create([
                        'grant_id' => $grant->id,
                        'grant_position' => $grantPosition,
                        'grant_salary' => isset($row['C']) && $row['C'] !== '' ? $this->toFloat($row['C']) : null,
                        'grant_benefit' => isset($row['D']) && $row['D'] !== '' ? $this->toFloat($row['D']) : null,
                        'grant_level_of_effort' => isset($row['E']) && $row['E'] !== '' ?
                            (float)trim(str_replace('%', '', $row['E'])) / 100 : null,
                        'grant_position_number' => isset($row['F']) && $row['F'] !== '' ? (int)$row['F'] : 1,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $createdGrantItems[$itemKey] = $grantItem;
                } else {
                    $grantItem = $createdGrantItems[$itemKey];
                }

                // --- 3. Position Slots (One per position number, all under the same BG line) ---
                $positionCount = isset($row['F']) && $row['F'] !== '' ? (int)$row['F'] : 1;
                for ($slot = 1; $slot <= $positionCount; $slot++) {
                    PositionSlot::create([
                        'grant_item_id' => $grantItem->id,
                        'slot_number' => $slot,
                        'budget_line_id' => $budgetLine->id,
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system',
                    ]);
                }

                $itemsProcessed++;
            }
        } catch (\Exception $e) {
            $errors[] = "Sheet '$sheetName': Error processing items - " . $e->getMessage();
            throw $e;
        }

        return $itemsProcessed;
    }

    /**
     * Validate uploaded file
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240' // Added max file size
        ]);
    }

    /**
     * Convert string value to float
     *
     * @param mixed $value Value to convert
     * @return float|null Converted float value or null if input is null
     */
    private function toFloat($value): ?float
    {
        if (is_null($value)) return null;
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name", "subsidiary"},
     *             @OA\Property(property="code", type="string", example="GR-2023-001"),
     *             @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *             @OA\Property(property="subsidiary", type="string", example="Main Branch"),
     *             @OA\Property(property="description", type="string", example="Funding for health initiatives", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Grant created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/Grant"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create grant"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function storeGrant(Request $request)
    {
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
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['created_by'] = Auth::user()->name ?? 'system';
            $data['updated_by'] = Auth::user()->name ?? 'system';

            $grant = Grant::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Grant created successfully',
                'data' => $grant
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create grant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/grants/items",
     *     operationId="storeGrantItem",
     *     summary="Store a new grant item",
     *     description="Creates a new grant item associated with an existing grant",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"grant_id"},
     *             @OA\Property(property="grant_id", type="integer", example=1, description="ID of the existing grant"),
     *             @OA\Property(property="grant_position", type="string", example="Project Manager", description="Position title"),
     *             @OA\Property(property="grant_salary", type="number", format="float", example=75000, description="Salary amount"),
     *             @OA\Property(property="grant_benefit", type="number", format="float", example=15000, description="Benefits amount"),
     *             @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75, description="Level of effort (0-1)"),
     *             @OA\Property(property="grant_position_number", type="string", example="POS-001", description="Position identifier"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Grant item created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/GrantItem")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or duplicate item",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
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
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create grant item"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function storeGrantItem(Request $request)
    {
        // validate the request
        $request->validate([
            'grant_id'               => 'required|exists:grants,id',
            'grant_position'         => 'nullable|string|max:255',
            'grant_salary'           => 'nullable|numeric|min:0',
            'grant_benefit'          => 'nullable|numeric|min:0',
            'grant_level_of_effort'  => 'nullable|numeric|between:0,1',
            'grant_position_number'  => 'nullable|integer|min:0',
        ]);

        // Add user info
        $data = $request->all();
        $data['created_by'] = auth()->user()->name ?? 'system';
        $data['updated_by'] = auth()->user()->name ?? 'system';

        $grantItem = GrantItem::create($data);
        return response()->json([
            'success' => true,
            'message' => 'Grant item created successfully',
            'data' => $grantItem
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/grant-items/{id}",
     *     summary="Update a grant item",
     *     description="Update an existing grant item by ID",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant item ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="grant_id", type="integer", example=1),
     *             @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *             @OA\Property(property="grant_salary", type="number", example=5000),
     *             @OA\Property(property="grant_benefit", type="number", example=1000),
     *             @OA\Property(property="grant_level_of_effort", type="number", example=0.75),
     *             @OA\Property(property="grant_position_number", type="string", example="P-123"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grant item updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/GrantItem")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     )
     * )
     */
    public function updateGrantItem(Request $request, $id)
    {
        // Find the grant item or return 404
        $grantItem = GrantItem::findOrFail($id);

        // Validate the request
        $validated = $request->validate([
            'grant_id' => 'sometimes|required|exists:grants,id',
            'grant_position' => 'nullable|string',
            'grant_salary' => 'nullable|numeric',
            'grant_benefit' => 'nullable|numeric',
            'grant_level_of_effort' => 'nullable|numeric|min:0|max:100',
            'grant_position_number' => 'nullable|string',
        ]);

        // Add user info
        $validated['updated_by'] = auth()->user()->name ?? 'system';

        // Update the grant item
        $grantItem->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Grant item updated successfully',
            'data' => $grantItem
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/grants/{id}",
     *     summary="Delete a grant",
     *     description="Delete a grant and all its associated items",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grant deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'message' => 'Grant deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grant',
                'error' => $e->getMessage()
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant item ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grant item deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grant item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'message' => 'Grant item deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grant item',
                'error' => $e->getMessage()
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the grant to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Grant Name"),
     *             @OA\Property(property="code", type="string", example="GR-2023-002"),
     *             @OA\Property(property="description", type="string", example="Updated grant description"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-12-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grant updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Grant"),
     *             @OA\Property(property="message", type="string", example="Grant updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update grant")
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
                'end_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
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
                'data' => $grant,
                'message' => 'Grant updated successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update grant: ' . $e->getMessage()
            ], 500);
        }
    }

    
    // /**
    //  * @OA\Post(
    //  *     path="/grants/position-slot/{grantItemId}/{budgetLine}",
    //  *     operationId="createGrantPositionSlot",
    //  *     summary="Create grant position slots for a grant item and budget line",
    //  *     description="Creates position slots for a given grant item and budget line. The number of slots is determined by the grant_position_number of the grant item. The budgetLine can be a string or numeric value.",
    //  *     tags={"Grants"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="grantItemId",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID of the GrantItem",
    //  *         @OA\Schema(type="integer")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="budgetLine",
    //  *         in="path",
    //  *         required=true,
    //  *         description="Budget line identifier (can be string or numeric)",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="Grant position slots created successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="Grant position slots created successfully")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=404,
    //  *         description="GrantItem not found"
    //  *     ),
    //  *     @OA\Response(
    //  *         response=400,
    //  *         description="Invalid input"
    //  *     ),
    //  *     @OA\Response(
    //  *         response=500,
    //  *         description="Server error"
    //  *     )
    //  * )
    //  */
    // public function createGrantPositionSlot(Request $request, $grantItemId, $budgetLine)
    // {
    //     // Validate grantItemId
    //     if (!is_numeric($grantItemId)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid GrantItem ID'
    //         ], 400);
    //     }

    //     $grantItem = GrantItem::find($grantItemId);

    //     if (!$grantItem) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'GrantItem not found'
    //         ], 404);
    //     }

    //     // grant_position_number must be present and >= 1
    //     $numSlots = (int) $grantItem->grant_position_number;
    //     if ($numSlots < 1) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'GrantItem grant_position_number must be at least 1'
    //         ], 400);
    //     }

    //     try {
    //         DB::transaction(function () use ($grantItem, $budgetLine, $numSlots) {
    //             for ($i = 1; $i <= $numSlots; $i++) {
    //                 GrantPositionSlot::create([
    //                     'grant_item_id' => $grantItem->id,
    //                     'slot_number' => $i,
    //                     'created_by' => auth()->user()->name ?? 'system',
    //                     // 'bg_line' => $budgetLine, // bg_line removed
    //                 ]);
    //             }
    //         });

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Grant position slots created successfully'
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to create grant position slots: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * @OA\Get(
     *     path="/grants/grant-positions",
     *     operationId="getGrantStatistics",
     *     summary="Get grant statistics with position recruitment status",
     *     description="Retrieves statistics for all grants including position recruitment status using new funding allocation schema",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="grant_id", type="integer", example=1),
     *                     @OA\Property(property="grant_code", type="string", example="GR-001"),
     *                     @OA\Property(property="grant_name", type="string", example="Research Grant"),
     *                     @OA\Property(
     *                         property="positions",
     *                         type="array",
     *                         @OA\Items(
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
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'grantItems.positionSlots'
            ])->orderBy('created_at', 'desc')->get();

            if ($grants->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'message' => 'No grants found'
                ]);
            }

            $grantStats = [];

            foreach ($grants as $grant) {
                $totalPositions     = 0;
                $recruitedPositions = 0;
                $openPositions      = 0;
                $grantPositions     = [];

                foreach ($grant->grantItems as $item) {
                    $positionTitle = $item->grant_position;
                    $manpower      = (int)($item->grant_position_number ?? 0);

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
                            $allocationQuery->where(function($q) {
                                $q->whereNull('end_date')->orWhere('end_date', '>', now());
                            });
                        }

                        $activeAllocations = $allocationQuery->count();
                        $slotAllocations  += $activeAllocations;
                    }

                    $totalPositions     += $manpower;
                    $recruitedPositions += $slotAllocations;
                    $openPositions      += ($manpower - $slotAllocations);

                    $grantPositions[] = [
                        'id'        => $item->id,
                        'position'  => $positionTitle,
                        'manpower'  => $manpower,
                        'recruited' => $slotAllocations,
                        'finding'   => max(0, $manpower - $slotAllocations),
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
                    'grant_id'        => $grant->id,
                    'grant_code'      => $grant->code,
                    'grant_name'      => $grant->name,
                    'positions'       => $grantPositions,
                    'total_manpower'  => $totalPositions,
                    'total_recruited' => $recruitedPositions,
                    'total_finding'   => $openPositions,
                    'status'          => $status,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => $grantStats,
                'message' => 'Grant statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant statistics',
                'error' => app()->environment('production') ? null : $e->getMessage()
            ], 500);
        }
    }


}
