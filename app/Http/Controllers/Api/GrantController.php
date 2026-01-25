<?php

namespace App\Http\Controllers\Api;

use App\Exports\GrantTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\GrantResource;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\Position;
use App\Models\User;
use App\Notifications\GrantActionNotification;
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
    public function showByCode($code)
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
            $export = new GrantTemplateExport;
            $tempFile = $export->generate();
            $filename = $export->getFilename();

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
    public function store(Request $request)
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
    public function destroy($id)
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
    public function update(Request $request, $id)
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
    public function positions(Request $request)
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
    public function destroyBatch(Request $request)
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
