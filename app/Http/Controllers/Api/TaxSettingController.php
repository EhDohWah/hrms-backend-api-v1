<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Tax Settings",
 *     description="API Endpoints for managing tax settings"
 * )
 */
class TaxSettingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/tax-settings",
     *     summary="Get all tax settings",
     *     description="Get a list of all tax settings with optional filtering",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Filter by effective year",
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by setting type",
     *         @OA\Schema(type="string", enum={"DEDUCTION", "RATE", "LIMIT"})
     *     ),
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Show only active settings",
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax settings retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="setting_key", type="string", example="PERSONAL_ALLOWANCE"),
     *                     @OA\Property(property="setting_value", type="number", example=60000),
     *                     @OA\Property(property="setting_type", type="string", example="DEDUCTION"),
     *                     @OA\Property(property="description", type="string", example="Personal allowance for income tax"),
     *                     @OA\Property(property="effective_year", type="integer", example=2025),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = TaxSetting::query();
            
            // Filter by year if provided
            if ($request->has('year')) {
                $query->forYear($request->year);
            }
            
            // Filter by type if provided
            if ($request->has('type')) {
                $query->byType($request->type);
            }
            
            // Filter by active status
            if ($request->boolean('active_only', true)) {
                $query->active();
            }
            
            $taxSettings = $query->orderBy('setting_type')->orderBy('setting_key')->get();

            return response()->json([
                'success' => true,
                'message' => 'Tax settings retrieved successfully',
                'data' => $taxSettings
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-settings",
     *     summary="Create a new tax setting",
     *     description="Create a new tax setting",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"setting_key", "setting_value", "setting_type", "effective_year"},
     *             @OA\Property(property="setting_key", type="string", example="PERSONAL_ALLOWANCE"),
     *             @OA\Property(property="setting_value", type="number", example=60000),
     *             @OA\Property(property="setting_type", type="string", enum={"DEDUCTION", "RATE", "LIMIT"}, example="DEDUCTION"),
     *             @OA\Property(property="description", type="string", example="Personal allowance for income tax"),
     *             @OA\Property(property="effective_year", type="integer", example=2025),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tax setting created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax setting created successfully"),
     *             @OA\Property(property="data", type="object")
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
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'setting_key' => 'required|string|max:50|unique:tax_settings,setting_key',
                'setting_value' => 'required|numeric|min:0',
                'setting_type' => 'required|string|in:DEDUCTION,RATE,LIMIT',
                'description' => 'nullable|string|max:255',
                'effective_year' => 'required|integer|min:2000|max:2100',
                'is_active' => 'boolean',
                'created_by' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $taxSetting = TaxSetting::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tax setting created successfully',
                'data' => $taxSetting
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tax setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/tax-settings/{id}",
     *     summary="Get a specific tax setting",
     *     description="Get details of a specific tax setting by ID",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tax setting ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax setting retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax setting retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax setting not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tax setting not found")
     *         )
     *     )
     * )
     */
    public function show(string $id)
    {
        try {
            $taxSetting = TaxSetting::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Tax setting retrieved successfully',
                'data' => $taxSetting
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax setting not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/tax-settings/{id}",
     *     summary="Update a tax setting",
     *     description="Update an existing tax setting",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tax setting ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="setting_key", type="string", example="PERSONAL_ALLOWANCE"),
     *             @OA\Property(property="setting_value", type="number", example=65000),
     *             @OA\Property(property="setting_type", type="string", enum={"DEDUCTION", "RATE", "LIMIT"}, example="DEDUCTION"),
     *             @OA\Property(property="description", type="string", example="Updated personal allowance for income tax"),
     *             @OA\Property(property="effective_year", type="integer", example=2025),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="updated_by", type="string", example="admin@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax setting updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax setting updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax setting not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tax setting not found")
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
     *     )
     * )
     */
    public function update(Request $request, string $id)
    {
        try {
            $taxSetting = TaxSetting::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'setting_key' => 'sometimes|required|string|max:50|unique:tax_settings,setting_key,' . $id,
                'setting_value' => 'sometimes|required|numeric|min:0',
                'setting_type' => 'sometimes|required|string|in:DEDUCTION,RATE,LIMIT',
                'description' => 'nullable|string|max:255',
                'effective_year' => 'sometimes|required|integer|min:2000|max:2100',
                'is_active' => 'boolean',
                'updated_by' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $taxSetting->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tax setting updated successfully',
                'data' => $taxSetting
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax setting not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/tax-settings/{id}",
     *     summary="Delete a tax setting",
     *     description="Delete a specific tax setting",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tax setting ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax setting deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax setting deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax setting not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tax setting not found")
     *         )
     *     )
     * )
     */
    public function destroy(string $id)
    {
        try {
            $taxSetting = TaxSetting::findOrFail($id);
            $taxSetting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tax setting deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax setting not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tax setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/tax-settings/by-year/{year}",
     *     summary="Get all tax settings for a specific year",
     *     description="Get all tax settings grouped by type for a specific year",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="path",
     *         required=true,
     *         description="Tax year",
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax settings for year retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax settings retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="DEDUCTION",
     *                     type="object",
     *                     @OA\Property(property="PERSONAL_ALLOWANCE", type="number", example=60000),
     *                     @OA\Property(property="SPOUSE_ALLOWANCE", type="number", example=60000)
     *                 ),
     *                 @OA\Property(
     *                     property="RATE",
     *                     type="object",
     *                     @OA\Property(property="SSF_RATE", type="number", example=5)
     *                 ),
     *                 @OA\Property(
     *                     property="LIMIT",
     *                     type="object",
     *                     @OA\Property(property="SSF_MAX_MONTHLY", type="number", example=750)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getByYear(int $year)
    {
        try {
            $settings = TaxSetting::getSettingsForYear($year);

            return response()->json([
                'success' => true,
                'message' => 'Tax settings retrieved successfully',
                'data' => $settings,
                'year' => $year
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/tax-settings/value/{key}",
     *     summary="Get a specific tax setting value",
     *     description="Get the value of a specific tax setting by key",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Tax setting key",
     *         @OA\Schema(type="string", example="PERSONAL_ALLOWANCE")
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Tax year (defaults to current year)",
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax setting value retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax setting value retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="key", type="string", example="PERSONAL_ALLOWANCE"),
     *                 @OA\Property(property="value", type="number", example=60000),
     *                 @OA\Property(property="year", type="integer", example=2025)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax setting not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tax setting not found")
     *         )
     *     )
     * )
     */
    public function getValue(Request $request, string $key)
    {
        try {
            $year = $request->get('year', date('Y'));
            $value = TaxSetting::getValue($key, $year);

            if ($value === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tax setting not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax setting value retrieved successfully',
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'year' => $year
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax setting value',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-settings/bulk-update",
     *     summary="Bulk update tax settings",
     *     description="Update multiple tax settings at once",
     *     tags={"Tax Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"settings", "effective_year"},
     *             @OA\Property(property="effective_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="settings",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="setting_key", type="string", example="PERSONAL_ALLOWANCE"),
     *                     @OA\Property(property="setting_value", type="number", example=60000),
     *                     @OA\Property(property="setting_type", type="string", example="DEDUCTION"),
     *                     @OA\Property(property="description", type="string", example="Personal allowance")
     *                 )
     *             ),
     *             @OA\Property(property="updated_by", type="string", example="admin@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax settings updated successfully"),
     *             @OA\Property(property="updated_count", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function bulkUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'effective_year' => 'required|integer|min:2000|max:2100',
                'settings' => 'required|array|min:1',
                'settings.*.setting_key' => 'required|string|max:50',
                'settings.*.setting_value' => 'required|numeric|min:0',
                'settings.*.setting_type' => 'required|string|in:DEDUCTION,RATE,LIMIT',
                'settings.*.description' => 'nullable|string|max:255',
                'updated_by' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updatedCount = 0;
            $effectiveYear = $request->effective_year;
            $updatedBy = $request->updated_by;

            foreach ($request->settings as $settingData) {
                $setting = TaxSetting::updateOrCreate(
                    [
                        'setting_key' => $settingData['setting_key'],
                        'effective_year' => $effectiveYear
                    ],
                    [
                        'setting_value' => $settingData['setting_value'],
                        'setting_type' => $settingData['setting_type'],
                        'description' => $settingData['description'] ?? null,
                        'is_active' => true,
                        'updated_by' => $updatedBy
                    ]
                );
                $updatedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax settings updated successfully',
                'updated_count' => $updatedCount,
                'effective_year' => $effectiveYear
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}