<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\IndexPositionRequest;
use App\Http\Requests\OptionsPositionRequest;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Http\Resources\PositionDetailResource;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use App\Services\PositionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Manages organizational positions including hierarchy and reporting structure.
 */
#[OA\Tag(name: 'Positions', description: 'API Endpoints for Position management')]
class PositionController extends BaseApiController
{
    public function __construct(
        private readonly PositionService $positionService,
    ) {}

    #[OA\Get(
        path: '/positions/options',
        summary: 'Get position options (lightweight)',
        description: 'Returns minimal position list for dropdowns, optionally filtered by department',
        operationId: 'getPositionOptions',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'department_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000, default: 200))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function options(OptionsPositionRequest $request): JsonResponse
    {
        $positions = $this->positionService->options($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Position options retrieved successfully',
            'data' => $positions,
        ]);
    }

    #[OA\Get(
        path: '/positions',
        summary: 'Get all positions',
        description: 'Returns a paginated list of positions with optional filtering and search',
        operationId: 'getPositions',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'department_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'is_manager', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'level', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index(IndexPositionRequest $request): JsonResponse
    {
        $paginator = $this->positionService->list($request->validated());

        return PositionResource::collection($paginator)
            ->additional(['success' => true, 'message' => 'Positions retrieved successfully'])
            ->response();
    }

    #[OA\Post(
        path: '/positions',
        summary: 'Create a new position',
        description: 'Creates a new position and returns it',
        operationId: 'storePosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'department_id'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'department_id', type: 'integer'),
                new OA\Property(property: 'reports_to_position_id', type: 'integer', nullable: true),
                new OA\Property(property: 'level', type: 'integer', minimum: 1, default: 1),
                new OA\Property(property: 'is_manager', type: 'boolean', default: false),
                new OA\Property(property: 'is_active', type: 'boolean', default: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Position created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = $this->positionService->create($request->validated());

        return PositionResource::make($position)
            ->additional(['success' => true, 'message' => 'Position created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/positions/{id}',
        summary: 'Get a specific position',
        description: 'Returns a specific position by ID with detailed information',
        operationId: 'getPosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Position not found')]
    public function show(Position $position): JsonResponse
    {
        $position = $this->positionService->show($position);

        return PositionDetailResource::make($position)
            ->additional(['success' => true, 'message' => 'Position retrieved successfully'])
            ->response();
    }

    #[OA\Put(
        path: '/positions/{id}',
        summary: 'Update a position',
        description: 'Updates a position and returns it',
        operationId: 'updatePosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'department_id', type: 'integer'),
                new OA\Property(property: 'reports_to_position_id', type: 'integer', nullable: true),
                new OA\Property(property: 'level', type: 'integer', minimum: 1),
                new OA\Property(property: 'is_manager', type: 'boolean'),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Position updated successfully')]
    #[OA\Response(response: 404, description: 'Position not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $position = $this->positionService->update($position, $request->validated());

        return PositionResource::make($position)
            ->additional(['success' => true, 'message' => 'Position updated successfully'])
            ->response();
    }

    #[OA\Delete(
        path: '/positions/{id}',
        summary: 'Delete a position',
        description: 'Deletes a position (deactivates if has subordinates, hard delete if empty)',
        operationId: 'deletePosition',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Position deleted successfully')]
    #[OA\Response(response: 404, description: 'Position not found')]
    #[OA\Response(response: 422, description: 'Cannot delete position with active subordinates')]
    public function destroy(Position $position): JsonResponse
    {
        $this->positionService->delete($position);

        return $this->successResponse(null, 'Position deleted successfully');
    }

    #[OA\Get(
        path: '/positions/{id}/direct-reports',
        summary: 'Get direct reports of a manager position',
        description: 'Returns all positions that directly report to this manager',
        operationId: 'getPositionDirectReports',
        security: [['bearerAuth' => []]],
        tags: ['Positions']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Position not found')]
    public function directReports(Position $position): JsonResponse
    {
        $directReports = $this->positionService->directReports($position);

        return response()->json([
            'success' => true,
            'message' => 'Direct reports retrieved successfully',
            'data' => PositionResource::collection($directReports),
        ]);
    }

    /**
     * Batch delete multiple positions.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Position::findOrFail($id);
                $this->positionService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} position(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
