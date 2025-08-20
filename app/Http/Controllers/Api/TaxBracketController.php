<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxBracket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Tax Brackets",
 *     description="API Endpoints for managing tax brackets"
 * )
 */
class TaxBracketController extends Controller
{
    /**
     * @OA\Get(
     *     path="/tax-brackets",
     *     summary="Get all tax brackets",
     *     description="Get a list of all tax brackets with optional filtering by year",
     *     tags={"Tax Brackets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Filter by effective year",
     *
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Show only active brackets",
     *
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tax brackets retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax brackets retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="min_income", type="number", example=0),
     *                     @OA\Property(property="max_income", type="number", example=150000),
     *                     @OA\Property(property="tax_rate", type="number", example=0),
     *                     @OA\Property(property="bracket_order", type="integer", example=1),
     *                     @OA\Property(property="effective_year", type="integer", example=2025),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="description", type="string", example="Tax-free bracket"),
     *                     @OA\Property(property="income_range", type="string", example="฿0 - ฿150,000"),
     *                     @OA\Property(property="formatted_rate", type="string", example="0%")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = TaxBracket::query();

            // Filter by year if provided
            if ($request->has('year')) {
                $query->forYear($request->year);
            }

            // Filter by active status
            if ($request->boolean('active_only', true)) {
                $query->active();
            }

            $taxBrackets = $query->ordered()->get();

            // Add calculated attributes
            $taxBrackets->each(function ($bracket) {
                $bracket->append(['income_range', 'formatted_rate']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Tax brackets retrieved successfully',
                'data' => $taxBrackets,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax brackets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/tax-brackets",
     *     summary="Create a new tax bracket",
     *     description="Create a new tax bracket",
     *     tags={"Tax Brackets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"min_income", "tax_rate", "bracket_order", "effective_year"},
     *
     *             @OA\Property(property="min_income", type="number", example=150001),
     *             @OA\Property(property="max_income", type="number", example=300000, nullable=true),
     *             @OA\Property(property="tax_rate", type="number", example=5),
     *             @OA\Property(property="bracket_order", type="integer", example=2),
     *             @OA\Property(property="effective_year", type="integer", example=2025),
     *             @OA\Property(property="description", type="string", example="5% tax bracket"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Tax bracket created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax bracket created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/tax-brackets/{id}",
     *     summary="Get a specific tax bracket",
     *     description="Get details of a specific tax bracket by ID",
     *     tags={"Tax Brackets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tax bracket ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tax bracket retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax bracket retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Tax bracket not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tax bracket not found")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/tax-brackets/{id}",
     *     summary="Update a tax bracket",
     *     description="Update an existing tax bracket",
     *     tags={"Tax Brackets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tax bracket ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="min_income", type="number", example=150001),
     *             @OA\Property(property="max_income", type="number", example=300000, nullable=true),
     *             @OA\Property(property="tax_rate", type="number", example=5),
     *             @OA\Property(property="bracket_order", type="integer", example=2),
     *             @OA\Property(property="effective_year", type="integer", example=2025),
     *             @OA\Property(property="description", type="string", example="5% tax bracket"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="updated_by", type="string", example="admin@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tax bracket updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax bracket updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Tax bracket not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tax bracket not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/tax-brackets/{id}",
     *     summary="Delete a tax bracket",
     *     description="Delete a specific tax bracket",
     *     tags={"Tax Brackets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Tax bracket ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tax bracket deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax bracket deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Tax bracket not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tax bracket not found")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/tax-brackets/calculate/{income}",
     *     summary="Calculate tax for a specific income",
     *     description="Calculate the tax amount for a given income using current tax brackets",
     *     tags={"Tax Brackets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="income",
     *         in="path",
     *         required=true,
     *         description="Annual income amount",
     *
     *         @OA\Schema(type="number")
     *     ),
     *
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Tax year (defaults to current year)",
     *
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tax calculation completed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="income", type="number", example=500000),
     *                 @OA\Property(property="total_tax", type="number", example=25000),
     *                 @OA\Property(property="effective_rate", type="number", example=5),
     *                 @OA\Property(property="breakdown", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
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
