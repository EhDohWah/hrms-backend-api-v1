<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BenefitSetting\IndexBenefitSettingRequest;
use App\Http\Requests\BenefitSetting\StoreBenefitSettingRequest;
use App\Http\Requests\BenefitSetting\UpdateBenefitSettingRequest;
use App\Http\Resources\BenefitSettingResource;
use App\Models\BenefitSetting;
use App\Services\BenefitSettingService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Benefit Settings', description: 'API Endpoints for managing global benefit settings (percentages, rates, etc.)')]
/**
 * Manages global benefit settings such as percentages, rates, and categories.
 */
class BenefitSettingController extends BaseApiController
{
    public function __construct(
        private readonly BenefitSettingService $benefitSettingService,
    ) {}

    /**
     * Get all benefit settings with optional filtering.
     */
    #[OA\Get(
        path: '/benefit-settings',
        summary: 'Get all benefit settings',
        description: 'Get a list of all benefit settings with optional filtering',
        tags: ['Benefit Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'filter_is_active', in: 'query', required: false, description: 'Filter by active status', schema: new OA\Schema(type: 'boolean', example: true)),
            new OA\Parameter(name: 'filter_setting_type', in: 'query', required: false, description: 'Filter by setting type', schema: new OA\Schema(type: 'string', example: 'percentage')),
            new OA\Parameter(name: 'filter_category', in: 'query', required: false, description: 'Filter by category', schema: new OA\Schema(type: 'string', example: 'social_security')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Benefit settings retrieved successfully'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(IndexBenefitSettingRequest $request): JsonResponse
    {
        $settings = $this->benefitSettingService->list($request->validated());

        return BenefitSettingResource::collection($settings)
            ->additional([
                'success' => true,
                'message' => 'Benefit settings retrieved successfully',
                'categories' => BenefitSetting::getCategories(),
            ])
            ->response();
    }

    /**
     * Get a specific benefit setting by ID.
     */
    #[OA\Get(
        path: '/benefit-settings/{id}',
        summary: 'Get a specific benefit setting',
        description: 'Get details of a specific benefit setting by ID',
        tags: ['Benefit Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Benefit Setting ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Benefit setting retrieved successfully'),
            new OA\Response(response: 404, description: 'Benefit setting not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function show(BenefitSetting $benefitSetting): JsonResponse
    {
        $benefitSetting = $this->benefitSettingService->show($benefitSetting);

        return BenefitSettingResource::make($benefitSetting)
            ->additional(['success' => true, 'message' => 'Benefit setting retrieved successfully'])
            ->response();
    }

    /**
     * Create a new benefit setting.
     */
    #[OA\Post(
        path: '/benefit-settings',
        summary: 'Create a new benefit setting',
        description: 'Create a new benefit setting',
        tags: ['Benefit Settings'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BenefitSetting')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Benefit setting created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function store(StoreBenefitSettingRequest $request): JsonResponse
    {
        $setting = $this->benefitSettingService->create($request->validated());

        return BenefitSettingResource::make($setting)
            ->additional(['success' => true, 'message' => 'Benefit setting created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing benefit setting.
     */
    #[OA\Put(
        path: '/benefit-settings/{id}',
        summary: 'Update a benefit setting',
        description: 'Update an existing benefit setting',
        tags: ['Benefit Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Benefit Setting ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BenefitSetting')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Benefit setting updated successfully'),
            new OA\Response(response: 404, description: 'Benefit setting not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function update(UpdateBenefitSettingRequest $request, BenefitSetting $benefitSetting): JsonResponse
    {
        $benefitSetting = $this->benefitSettingService->update($benefitSetting, $request->validated());

        return BenefitSettingResource::make($benefitSetting)
            ->additional(['success' => true, 'message' => 'Benefit setting updated successfully'])
            ->response();
    }

    /**
     * Delete a benefit setting (soft delete).
     */
    #[OA\Delete(
        path: '/benefit-settings/{id}',
        summary: 'Delete a benefit setting',
        description: 'Soft delete a benefit setting',
        tags: ['Benefit Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Benefit Setting ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Benefit setting deleted successfully'),
            new OA\Response(response: 404, description: 'Benefit setting not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function destroy(BenefitSetting $benefitSetting): JsonResponse
    {
        $this->benefitSettingService->delete($benefitSetting);

        return $this->successResponse(null, 'Benefit setting deleted successfully');
    }
}
