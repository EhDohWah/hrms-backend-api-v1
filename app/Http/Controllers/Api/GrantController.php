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

/**
 * @OA\Tag(
 *     name="Grants",
 *     description="API Endpoints for managing grants"
 * )
 */
class GrantController extends Controller
{
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
                'message' => 'Grant data import completed',
                'processed_grants' => $processedGrants,
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            if (!empty($skippedGrants)) {
                $response['skipped_grants'] = $skippedGrants;
                $response['message'] = 'Grant data import completed with skipped grants';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
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
     *                             @OA\Property(property="grant_cost_by_monthly", type="number", format="float", example=7500),
     *                             @OA\Property(property="grant_total_amount", type="number", format="float", example=90000),
     *                             @OA\Property(property="grant_total_cost_by_person", type="number", format="float", example=90000)
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
                    $query->select('id', 'grant_id', 'bg_line', 'grant_position', 'grant_salary', 'grant_benefit', 'grant_level_of_effort', 'grant_position_number', 'grant_cost_by_monthly', 'grant_total_amount', 'grant_total_cost_by_person');
                    // No need for position relationship now
                }
            ])
            ->get(['id', 'code', 'name', 'end_date']); // Select only necessary columns from the Grant table

        return response()->json([
            'status' => 'success',
            'message' => 'Grants retrieved successfully',
            'data' => $grants,
            'count' => $grants->count()
        ], 200);
    }


    private function createGrant(array $data, string $sheetName, array &$errors)
    {
        try {
            $grantName = trim(str_replace('Grant name -', '', $data[1]['A'] ?? ''));
            $grantCode = trim(str_replace('Grant code -', '', $data[2]['A'] ?? ''));
            $endDate = null;

            // Try to extract end date if available
            if (isset($data[3]['A'])) {
                $endDateStr = trim(str_replace('End date -', '', $data[3]['A'] ?? ''));
                if (!empty($endDateStr)) {
                    try {
                        $endDate = \Carbon\Carbon::parse($endDateStr)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Sheet '$sheetName': Invalid end date format - " . $endDateStr;
                    }
                }
            }

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
            for ($i = 4; $i < count($data); $i++) {
                $row = $data[$i];
                $bgLine = $row['A'] ?? null;

                // Validate row
                if (!is_numeric(trim(str_replace(',', '', $bgLine)))) {
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

                // Prepare item data
                $grantItems[] = [
                    'grant_id' => $grant->id,
                    'bg_line' => $bgLine,
                    'grant_position' => $row['B'] ?? null,
                    'grant_salary' => $this->toFloat($row['C']),
                    'grant_benefit' => $this->toFloat($row['D']),
                    'grant_level_of_effort' => isset($row['E']) ? trim(str_replace('%', '', $row['E'])) : null,
                    'grant_position_number' => $row['F'] ?? null,
                    'grant_cost_by_monthly' => $this->toFloat($row['G']),
                    'grant_total_amount' => $this->toFloat($row['H']),
                    'grant_total_cost_by_person' => $this->toFloat($row['I']),
                    'position_id' => $row['J'] ?? null,
                    'created_by' => auth()->user()->name ?? 'system',
                    'updated_by' => auth()->user()->name ?? 'system',
                ];
            }

            if (!empty($grantItems)) {
                GrantItem::insert($grantItems);
                $itemsProcessed = count($grantItems);
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


    // create a function to store a new grant on the database by code and name
    /**
     * @OA\Post(
     *     path="/grants",
     *     summary="Create a new grant",
     *     description="Store a new grant with the provided details",
     *     tags={"Grants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name"},
     *             @OA\Property(property="code", type="string", example="GR-2023-001"),
     *             @OA\Property(property="name", type="string", example="Health Initiative Grant"),
     *             @OA\Property(property="budget_line", type="string", example="BL-123", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Grant created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Grant")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function storeGrant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:grants,code',
            'name' => 'required|string|max:255',
            'budget_line' => 'nullable|string|max:255',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
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
                'message' => 'Grant created successfully',
                'data' => $grant
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
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
     *             @OA\Property(property="grant_cost_by_monthly", type="number", format="float", example=7500, description="Monthly cost"),
     *             @OA\Property(property="grant_total_cost_by_person", type="number", format="float", example=90000, description="Total cost per person"),
     *             @OA\Property(property="grant_total_amount", type="number", format="float", example=90000, description="Total grant amount"),
     *             @OA\Property(property="position_id", type="string", example="P123", description="Position reference ID")
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
            'grant_cost_by_monthly' => 'nullable|numeric',
            'grant_total_amount' => 'nullable|numeric',
            'grant_total_cost_by_person' => 'nullable|numeric',
            'position_id' => 'nullable|string',
        ]);

        // Check for duplicates
        $existingItem = GrantItem::where('grant_id', $request->grant_id)
            ->where('bg_line', $request->bg_line)
            ->exists();

        if ($existingItem) {
            return response()->json([
                'error' => 'Duplicate item with this BG Line already exists for this grant'
            ], 422);
        }

        // Add user info
        $data = $request->all();
        $data['created_by'] = auth()->user()->name ?? 'system';
        $data['updated_by'] = auth()->user()->name ?? 'system';

        $grantItem = GrantItem::create($data);
        return response()->json($grantItem, 201);
    }


}
