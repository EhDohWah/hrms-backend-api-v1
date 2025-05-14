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
     *     path="/grants/{code}",
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
     *                         @OA\Property(property="bg_line", type="string", example="BL-123"),
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
                    $query->select('id', 'grant_id', 'bg_line', 'grant_position', 'grant_salary', 'grant_benefit', 'grant_level_of_effort', 'grant_position_number');
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
     *     summary="List all grants with their items",
     *     description="Returns a list of grants and their associated items.",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of grants with items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Grants retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="GR-2023-001"),
     *                     @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *                     @OA\Property(property="description", type="string", example="Funding for health initiatives", nullable=true),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true),
     *                     @OA\Property(
     *                         property="grant_items",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="grant_id", type="integer", example=1),
     *                             @OA\Property(property="bg_line", type="string", example="BL-123"),
     *                             @OA\Property(property="grant_position", type="string", example="Project Manager"),
     *                             @OA\Property(property="grant_salary", type="number", format="float", example=75000),
     *                             @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
     *                             @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
     *                             @OA\Property(property="grant_position_number", type="string", example="POS-001"),
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=1)
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
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grants"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $grants = Grant::with([
                'grantItems' => function($query) {
                    $query->select('id', 'grant_id', 'bg_line', 'grant_position', 'grant_salary', 'grant_benefit', 'grant_level_of_effort', 'grant_position_number');
                    // No need for position relationship now
                }
            ])
            ->orderBy('created_at', 'desc') // Order by created_at in descending order to show latest grants first
            ->get(['id', 'code', 'name', 'subsidiary', 'description', 'end_date']); // Select only necessary columns from the Grant table

        return response()->json([
            'success' => true,
            'message' => 'Grants retrieved successfully',
            'data' => $grants,
            'count' => $grants->count()
        ], 200);
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
     *                     @OA\Property(property="bg_line", type="string", example="BL-123"),
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

            $formattedItems = $grantItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'grant_id' => $item->grant_id,
                    'grant_code' => $item->grant->code,
                    'grant_name' => $item->grant->name,
                    'bg_line' => $item->bg_line,
                    'grant_position' => $item->grant_position,
                    'grant_salary' => $item->grant_salary,
                    'grant_benefit' => $item->grant_benefit,
                    'grant_level_of_effort' => $item->grant_level_of_effort,
                    'grant_position_number' => $item->grant_position_number
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Grant items retrieved successfully',
                'data' => $formattedItems,
                'count' => $formattedItems->count()
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
     *                 @OA\Property(property="bg_line", type="string", example="BL-123"),
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

        try {
            // Skip header rows (1-6) and start processing from row 7
            $headerRowsCount = 7;

            for ($i = $headerRowsCount + 1; $i <= count($data); $i++) {
                $row = $data[$i];
                $bgLine = $row['A'] ?? null;

                // Skip empty rows or non-data rows
                if (empty($bgLine)) {
                    continue;
                }

                // Check for duplicates
                $existingItem = GrantItem::where('grant_id', $grant->id)
                    ->where('bg_line', $bgLine)
                    ->exists();

                if ($existingItem) {
                    $errors[] = "Sheet '$sheetName': Duplicate item (BG Line: $bgLine) skipped";
                    continue;
                }

                // Prepare item data based on the updated schema
                // Handle empty cells by explicitly setting them to null
                $grantItems[] = [
                    'grant_id' => $grant->id,
                    'bg_line' => $bgLine,
                    'grant_position' => isset($row['B']) && $row['B'] !== '' ? $row['B'] : null,
                    'grant_salary' => isset($row['C']) && $row['C'] !== '' ? $this->toFloat($row['C']) : null,
                    'grant_benefit' => isset($row['D']) && $row['D'] !== '' ? $this->toFloat($row['D']) : null,
                    'grant_level_of_effort' => isset($row['E']) && $row['E'] !== '' ?
                        (float)trim(str_replace('%', '', $row['E'])) : null,
                    'grant_position_number' => isset($row['F']) && $row['F'] !== '' ? $row['F'] : null,
                    'created_by' => auth()->user()->name ?? 'system',
                    'updated_by' => auth()->user()->name ?? 'system',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $itemsProcessed++;
            }

            if (!empty($grantItems)) {
                GrantItem::insert($grantItems);
            }
        } catch (\Exception $e) {
            $errors[] = "Sheet '$sheetName': Error processing items - " . $e->getMessage();
            throw $e; // Re-throw to trigger transaction rollback
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
     *             required={"grant_id", "bg_line"},
     *             @OA\Property(property="grant_id", type="integer", example=1, description="ID of the existing grant"),
     *             @OA\Property(property="bg_line", type="string", example="BL-123", description="Budget line identifier"),
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
     *             @OA\Property(property="errors", type="object"),
     *             @OA\Property(property="error", type="string", example="Duplicate item with this BG Line already exists for this grant")
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
            'grant_id' => 'required|exists:grants,id',
            'bg_line' => 'required|string',
            'grant_position' => 'nullable|string',
            'grant_salary' => 'nullable|numeric',
            'grant_benefit' => 'nullable|numeric',
            'grant_level_of_effort' => 'nullable|numeric',
            'grant_position_number' => 'nullable|string',
        ]);

        // Check for duplicates
        $existingItem = GrantItem::where('grant_id', $request->grant_id)
            ->where('bg_line', $request->bg_line)
            ->exists();

        if ($existingItem) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate item with this BG Line already exists for this grant'
            ], 422);
        }

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
     *             @OA\Property(property="bg_line", type="string", example="BG-123"),
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
            'bg_line' => 'sometimes|required|string',
            'grant_position' => 'nullable|string',
            'grant_salary' => 'nullable|numeric',
            'grant_benefit' => 'nullable|numeric',
            'grant_level_of_effort' => 'nullable|numeric|min:0|max:100',
            'grant_position_number' => 'nullable|string',
        ]);

        // Check for duplicates if bg_line or grant_id is being changed
        if (($request->has('bg_line') && $request->bg_line !== $grantItem->bg_line) ||
            ($request->has('grant_id') && $request->grant_id !== $grantItem->grant_id)) {

            $existingItem = GrantItem::where('grant_id', $request->grant_id ?? $grantItem->grant_id)
                ->where('bg_line', $request->bg_line ?? $grantItem->bg_line)
                ->where('id', '!=', $id)
                ->exists();

            if ($existingItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid',
                    'errors' => [
                        'bg_line' => ['Duplicate item with this BG Line already exists for this grant']
                    ]
                ], 422);
            }
        }

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

    /**
     *
     * Grant Position Section
     *
     */

    /**
     * @OA\Get(
     *     path="/grants/grant-positions",
     *     operationId="getGrantStatistics",
     *     summary="Get grant statistics with position recruitment status",
     *     description="Retrieves statistics for all grants including position recruitment status",
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
     *                             @OA\Property(property="position", type="string", example="Researcher"),
     *                             @OA\Property(property="budget_line", type="string", example="BL-123"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access grant statistics"
     *     ),
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
            // Eager-load grantItems to minimize queries
            $grants = Grant::with('grantItems.employeeGrantAllocations')->orderBy('created_at', 'desc')->get();

            // If no grants exist, return an empty result
            if ($grants->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'message' => 'No grants found'
                ]);
            }

            $grantStats = [];

            foreach ($grants as $grant) {
                $totalPositions      = 0;  // Sum of all position_number across this grant
                $recruitedPositions  = 0;  // Sum of all currently hired employees
                $openPositions       = 0;  // Positions still open = totalPositions - recruitedPositions
                $grantPositions      = []; // Detailed breakdown of each position

                // Loop through each grant item to build statistics
                foreach ($grant->grantItems as $item) {
                    // position_number is how many people can fill this role
                    // grant_position (or position_title) is the name of the role
                    $positionTitle = $item->grant_position;
                    $manpower      = $item->grant_position_number ?? 0; // Adjust based on your column name
                    $budgetLine    = $item->bg_line; // Get budget line for each grant item

                    // Count how many active allocations are linked to this grant item
                    $activeAllocations = $item->employeeGrantAllocations()
                        ->where('active', true)
                        ->where(function ($query) {
                            $query->whereNull('end_date')
                                  ->orWhere('end_date', '>', now());
                        })
                        ->count();

                    // Update aggregates
                    $totalPositions     += $manpower;
                    $recruitedPositions += $activeAllocations;
                    $openPositions      += ($manpower - $activeAllocations);

                    // Add this position's breakdown
                    $grantPositions[] = [
                        'position'    => $positionTitle,
                        'budget_line' => $budgetLine,
                        'manpower'    => $manpower,
                        'recruited'   => $activeAllocations,
                        'finding'     => $manpower - $activeAllocations,
                    ];
                }

                // Determine overall grant status
                $status = 'Active';
                if ($grant->end_date && $grant->end_date < now()) {
                    $status = 'Completed';
                } elseif ($recruitedPositions == $totalPositions && $totalPositions > 0) {
                    $status = 'Completed';
                } elseif ($recruitedPositions == 0 && $totalPositions > 0) {
                    $status = 'Pending';
                }

                // Build the grant statistics array
                $grantStats[] = [
                    'grant_id'         => $grant->id,
                    'grant_code'       => $grant->code,
                    'grant_name'       => $grant->name,
                    'positions'        => $grantPositions,
                    'total_manpower'   => $totalPositions,
                    'total_recruited'  => $recruitedPositions,
                    'total_finding'    => $openPositions,
                    'status'           => $status,
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
