<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\InterOrganizationAdvance\AutoCreateAdvancesRequest;
use App\Http\Requests\InterOrganizationAdvance\BulkSettleRequest;
use App\Http\Requests\InterOrganizationAdvance\ListInterOrgAdvancesRequest;
use App\Http\Requests\InterOrganizationAdvance\SummaryRequest;
use App\Http\Requests\StoreInterOrganizationAdvanceRequest;
use App\Http\Requests\UpdateInterOrganizationAdvanceRequest;
use App\Http\Resources\InterOrganizationAdvanceResource;
use App\Services\InterOrganizationAdvanceService;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD, bulk settlement, and auto-creation of inter-organization advance records.
 */
class InterOrganizationAdvanceController extends BaseApiController
{
    public function __construct(
        private readonly InterOrganizationAdvanceService $advanceService,
    ) {}

    /**
     * List inter-organization advances with filtering and pagination.
     */
    public function index(ListInterOrgAdvancesRequest $request): JsonResponse
    {
        $result = $this->advanceService->list($request->validated());

        return response()->json([
            'success' => true,
            'data' => InterOrganizationAdvanceResource::collection($result['paginator']->items()),
            'pagination' => [
                'current_page' => $result['paginator']->currentPage(),
                'per_page' => $result['paginator']->perPage(),
                'total' => $result['paginator']->total(),
                'last_page' => $result['paginator']->lastPage(),
                'from' => $result['paginator']->firstItem(),
                'to' => $result['paginator']->lastItem(),
                'has_more_pages' => $result['paginator']->hasMorePages(),
            ],
            'summary' => $result['summary'],
        ]);
    }

    /**
     * Create a new inter-organization advance record.
     */
    public function store(StoreInterOrganizationAdvanceRequest $request): JsonResponse
    {
        $item = $this->advanceService->store($request->validated());

        return InterOrganizationAdvanceResource::make($item)
            ->additional(['success' => true, 'message' => 'Advance recorded.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retrieve a specific inter-organization advance by ID.
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->advanceService->show($id);

        return InterOrganizationAdvanceResource::make($item)
            ->additional(['success' => true, 'message' => 'Advance retrieved successfully'])
            ->response();
    }

    /**
     * Update an existing inter-organization advance record.
     */
    public function update(UpdateInterOrganizationAdvanceRequest $request, int $id): JsonResponse
    {
        $item = $this->advanceService->update($id, $request->validated());

        return InterOrganizationAdvanceResource::make($item)
            ->additional(['success' => true, 'message' => 'Advance updated.'])
            ->response();
    }

    /**
     * Delete an inter-organization advance record.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->advanceService->destroy($id);

        return $this->successResponse(null, 'Advance deleted.');
    }

    /**
     * Bulk settle multiple inter-organization advances.
     */
    public function bulkSettle(BulkSettleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->advanceService->bulkSettle(
            $validated['advance_ids'],
            $validated['settlement_date'],
            $validated['notes'] ?? null,
        );

        if ($result['empty']) {
            return response()->json([
                'success' => false,
                'message' => 'No unsettled advances found with the provided IDs',
            ], 404);
        }

        return $this->successResponse([
            'settled_count' => $result['settled_count'],
            'total_amount' => $result['total_amount'],
            'settlement_date' => $result['settlement_date'],
        ], 'Advances settled successfully');
    }

    /**
     * Get summary statistics for inter-organization advances.
     */
    public function summary(SummaryRequest $request): JsonResponse
    {
        $summary = $this->advanceService->summary($request->validated());

        return $this->successResponse($summary);
    }

    /**
     * Auto-create inter-organization advances based on payroll period data.
     */
    public function autoCreateAdvances(AutoCreateAdvancesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->advanceService->autoCreateAdvances(
            $validated['payroll_period_date'],
            $validated['dry_run'] ?? false,
        );

        return $this->successResponse(
            $result,
            $result['dry_run']
                ? 'Dry run completed - advances would be created'
                : 'Advances created successfully'
        );
    }
}
