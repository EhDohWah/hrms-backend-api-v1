<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Lookup\IndexLookupRequest;
use App\Http\Requests\Lookup\SearchLookupRequest;
use App\Http\Requests\Lookup\StoreLookupRequest;
use App\Http\Requests\Lookup\UpdateLookupRequest;
use App\Http\Resources\LookupResource;
use App\Models\Lookup;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;

/**
 * Manages system lookup values used for dropdowns and reference data.
 */
class LookupController extends BaseApiController
{
    public function __construct(
        private readonly LookupService $lookupService,
    ) {}

    /**
     * Get all lookups organized by category.
     */
    public function lists(): JsonResponse
    {
        $grouped = $this->lookupService->grouped();

        return $this->successResponse($grouped, 'Lookup lists retrieved successfully');
    }

    /**
     * Get all lookups with pagination and filtering.
     */
    public function index(IndexLookupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['grouped'] ?? false) {
            return $this->lists();
        }

        $paginator = $this->lookupService->list($validated);

        return LookupResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Lookups retrieved successfully',
                'filters' => [
                    'applied_filters' => array_filter([
                        'type' => ! empty($validated['filter_type']) ? explode(',', $validated['filter_type']) : null,
                        'search' => $validated['search'] ?? null,
                    ]),
                    'available_types' => $this->lookupService->types(),
                ],
            ])
            ->response();
    }

    /**
     * Store a new lookup value.
     */
    public function store(StoreLookupRequest $request): JsonResponse
    {
        $lookup = $this->lookupService->store($request->validated(), $request->user());

        return LookupResource::make($lookup)
            ->additional(['success' => true, 'message' => 'Lookup created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing lookup value.
     */
    public function update(UpdateLookupRequest $request, Lookup $lookup): JsonResponse
    {
        $lookup = $this->lookupService->update($lookup, $request->validated(), $request->user());

        return LookupResource::make($lookup)
            ->additional(['success' => true, 'message' => 'Lookup updated successfully'])
            ->response();
    }

    /**
     * Delete a lookup value.
     */
    public function destroy(Lookup $lookup): JsonResponse
    {
        $this->lookupService->destroy($lookup);

        return $this->successResponse(null, 'Lookup deleted successfully');
    }

    /**
     * Get a specific lookup value.
     */
    public function show(Lookup $lookup): JsonResponse
    {
        return LookupResource::make($lookup)
            ->additional(['success' => true, 'message' => 'Lookup retrieved successfully'])
            ->response();
    }

    /**
     * Search lookups with advanced filtering.
     */
    public function search(SearchLookupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->lookupService->search($validated);

        $searchedTypes = ! empty($validated['types'])
            ? array_map('trim', explode(',', $validated['types']))
            : [];

        if ($paginator->isEmpty()) {
            $searchTerm = $validated['search'] ?? $validated['value'] ?? 'specified criteria';

            return $this->errorResponse(
                "No lookup records found for search: {$searchTerm}",
                404
            );
        }

        return LookupResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Search completed successfully',
                'search_info' => [
                    'search_term' => $validated['search'] ?? null,
                    'searched_types' => $searchedTypes,
                    'total_found' => $paginator->total(),
                ],
            ])
            ->response();
    }

    /**
     * Get all available lookup types.
     */
    public function types(): JsonResponse
    {
        return $this->successResponse(
            $this->lookupService->types(),
            'Lookup types retrieved successfully'
        );
    }

    /**
     * Get lookup values by type.
     */
    public function byType(string $type): JsonResponse
    {
        if (! $this->lookupService->typeExists($type)) {
            return $this->errorResponse(
                "Lookup type '{$type}' does not exist",
                404
            );
        }

        $lookups = $this->lookupService->byType($type);

        return $this->successResponse(
            LookupResource::collection($lookups),
            'Lookups retrieved successfully'
        );
    }
}
