<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Http\Resources\TaxSettingResource;
use App\Models\TaxSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Tax Settings', description: 'API Endpoints for managing tax settings')]
class TaxSettingController extends Controller
{
    #[OA\Get(
        path: '/tax-settings',
        summary: 'Get all tax settings with advanced filtering and pagination',
        description: 'Get a paginated list of all tax settings with advanced filtering, sorting, and search capabilities',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
            new OA\Parameter(name: 'filter_setting_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_effective_year', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_is_selected', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax settings retrieved successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_setting_type' => 'string|nullable',
                'filter_effective_year' => 'string|nullable',
                'filter_is_selected' => 'nullable|in:true,false,1,0',
                'sort_by' => 'string|nullable|in:setting_key,setting_value,setting_type,effective_year',
                'sort_order' => 'string|nullable|in:asc,desc',
                'search' => 'string|nullable',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Get total count before filtering for meta
            $totalCount = TaxSetting::count();

            // Build query
            $query = TaxSetting::query();

            // Apply search filter
            if (! empty($validated['search'])) {
                $query->where('setting_key', 'LIKE', '%'.$validated['search'].'%');
            }

            // Apply setting type filter
            if (! empty($validated['filter_setting_type'])) {
                $types = explode(',', $validated['filter_setting_type']);
                $query->whereIn('setting_type', $types);
            }

            // Apply effective year filter
            if (! empty($validated['filter_effective_year'])) {
                $years = explode(',', $validated['filter_effective_year']);
                $years = array_map('intval', $years); // Convert to integers
                $query->whereIn('effective_year', $years);
            }

            // Apply is_selected filter
            if (isset($validated['filter_is_selected'])) {
                $isSelected = filter_var($validated['filter_is_selected'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isSelected !== null) {
                    $query->where('is_selected', $isSelected);
                }
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'setting_type';
            $sortOrder = $validated['sort_order'] ?? 'asc';

            if (in_array($sortBy, ['setting_key', 'setting_value', 'setting_type', 'effective_year'])) {
                $query->orderBy($sortBy, $sortOrder);
                // Add secondary sort for consistency
                if ($sortBy !== 'setting_key') {
                    $query->orderBy('setting_key', 'asc');
                }
            } else {
                $query->orderBy('setting_type', 'asc')->orderBy('setting_key', 'asc');
            }

            // Execute pagination
            $taxSettings = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_setting_type'])) {
                $appliedFilters['setting_type'] = explode(',', $validated['filter_setting_type']);
            }
            if (! empty($validated['filter_effective_year'])) {
                $appliedFilters['effective_year'] = array_map('intval', explode(',', $validated['filter_effective_year']));
            }
            if (isset($validated['filter_is_selected'])) {
                $appliedFilters['is_selected'] = filter_var($validated['filter_is_selected'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax settings retrieved successfully',
                'data' => TaxSettingResource::collection($taxSettings->items()),
                'pagination' => [
                    'current_page' => $taxSettings->currentPage(),
                    'per_page' => $taxSettings->perPage(),
                    'total' => $taxSettings->total(),
                    'last_page' => $taxSettings->lastPage(),
                    'from' => $taxSettings->firstItem(),
                    'to' => $taxSettings->lastItem(),
                    'has_more_pages' => $taxSettings->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
                'meta' => [
                    'total_count' => $totalCount,
                    'filtered_count' => $taxSettings->total(),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/tax-settings',
        summary: 'Create a new tax setting',
        description: 'Create a new tax setting',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TaxSetting')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tax setting created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreTaxSettingRequest $request)
    {
        try {
            $taxSetting = TaxSetting::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tax setting created successfully',
                'data' => $taxSetting,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tax setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/tax-settings/{id}',
        summary: 'Get a specific tax setting',
        description: 'Get details of a specific tax setting by ID',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Tax setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting retrieved successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function show(string $id)
    {
        try {
            $taxSetting = TaxSetting::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Tax setting retrieved successfully',
                'data' => $taxSetting,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax setting not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/tax-settings/{id}',
        summary: 'Update a tax setting',
        description: 'Update an existing tax setting',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Tax setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TaxSetting')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tax setting updated successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateTaxSettingRequest $request, string $id)
    {
        try {
            $taxSetting = TaxSetting::findOrFail($id);
            $taxSetting->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tax setting updated successfully',
                'data' => $taxSetting,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax setting not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/tax-settings/{id}',
        summary: 'Delete a tax setting',
        description: 'Delete a specific tax setting',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Tax setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting deleted successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function destroy(string $id)
    {
        try {
            $taxSetting = TaxSetting::findOrFail($id);
            $taxSetting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tax setting deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax setting not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tax setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/tax-settings/by-year/{year}',
        summary: 'Get all tax settings for a specific year',
        description: 'Get all tax settings grouped by type for a specific year',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'year', in: 'path', required: true, description: 'Tax year', schema: new OA\Schema(type: 'integer', example: 2025)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax settings for year retrieved successfully'),
        ]
    )]
    public function getByYear(int $year)
    {
        try {
            $settings = TaxSetting::getSettingsForYear($year);

            return response()->json([
                'success' => true,
                'message' => 'Tax settings retrieved successfully',
                'data' => $settings,
                'year' => $year,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/tax-settings/value/{key}',
        summary: 'Get a specific tax setting value',
        description: 'Get the value of a specific tax setting by key',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, description: 'Tax setting key', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Tax year', schema: new OA\Schema(type: 'integer', example: 2025)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting value retrieved successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function getValue(Request $request, string $key)
    {
        try {
            $year = $request->get('year', date('Y'));
            $value = TaxSetting::getValue($key, $year);

            if ($value === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tax setting not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax setting value retrieved successfully',
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'year' => $year,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax setting value',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/tax-settings/allowed-keys',
        summary: 'Get all allowed tax setting keys',
        description: 'Get all allowed tax setting keys organized by category',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Allowed keys retrieved successfully'),
        ]
    )]
    public function getAllowedKeys()
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Allowed keys retrieved successfully',
                'data' => [
                    'all_keys' => TaxSetting::getAllowedKeys(),
                    'by_category' => TaxSetting::getKeysByCategory(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve allowed keys',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/tax-settings/bulk-update',
        summary: 'Bulk update tax settings',
        description: 'Update multiple tax settings at once',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tax settings updated successfully'),
        ]
    )]
    public function bulkUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'effective_year' => 'required|integer|min:2000|max:2100',
                'settings' => 'required|array|min:1',
                'settings.*.setting_key' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::in(TaxSetting::getAllowedKeys()),
                ],
                'settings.*.setting_value' => 'required|numeric|min:0',
                'settings.*.setting_type' => 'required|string|in:DEDUCTION,RATE,LIMIT',
                'settings.*.description' => 'nullable|string|max:255',
                'updated_by' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $updatedCount = 0;
            $effectiveYear = $request->effective_year;
            $updatedBy = $request->updated_by;

            foreach ($request->settings as $settingData) {
                $setting = TaxSetting::updateOrCreate(
                    [
                        'setting_key' => $settingData['setting_key'],
                        'effective_year' => $effectiveYear,
                    ],
                    [
                        'setting_value' => $settingData['setting_value'],
                        'setting_type' => $settingData['setting_type'],
                        'description' => $settingData['description'] ?? null,
                        'is_selected' => true,
                        'updated_by' => $updatedBy,
                    ]
                );
                $updatedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax settings updated successfully',
                'updated_count' => $updatedCount,
                'effective_year' => $effectiveYear,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Patch(
        path: '/tax-settings/{id}/toggle',
        summary: 'Toggle tax setting selection status',
        description: 'Toggle the is_selected status of a tax setting for global control',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Tax setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting toggled successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function toggleSelection($id)
    {
        try {
            $taxSetting = TaxSetting::findOrFail($id);
            $oldStatus = $taxSetting->is_selected;

            $taxSetting->update([
                'is_selected' => ! $taxSetting->is_selected,
                'updated_by' => auth()->user()->name ?? 'System',
            ]);

            // Clear tax calculation cache immediately
            try {
                Cache::tags(['tax_calculations'])->flush();
            } catch (\BadMethodCallException $e) {
                // Fallback for cache drivers that don't support tagging
                Cache::flush();
            }

            // Log for audit trail
            Log::info('Tax setting toggled', [
                'setting_id' => $taxSetting->id,
                'setting_key' => $taxSetting->setting_key,
                'old_status' => $oldStatus ? 'enabled' : 'disabled',
                'new_status' => $taxSetting->is_selected ? 'enabled' : 'disabled',
                'user' => auth()->user()->name ?? 'System',
                'effective_year' => $taxSetting->effective_year,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tax setting toggled successfully',
                'data' => new TaxSettingResource($taxSetting),
                'status' => $taxSetting->is_selected ? 'enabled' : 'disabled',
                'previous_status' => $oldStatus ? 'enabled' : 'disabled',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle tax setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
