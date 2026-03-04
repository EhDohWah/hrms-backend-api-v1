<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\Grant\GrantPositionsRequest;
use App\Http\Requests\Grant\IndexGrantRequest;
use App\Http\Requests\Grant\StoreGrantRequest;
use App\Http\Requests\Grant\UpdateGrantRequest;
use App\Http\Requests\Grant\UploadGrantRequest;
use App\Http\Resources\GrantResource;
use App\Models\Grant;
use App\Services\GrantService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GrantController extends BaseApiController
{
    public function __construct(
        private readonly GrantService $grantService,
    ) {}

    /**
     * List all grants with pagination and filtering.
     */
    public function index(IndexGrantRequest $request): JsonResponse
    {
        $result = $this->grantService->list($request->validated());
        $grants = $result['grants'];

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
                'applied_filters' => $result['applied_filters'],
            ],
        ]);
    }

    /**
     * Get a specific grant with its items by grant code.
     */
    public function showByCode(string $code): JsonResponse
    {
        $grant = $this->grantService->showByCode($code);

        return GrantResource::make($grant)
            ->additional(['success' => true, 'message' => 'Grant retrieved successfully'])
            ->response();
    }

    /**
     * Get a specific grant with its items by grant ID.
     */
    public function show(Grant $grant): JsonResponse
    {
        $grant = $this->grantService->show($grant);

        return GrantResource::make($grant)
            ->additional(['success' => true, 'message' => 'Grant retrieved successfully'])
            ->response();
    }

    /**
     * Create a new grant.
     */
    public function store(StoreGrantRequest $request): JsonResponse
    {
        $grant = $this->grantService->store($request->validated());

        return GrantResource::make($grant)
            ->additional(['success' => true, 'message' => 'Grant created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing grant.
     */
    public function update(UpdateGrantRequest $request, Grant $grant): JsonResponse
    {
        $grant = $this->grantService->update($grant, $request->validated());

        return GrantResource::make($grant)
            ->additional(['success' => true, 'message' => 'Grant updated successfully'])
            ->response();
    }

    /**
     * Delete a grant (soft delete).
     */
    public function destroy(Grant $grant): JsonResponse
    {
        $this->grantService->destroy($grant);

        return $this->successResponse(null, 'Grant moved to recycle bin');
    }

    /**
     * Delete multiple grants (soft delete).
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $result = $this->grantService->destroyBatch($request->validated()['ids']);

        $successCount = count($result['succeeded']);
        $failureCount = count($result['failed']);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} grant(s) moved to recycle bin"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
        ], $failureCount === 0 ? 200 : 207);
    }

    /**
     * Get grant statistics with position recruitment status.
     */
    public function positions(GrantPositionsRequest $request): JsonResponse
    {
        $result = $this->grantService->positions($request->validated());
        $paginator = $result['paginator'];

        return response()->json([
            'success' => true,
            'message' => $paginator->isEmpty()
                ? (! empty($request->validated()['search']) ? 'No grant positions found matching your search' : 'No grants found')
                : 'Grant statistics retrieved successfully',
            'data' => $result['data'],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Upload grant data from Excel file.
     */
    public function upload(UploadGrantRequest $request): JsonResponse
    {
        $result = $this->grantService->upload($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Download grant import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return $this->grantService->downloadTemplate();
    }
}
