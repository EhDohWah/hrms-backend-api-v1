<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Tax\CalculateTaxBracketRequest;
use App\Http\Requests\Tax\ListTaxBracketsRequest;
use App\Http\Requests\Tax\SearchTaxBracketRequest;
use App\Http\Requests\Tax\StoreTaxBracketRequest;
use App\Http\Requests\Tax\UpdateTaxBracketRequest;
use App\Http\Resources\TaxBracketResource;
use App\Models\TaxBracket;
use App\Services\TaxBracketService;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD operations and tax calculation for income tax brackets.
 */
class TaxBracketController extends BaseApiController
{
    public function __construct(
        private readonly TaxBracketService $taxBracketService,
    ) {}

    /**
     * Get all tax brackets with advanced filtering and pagination.
     */
    public function index(ListTaxBracketsRequest $request): JsonResponse
    {
        $result = $this->taxBracketService->list($request->validated());

        return TaxBracketResource::collection($result['paginator'])
            ->additional([
                'success' => true,
                'message' => 'Tax brackets retrieved successfully',
                'filters' => ['applied_filters' => $result['applied_filters']],
                'total_count' => $result['total_count'],
            ])
            ->response();
    }

    /**
     * Search tax brackets by bracket order ID.
     */
    public function search(SearchTaxBracketRequest $request): JsonResponse
    {
        $result = $this->taxBracketService->search($request->validated());

        if ($result['brackets']->isEmpty()) {
            return $this->errorResponse(
                "No tax brackets found for order ID: {$request->validated('order_id')}",
                404
            );
        }

        return $this->successResponse([
            'items' => TaxBracketResource::collection($result['brackets']),
            'total_records' => $result['brackets']->count(),
            'search_criteria' => $result['search_criteria'],
        ], 'Tax brackets found successfully');
    }

    /**
     * Create a new tax bracket.
     */
    public function store(StoreTaxBracketRequest $request): JsonResponse
    {
        $taxBracket = $this->taxBracketService->store($request->validated());

        return TaxBracketResource::make($taxBracket)
            ->additional(['success' => true, 'message' => 'Tax bracket created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a specific tax bracket by ID.
     */
    public function show(TaxBracket $taxBracket): JsonResponse
    {
        return TaxBracketResource::make($taxBracket)
            ->additional(['success' => true, 'message' => 'Tax bracket retrieved successfully'])
            ->response();
    }

    /**
     * Update an existing tax bracket.
     */
    public function update(UpdateTaxBracketRequest $request, TaxBracket $taxBracket): JsonResponse
    {
        $taxBracket = $this->taxBracketService->update($taxBracket, $request->validated());

        return TaxBracketResource::make($taxBracket)
            ->additional(['success' => true, 'message' => 'Tax bracket updated successfully'])
            ->response();
    }

    /**
     * Delete a specific tax bracket.
     */
    public function destroy(TaxBracket $taxBracket): JsonResponse
    {
        $this->taxBracketService->destroy($taxBracket);

        return $this->successResponse(null, 'Tax bracket deleted successfully');
    }

    /**
     * Calculate tax for a specific income amount.
     */
    public function calculateTax(CalculateTaxBracketRequest $request, float $income): JsonResponse
    {
        $year = $request->validated('year') ?? (int) date('Y');
        $result = $this->taxBracketService->calculateTax($income, $year);

        if ($result === null) {
            return $this->errorResponse('No tax brackets found for the specified year', 404);
        }

        return $this->successResponse($result, 'Tax calculated successfully');
    }
}
