<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreGrantItemRequest;
use App\Http\Requests\UpdateGrantItemRequest;
use App\Http\Resources\GrantItemResource;
use App\Models\GrantItem;
use App\Services\GrantItemService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Grant Items', description: 'API Endpoints for managing grant items')]
/**
 * Handles CRUD operations for grant item records.
 */
class GrantItemController extends BaseApiController
{
    public function __construct(
        private readonly GrantItemService $grantItemService,
    ) {}

    /**
     * List all grant items.
     */
    #[OA\Get(
        path: '/grant-items',
        summary: 'List all grant items',
        tags: ['Grant Items'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Grant items retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Grant items retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/GrantItem')
                        ),
                        new OA\Property(property: 'count', type: 'integer', example: 10),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $grantItems = $this->grantItemService->list();

        return GrantItemResource::collection($grantItems)
            ->additional([
                'success' => true,
                'message' => 'Grant items retrieved successfully',
                'count' => $grantItems->count(),
            ])
            ->response();
    }

    /**
     * Get a specific grant item by ID.
     */
    #[OA\Get(
        path: '/grant-items/{id}',
        summary: 'Get a specific grant item by ID',
        tags: ['Grant Items'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Grant item ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Grant item not found'),
        ]
    )]
    public function show(GrantItem $grantItem): JsonResponse
    {
        $grantItem = $this->grantItemService->show($grantItem);

        return GrantItemResource::make($grantItem)
            ->additional(['success' => true, 'message' => 'Grant item retrieved successfully'])
            ->response();
    }

    /**
     * Create a new grant item.
     */
    #[OA\Post(
        path: '/grant-items',
        summary: 'Store a new grant item',
        tags: ['Grant Items'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['grant_id'],
                properties: [
                    new OA\Property(property: 'grant_id', type: 'integer', example: 1),
                    new OA\Property(property: 'grant_position', type: 'string', example: 'Project Manager'),
                    new OA\Property(property: 'grant_salary', type: 'number', example: 75000),
                    new OA\Property(property: 'grant_benefit', type: 'number', example: 15000),
                    new OA\Property(property: 'grant_level_of_effort', type: 'number', example: 0.75),
                    new OA\Property(property: 'grant_position_number', type: 'integer', example: 2),
                    new OA\Property(property: 'budgetline_code', type: 'string', example: 'BL001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Grant item created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreGrantItemRequest $request): JsonResponse
    {
        $grantItem = $this->grantItemService->create($request->validated());

        return GrantItemResource::make($grantItem)
            ->additional(['success' => true, 'message' => 'Grant item created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing grant item.
     */
    #[OA\Put(
        path: '/grant-items/{id}',
        summary: 'Update a grant item',
        tags: ['Grant Items'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Grant item ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'grant_id', type: 'integer', example: 1),
                    new OA\Property(property: 'grant_position', type: 'string', example: 'Project Manager'),
                    new OA\Property(property: 'grant_salary', type: 'number', example: 5000),
                    new OA\Property(property: 'grant_benefit', type: 'number', example: 1000),
                    new OA\Property(property: 'grant_level_of_effort', type: 'number', example: 0.75),
                    new OA\Property(property: 'grant_position_number', type: 'integer', example: 2),
                    new OA\Property(property: 'budgetline_code', type: 'string', example: 'BL001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Grant item updated successfully'),
            new OA\Response(response: 404, description: 'Grant item not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateGrantItemRequest $request, GrantItem $grantItem): JsonResponse
    {
        $grantItem = $this->grantItemService->update($grantItem, $request->validated());

        return GrantItemResource::make($grantItem)
            ->additional(['success' => true, 'message' => 'Grant item updated successfully'])
            ->response();
    }

    /**
     * Delete a grant item.
     */
    #[OA\Delete(
        path: '/grant-items/{id}',
        summary: 'Delete a grant item',
        tags: ['Grant Items'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Grant item ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Grant item deleted successfully'),
            new OA\Response(response: 404, description: 'Grant item not found'),
        ]
    )]
    public function destroy(GrantItem $grantItem): JsonResponse
    {
        $this->grantItemService->delete($grantItem);

        return $this->successResponse(null, 'Grant item deleted successfully');
    }
}
