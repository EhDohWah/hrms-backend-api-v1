<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Budget Lines",
 *     description="Budget line management endpoints"
 * )
 */
class BudgetLineController extends Controller
{
    /**
     * @OA\Get(
     *     path="/budget-lines",
     *     tags={"Budget Lines"},
     *     summary="List all budget lines",
     *     description="Retrieve a list of all budget lines ordered by budget line code",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="budget_line_code", type="string", example="BL001"),
     *                     @OA\Property(property="description", type="string", example="Description"),
     *                     @OA\Property(property="created_by", type="string", example="Admin User"),
     *                     @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $lines = BudgetLine::orderBy('budget_line_code')->get();
        return response()->json(['success' => true, 'data' => $lines]);
    }

    /**
     * @OA\Post(
     *     path="/budget-lines", 
     *     tags={"Budget Lines"},
     *     summary="Create a new budget line",
     *     description="Create a new budget line with a unique budget line code",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="budget_line_code", type="string", example="BL001", description="Unique budget line code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Budget line created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_line_code", type="string", example="BL001"),
     *                 @OA\Property(property="description", type="string", example="Description"),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 @OA\Property(
     *                     property="budget_line_code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The budget line code has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'budget_line_code' => 'required|string|max:255|unique:budget_lines,budget_line_code',
            'description' => 'required|string|max:255',
        ]);
        $line = BudgetLine::create([
            'budget_line_code' => $validated['budget_line_code'],
            'description' => $validated['description'],
            'created_by' => $request->user()?->name ?? 'system'
        ]);
        return response()->json(['success' => true, 'data' => $line], 201);
    }

    /**
     * @OA\Get(
     *     path="/budget-lines/{id}",
     *     tags={"Budget Lines"},
     *     summary="Show a single budget line",
     *     description="Retrieve details of a specific budget line by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Budget line ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_line_code", type="string", example="BL001"),
     *                 @OA\Property(property="description", type="string", example="Description"),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Budget line not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\BudgetLine] 1")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $line = BudgetLine::findOrFail($id);
        return response()->json(['success' => true, 'data' => $line]);
    }

    /**
     * @OA\Put(
     *     path="/budget-lines/{id}",
     *     tags={"Budget Lines"},
     *     summary="Update a budget line",
     *     description="Update an existing budget line's budget line code",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Budget line ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="budget_line_code", type="string", example="BL001-UPDATED", description="Updated budget line code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Budget line updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_line_code", type="string", example="BL001-UPDATED"),
     *                 @OA\Property(property="description", type="string", example="Description"),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Budget line not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\BudgetLine] 1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 @OA\Property(
     *                     property="budget_line_code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The budget line code has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $line = BudgetLine::findOrFail($id);
        $validated = $request->validate([
            'budget_line_code' => [
                'required', 'string', 'max:255',
                Rule::unique('budget_lines')->ignore($line->id),
            ],
            'description' => 'required|string|max:255',
        ]);
        $line->budget_line_code = $validated['budget_line_code'];
        $line->description = $validated['description'];
        $line->updated_by = $request->user()?->name ?? 'system';
        $line->save();
        return response()->json(['success' => true, 'data' => $line]);
    }

    /**
     * @OA\Delete(
     *     path="/budget-lines/{id}",
     *     tags={"Budget Lines"},
     *     summary="Delete a budget line",
     *     description="Delete a specific budget line by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Budget line ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Budget line deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Budget line not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\BudgetLine] 1")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $line = BudgetLine::findOrFail($id);
        $line->delete();
        return response()->json(['success' => true]);
    }
}
