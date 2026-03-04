<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\Tax\BulkUpdateTaxSettingRequest;
use App\Http\Requests\Tax\ListTaxSettingsRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Http\Resources\TaxSettingResource;
use App\Models\TaxSetting;
use App\Services\TaxSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Handles CRUD and bulk operations for tax configuration settings.
 */
#[OA\Tag(name: 'Tax Settings', description: 'API Endpoints for managing tax configuration settings')]
class TaxSettingController extends BaseApiController
{
    public function __construct(
        private readonly TaxSettingService $taxSettingService,
    ) {}

    /**
     * Get all tax settings with advanced filtering and pagination.
     */
    #[OA\Get(
        path: '/tax-settings',
        summary: 'List all tax settings with filtering and pagination',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'filter_setting_type', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_effective_year', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_is_selected', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_order', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax settings retrieved successfully'),
        ]
    )]
    public function index(ListTaxSettingsRequest $request): JsonResponse
    {
        $result = $this->taxSettingService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tax settings retrieved successfully',
            'data' => TaxSettingResource::collection($result['paginator']->items()),
            'pagination' => [
                'current_page' => $result['paginator']->currentPage(),
                'per_page' => $result['paginator']->perPage(),
                'total' => $result['paginator']->total(),
                'last_page' => $result['paginator']->lastPage(),
                'from' => $result['paginator']->firstItem(),
                'to' => $result['paginator']->lastItem(),
                'has_more_pages' => $result['paginator']->hasMorePages(),
            ],
            'filters' => ['applied_filters' => $result['applied_filters']],
            'meta' => [
                'total_count' => $result['total_count'],
                'filtered_count' => $result['paginator']->total(),
            ],
        ]);
    }

    /**
     * Create a new tax setting.
     */
    #[OA\Post(
        path: '/tax-settings',
        summary: 'Create a new tax setting',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['setting_key', 'setting_value', 'setting_type', 'effective_year'],
                properties: [
                    new OA\Property(property: 'setting_key', type: 'string', example: 'PERSONAL_ALLOWANCE'),
                    new OA\Property(property: 'setting_value', type: 'number', example: 60000),
                    new OA\Property(property: 'setting_type', type: 'string', example: 'DEDUCTION'),
                    new OA\Property(property: 'effective_year', type: 'integer', example: 2025),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tax setting created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreTaxSettingRequest $request): JsonResponse
    {
        $taxSetting = $this->taxSettingService->store($request->validated());

        return TaxSettingResource::make($taxSetting)
            ->additional(['success' => true, 'message' => 'Tax setting created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a specific tax setting.
     */
    #[OA\Get(
        path: '/tax-settings/{taxSetting}',
        summary: 'Get a specific tax setting',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'taxSetting', in: 'path', required: true, description: 'Tax Setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting retrieved successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function show(TaxSetting $taxSetting): JsonResponse
    {
        $taxSetting = $this->taxSettingService->show($taxSetting);

        return TaxSettingResource::make($taxSetting)
            ->additional(['success' => true, 'message' => 'Tax setting retrieved successfully'])
            ->response();
    }

    /**
     * Update an existing tax setting.
     */
    #[OA\Put(
        path: '/tax-settings/{taxSetting}',
        summary: 'Update a tax setting',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'taxSetting', in: 'path', required: true, description: 'Tax Setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'setting_value', type: 'number', example: 70000),
                    new OA\Property(property: 'setting_type', type: 'string', example: 'DEDUCTION'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tax setting updated successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting): JsonResponse
    {
        $taxSetting = $this->taxSettingService->update($taxSetting, $request->validated());

        return TaxSettingResource::make($taxSetting)
            ->additional(['success' => true, 'message' => 'Tax setting updated successfully'])
            ->response();
    }

    /**
     * Delete a specific tax setting.
     */
    #[OA\Delete(
        path: '/tax-settings/{taxSetting}',
        summary: 'Delete a tax setting',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'taxSetting', in: 'path', required: true, description: 'Tax Setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting deleted successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function destroy(TaxSetting $taxSetting): JsonResponse
    {
        $this->taxSettingService->destroy($taxSetting);

        return $this->successResponse(null, 'Tax setting deleted successfully');
    }

    /**
     * Get all tax settings for a specific year.
     */
    #[OA\Get(
        path: '/tax-settings/by-year/{year}',
        summary: 'Get all tax settings for a specific year',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'year', in: 'path', required: true, description: 'Effective year', schema: new OA\Schema(type: 'integer', example: 2025)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax settings retrieved successfully'),
        ]
    )]
    public function byYear(int $year): JsonResponse
    {
        $settings = $this->taxSettingService->byYear($year);

        return response()->json([
            'success' => true,
            'message' => 'Tax settings retrieved successfully',
            'data' => $settings,
            'year' => $year,
        ]);
    }

    /**
     * Get a specific tax setting value by key.
     */
    #[OA\Get(
        path: '/tax-settings/value/{key}',
        summary: 'Get a specific tax setting value by key',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, description: 'Setting key', schema: new OA\Schema(type: 'string', example: 'PERSONAL_ALLOWANCE')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Effective year', schema: new OA\Schema(type: 'integer', example: 2025)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting value retrieved successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function value(Request $request, string $key): JsonResponse
    {
        $year = (int) $request->get('year', date('Y'));
        $result = $this->taxSettingService->value($key, $year);

        return response()->json([
            'success' => true,
            'message' => 'Tax setting value retrieved successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get all allowed tax setting keys organized by category.
     */
    #[OA\Get(
        path: '/tax-settings/allowed-keys',
        summary: 'Get all allowed tax setting keys',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Allowed keys retrieved successfully'),
        ]
    )]
    public function allowedKeys(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Allowed keys retrieved successfully',
            'data' => $this->taxSettingService->allowedKeys(),
        ]);
    }

    /**
     * Bulk update multiple tax settings at once.
     */
    #[OA\Post(
        path: '/tax-settings/bulk-update',
        summary: 'Bulk update multiple tax settings',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['effective_year', 'settings'],
                properties: [
                    new OA\Property(property: 'effective_year', type: 'integer', example: 2025),
                    new OA\Property(property: 'settings', type: 'array', items: new OA\Items(type: 'object')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tax settings updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function bulkUpdate(BulkUpdateTaxSettingRequest $request): JsonResponse
    {
        $result = $this->taxSettingService->bulkUpdate($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tax settings updated successfully',
            'updated_count' => $result['updated_count'],
            'effective_year' => $result['effective_year'],
        ]);
    }

    /**
     * Toggle the is_selected status of a tax setting.
     */
    #[OA\Patch(
        path: '/tax-settings/{taxSetting}/toggle',
        summary: 'Toggle tax setting selection status',
        tags: ['Tax Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'taxSetting', in: 'path', required: true, description: 'Tax Setting ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax setting toggled successfully'),
            new OA\Response(response: 404, description: 'Tax setting not found'),
        ]
    )]
    public function toggleSelection(TaxSetting $taxSetting): JsonResponse
    {
        $result = $this->taxSettingService->toggleSelection($taxSetting);

        return response()->json([
            'success' => true,
            'message' => 'Tax setting toggled successfully',
            'data' => TaxSettingResource::make($result['setting']),
            'status' => $result['status'],
            'previous_status' => $result['previous_status'],
        ]);
    }
}
