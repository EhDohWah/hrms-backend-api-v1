<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxBracket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Tax Brackets', description: 'API Endpoints for managing tax brackets')]
class TaxBracketController extends Controller
{
    #[OA\Get(
        path: '/tax-brackets',
        summary: 'Get all tax brackets with advanced filtering and pagination',
        description: 'Get a paginated list of all tax brackets with advanced filtering, sorting, and search capabilities',
        tags: ['Tax Brackets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
            new OA\Parameter(name: 'filter_effective_year', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax brackets retrieved successfully'),
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
                'filter_effective_year' => 'string|nullable',
                'filter_is_active' => 'nullable|in:true,false,1,0',
                'sort_by' => 'string|nullable|in:effective_year,bracket_order,min_income,max_income,tax_rate',
                'sort_order' => 'string|nullable|in:asc,desc',
                'search' => 'string|nullable',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Get total count before filtering for meta
            $totalCount = TaxBracket::count();

            // Build query
            $query = TaxBracket::query();

            // Apply search filter (search by bracket_order - exact match)
            if (! empty($validated['search'])) {
                // Try to parse as integer for bracket_order search
                if (is_numeric($validated['search'])) {
                    $query->where('bracket_order', intval($validated['search']));
                }
            }

            // Apply effective year filter
            if (! empty($validated['filter_effective_year'])) {
                $years = explode(',', $validated['filter_effective_year']);
                $years = array_map('intval', $years); // Convert to integers
                $query->whereIn('effective_year', $years);
            }

            // Apply is_active filter
            if (isset($validated['filter_is_active'])) {
                $isActive = filter_var($validated['filter_is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'bracket_order';
            $sortOrder = $validated['sort_order'] ?? 'asc';

            if (in_array($sortBy, ['effective_year', 'bracket_order', 'min_income', 'max_income', 'tax_rate'])) {
                $query->orderBy($sortBy, $sortOrder);
                // Add secondary sort for consistency
                if ($sortBy !== 'bracket_order') {
                    $query->orderBy('bracket_order', 'asc');
                }
            } else {
                $query->orderBy('bracket_order', 'asc');
            }

            // Execute pagination
            $taxBrackets = $query->paginate($perPage, ['*'], 'page', $page);

            // Add calculated attributes to each item
            $taxBrackets->getCollection()->each(function ($bracket) {
                $bracket->append(['income_range', 'formatted_rate']);
            });

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_effective_year'])) {
                $appliedFilters['effective_year'] = array_map('intval', explode(',', $validated['filter_effective_year']));
            }
            if (isset($validated['filter_is_active'])) {
                $appliedFilters['is_active'] = filter_var($validated['filter_is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax brackets retrieved successfully',
                'data' => $taxBrackets->items(),
                'pagination' => [
                    'current_page' => $taxBrackets->currentPage(),
                    'per_page' => $taxBrackets->perPage(),
                    'total' => $taxBrackets->total(),
                    'last_page' => $taxBrackets->lastPage(),
                    'from' => $taxBrackets->firstItem(),
                    'to' => $taxBrackets->lastItem(),
                    'has_more_pages' => $taxBrackets->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
                'meta' => [
                    'total_count' => $totalCount,
                    'filtered_count' => $taxBrackets->total(),
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
                'message' => 'Failed to retrieve tax brackets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/tax-brackets/search',
        operationId: 'searchTaxBracketsByOrder',
        summary: 'Search tax brackets by bracket order ID',
        description: 'Returns all tax brackets matching the specified bracket order',
        tags: ['Tax Brackets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order_id', in: 'query', required: true, description: 'Bracket order ID', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'effective_year', in: 'query', required: false, description: 'Filter by effective year', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, description: 'Filter by active status', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax brackets found successfully'),
            new OA\Response(response: 404, description: 'No tax brackets found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function search(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'order_id' => 'required|integer|min:1',
                'effective_year' => 'nullable|integer|min:2000|max:2100',
                'is_active' => 'nullable|boolean',
            ]);

            $orderId = $validated['order_id'];

            // Build query to search by bracket_order using model scope
            $query = TaxBracket::byOrder($orderId);

            // Apply optional filters
            if (isset($validated['effective_year'])) {
                $query->where('effective_year', $validated['effective_year']);
            }

            if (isset($validated['is_active'])) {
                $query->where('is_active', $validated['is_active']);
            }

            // Execute query with ordering
            $taxBrackets = $query->orderBy('effective_year', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Check if any records were found
            if ($taxBrackets->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No tax brackets found for order ID: {$orderId}",
                ], 404);
            }

            // Add calculated attributes to each item
            $taxBrackets->each(function ($bracket) {
                $bracket->append(['income_range', 'formatted_rate']);
            });

            // Build search criteria for response
            $searchCriteria = ['order_id' => $orderId];
            if (isset($validated['effective_year'])) {
                $searchCriteria['effective_year'] = $validated['effective_year'];
            }
            if (isset($validated['is_active'])) {
                $searchCriteria['is_active'] = $validated['is_active'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax brackets found successfully',
                'total_records' => $taxBrackets->count(),
                'search_criteria' => $searchCriteria,
                'data' => $taxBrackets,
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
                'message' => 'Failed to search tax brackets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/tax-brackets',
        summary: 'Create a new tax bracket',
        description: 'Create a new tax bracket',
        tags: ['Tax Brackets'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TaxBracket')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tax bracket created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'min_income' => 'required|numeric|min:0',
                'max_income' => 'nullable|numeric|gt:min_income',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'bracket_order' => 'required|integer|min:1',
                'effective_year' => 'required|integer|min:2000|max:2100',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'created_by' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check for duplicate bracket order in the same year
            $existingBracket = TaxBracket::where('effective_year', $request->effective_year)
                ->where('bracket_order', $request->bracket_order)
                ->first();

            if ($existingBracket) {
                return response()->json([
                    'success' => false,
                    'message' => 'A tax bracket with this order already exists for the specified year',
                ], 422);
            }

            $taxBracket = TaxBracket::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tax bracket created successfully',
                'data' => $taxBracket,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tax bracket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/tax-brackets/{id}',
        summary: 'Get a specific tax bracket',
        description: 'Get details of a specific tax bracket by ID',
        tags: ['Tax Brackets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Tax bracket ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax bracket retrieved successfully'),
            new OA\Response(response: 404, description: 'Tax bracket not found'),
        ]
    )]
    public function show(string $id)
    {
        try {
            $taxBracket = TaxBracket::findOrFail($id);
            $taxBracket->append(['income_range', 'formatted_rate']);

            return response()->json([
                'success' => true,
                'message' => 'Tax bracket retrieved successfully',
                'data' => $taxBracket,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax bracket not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax bracket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/tax-brackets/{id}',
        summary: 'Update a tax bracket',
        description: 'Update an existing tax bracket',
        tags: ['Tax Brackets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Tax bracket ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TaxBracket')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tax bracket updated successfully'),
            new OA\Response(response: 404, description: 'Tax bracket not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'min_income' => 'sometimes|required|numeric|min:0',
                'max_income' => 'nullable|numeric|gt:min_income',
                'tax_rate' => 'sometimes|required|numeric|min:0|max:100',
                'bracket_order' => 'sometimes|required|integer|min:1',
                'effective_year' => 'sometimes|required|integer|min:2000|max:2100',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'updated_by' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $taxBracket = TaxBracket::findOrFail($id);

            // Check for duplicate bracket order if updating order or year
            if ($request->has('bracket_order') || $request->has('effective_year')) {
                $bracketOrder = $request->bracket_order ?? $taxBracket->bracket_order;
                $effectiveYear = $request->effective_year ?? $taxBracket->effective_year;

                $existingBracket = TaxBracket::where('effective_year', $effectiveYear)
                    ->where('bracket_order', $bracketOrder)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingBracket) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A tax bracket with this order already exists for the specified year',
                    ], 422);
                }
            }

            $taxBracket->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tax bracket updated successfully',
                'data' => $taxBracket,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax bracket not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax bracket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/tax-brackets/{id}',
        summary: 'Delete a tax bracket',
        description: 'Delete a specific tax bracket',
        tags: ['Tax Brackets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Tax bracket ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax bracket deleted successfully'),
            new OA\Response(response: 404, description: 'Tax bracket not found'),
        ]
    )]
    public function destroy(string $id)
    {
        try {
            $taxBracket = TaxBracket::findOrFail($id);
            $taxBracket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tax bracket deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax bracket not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tax bracket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/tax-brackets/calculate/{income}',
        summary: 'Calculate tax for a specific income',
        description: 'Calculate the tax amount for a given income using current tax brackets',
        tags: ['Tax Brackets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'income', in: 'path', required: true, description: 'Annual income amount', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Tax year', schema: new OA\Schema(type: 'integer', example: 2025)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax calculation completed successfully'),
        ]
    )]
    public function calculateTax(Request $request, float $income)
    {
        try {
            $year = $request->get('year', date('Y'));
            $taxBrackets = TaxBracket::getBracketsForYear($year);

            if ($taxBrackets->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tax brackets found for the specified year',
                ], 404);
            }

            $totalTax = 0;
            $breakdown = [];
            $remainingIncome = $income;

            foreach ($taxBrackets as $bracket) {
                if ($remainingIncome <= 0) {
                    break;
                }

                $bracketMin = $bracket->min_income;
                $bracketMax = $bracket->max_income ?? PHP_FLOAT_MAX;
                $taxRate = $bracket->tax_rate;

                if ($income > $bracketMin) {
                    $taxableInBracket = min($remainingIncome, $bracketMax - $bracketMin);
                    $taxInBracket = $taxableInBracket * ($taxRate / 100);
                    $totalTax += $taxInBracket;

                    $breakdown[] = [
                        'bracket' => $bracket->income_range,
                        'rate' => $bracket->formatted_rate,
                        'taxable_amount' => $taxableInBracket,
                        'tax_amount' => $taxInBracket,
                    ];

                    $remainingIncome -= $taxableInBracket;
                }
            }

            $effectiveRate = $income > 0 ? ($totalTax / $income) * 100 : 0;

            return response()->json([
                'success' => true,
                'message' => 'Tax calculated successfully',
                'data' => [
                    'income' => $income,
                    'total_tax' => $totalTax,
                    'net_income' => $income - $totalTax,
                    'effective_rate' => round($effectiveRate, 2),
                    'breakdown' => $breakdown,
                    'tax_year' => $year,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate tax',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
