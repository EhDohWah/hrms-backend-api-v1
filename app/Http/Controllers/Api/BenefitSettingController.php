<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BenefitSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Benefit Settings",
 *     description="API Endpoints for managing global benefit settings (percentages, rates, etc.)"
 * )
 */
class BenefitSettingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/benefit-settings",
     *     summary="Get all benefit settings",
     *     description="Get a list of all benefit settings with optional filtering",
     *     tags={"Benefit Settings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="filter_is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_setting_type",
     *         in="query",
     *         description="Filter by setting type (percentage, boolean, numeric)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="percentage")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Benefit settings retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Benefit settings retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BenefitSetting"))
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BenefitSetting::query();

            // Filter by is_active
            if ($request->has('filter_is_active')) {
                $query->where('is_active', filter_var($request->filter_is_active, FILTER_VALIDATE_BOOLEAN));
            }

            // Filter by setting_type
            if ($request->filled('filter_setting_type')) {
                $query->where('setting_type', $request->filter_setting_type);
            }

            // Order by setting_key
            $query->orderBy('setting_key', 'asc');

            $settings = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Benefit settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve benefit settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/benefit-settings",
     *     summary="Create a new benefit setting",
     *     description="Create a new benefit setting",
     *     tags={"Benefit Settings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"setting_key","setting_value","setting_type"},
     *
     *             @OA\Property(property="setting_key", type="string", example="health_welfare_percentage"),
     *             @OA\Property(property="setting_value", type="number", format="float", example=5.00),
     *             @OA\Property(property="setting_type", type="string", example="percentage"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Health & Welfare percentage deduction"),
     *             @OA\Property(property="effective_date", type="string", format="date", nullable=true, example="2025-01-01"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="applies_to", type="object", nullable=true, example={"organization": "SMRU"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Benefit setting created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Benefit setting created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BenefitSetting")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'setting_key' => 'required|string|unique:benefit_settings,setting_key|max:255',
                'setting_value' => 'required|numeric',
                'setting_type' => 'required|string|in:percentage,boolean,numeric',
                'description' => 'nullable|string',
                'effective_date' => 'nullable|date',
                'is_active' => 'nullable|boolean',
                'applies_to' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['created_by'] = Auth::user()?->name ?? 'system';
            $data['updated_by'] = Auth::user()?->name ?? 'system';

            $setting = BenefitSetting::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Benefit setting created successfully',
                'data' => $setting,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create benefit setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/benefit-settings/{id}",
     *     summary="Get a specific benefit setting",
     *     description="Get details of a specific benefit setting by ID",
     *     tags={"Benefit Settings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Benefit Setting ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Benefit setting retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Benefit setting retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BenefitSetting")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Benefit setting not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $setting = BenefitSetting::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Benefit setting retrieved successfully',
                'data' => $setting,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Benefit setting not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/benefit-settings/{id}",
     *     summary="Update a benefit setting",
     *     description="Update an existing benefit setting",
     *     tags={"Benefit Settings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Benefit Setting ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="setting_value", type="number", format="float", example=6.00),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated description"),
     *             @OA\Property(property="effective_date", type="string", format="date", nullable=true, example="2025-02-01"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Benefit setting updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Benefit setting updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BenefitSetting")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Benefit setting not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $setting = BenefitSetting::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'setting_key' => 'sometimes|string|unique:benefit_settings,setting_key,'.$id.'|max:255',
                'setting_value' => 'sometimes|numeric',
                'setting_type' => 'sometimes|string|in:percentage,boolean,numeric',
                'description' => 'nullable|string',
                'effective_date' => 'nullable|date',
                'is_active' => 'nullable|boolean',
                'applies_to' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['updated_by'] = Auth::user()?->name ?? 'system';

            $setting->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Benefit setting updated successfully',
                'data' => $setting->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update benefit setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/benefit-settings/{id}",
     *     summary="Delete a benefit setting",
     *     description="Soft delete a benefit setting",
     *     tags={"Benefit Settings"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Benefit Setting ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Benefit setting deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Benefit setting deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Benefit setting not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $setting = BenefitSetting::findOrFail($id);
            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Benefit setting deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete benefit setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
