<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PositionSlot;
use App\Models\BudgetLine;
use App\Models\GrantItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Position Slots",
 *     description="Position slot management endpoints"
 * )
 */
class PositionSlotController extends Controller
{
    /**
     * @OA\Get(
     *     path="/position-slots",
     *     tags={"Position Slots"},
     *     summary="List all position slots",
     *     description="Retrieve a list of all position slots, optionally filtered by grant item ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="grant_item_id",
     *         in="query",
     *         description="Filter by grant item ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
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
     *                     @OA\Property(property="grant_item_id", type="integer", example=1),
     *                     @OA\Property(property="slot_number", type="integer", example=1),
     *                     @OA\Property(property="budget_line_id", type="integer", example=1),
     *                     @OA\Property(property="created_by", type="string", example="Admin User"),
     *                     @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="budgetLine",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="budget_line_code", type="string", example="BL001")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    // List all position slots for a grant item
    public function index(Request $request)
    {
        $grantItemId = $request->query('grant_item_id');
        $query = PositionSlot::with('budgetLine');
        if ($grantItemId) {
            $query->where('grant_item_id', $grantItemId);
        }
        $slots = $query->get();
        return response()->json(['success' => true, 'data' => $slots]);
    }

    /**
     * @OA\Post(
     *     path="/position-slots",
     *     tags={"Position Slots"},
     *     summary="Create a new position slot",
     *     description="Create a new position slot with existing or new budget line",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"grant_item_id", "slot_number"},
     *             @OA\Property(property="grant_item_id", type="integer", example=1, description="Grant item ID"),
     *             @OA\Property(property="slot_number", type="integer", example=1, description="Slot number"),
     *             @OA\Property(property="budget_line_id", type="integer", nullable=true, example=1, description="Existing budget line ID"),
     *             @OA\Property(property="budget_line_code", type="string", nullable=true, example="BL001", description="Budget line code (creates new if not exists)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Position slot created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="grant_item_id", type="integer", example=1),
     *                 @OA\Property(property="slot_number", type="integer", example=1),
     *                 @OA\Property(property="budget_line_id", type="integer", example=1),
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
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Budget line is required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    // Store a new position slot (with existing or new budget line)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'grant_item_id' => 'required|exists:grant_items,id',
            'slot_number' => 'required|integer|min:1',
            // Either budget_line_id OR budget_line_code must be provided:
            'budget_line_id' => 'nullable|exists:budget_lines,id',
            'budget_line_code' => 'nullable|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            // Create budget line if not exists and code provided
            if (empty($validated['budget_line_id']) && !empty($validated['budget_line_code'])) {
                $budgetLine = BudgetLine::firstOrCreate(
                    ['budget_line_code' => $validated['budget_line_code']],
                    ['created_by' => $request->user()?->name ?? 'system']
                );
                $budgetLineId = $budgetLine->id;
            } elseif (!empty($validated['budget_line_id'])) {
                $budgetLineId = $validated['budget_line_id'];
            } else {
                return response()->json(['success' => false, 'message' => 'Budget line is required'], 422);
            }

            // Create position slot
            $slot = PositionSlot::create([
                'grant_item_id' => $validated['grant_item_id'],
                'slot_number' => $validated['slot_number'],
                'budget_line_id' => $budgetLineId,
                'created_by' => $request->user()?->name ?? 'system'
            ]);
            DB::commit();
            return response()->json(['success' => true, 'data' => $slot->load('budgetLine')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/position-slots/{id}",
     *     tags={"Position Slots"},
     *     summary="Get a specific position slot",
     *     description="Retrieve details of a specific position slot by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Position slot ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="grant_item_id", type="integer", example=1),
     *                 @OA\Property(property="slot_number", type="integer", example=1),
     *                 @OA\Property(property="budget_line_id", type="integer", example=1),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="budgetLine",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="budget_line_code", type="string", example="BL001")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Position slot not found"
     *     )
     * )
     */
    // Show a single position slot
    public function show($id)
    {
        $slot = PositionSlot::with('budgetLine')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $slot]);
    }

    /**
     * @OA\Put(
     *     path="/position-slots/{id}",
     *     tags={"Position Slots"},
     *     summary="Update a position slot",
     *     description="Update a position slot with possible new budget line",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Position slot ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="slot_number", type="integer", example=2, description="Slot number"),
     *             @OA\Property(property="budget_line_id", type="integer", nullable=true, example=1, description="Existing budget line ID"),
     *             @OA\Property(property="budget_line_code", type="string", nullable=true, example="BL002", description="Budget line code (creates new if not exists)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Position slot updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="grant_item_id", type="integer", example=1),
     *                 @OA\Property(property="slot_number", type="integer", example=2),
     *                 @OA\Property(property="budget_line_id", type="integer", example=1),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Position slot not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    // Update a position slot (with possible new budget line)
    public function update(Request $request, $id)
    {
        $slot = PositionSlot::findOrFail($id);
        $validated = $request->validate([
            'slot_number' => 'sometimes|required|integer|min:1',
            'budget_line_id' => 'nullable|exists:budget_lines,id',
            'budget_line_code' => 'nullable|string|max:255'
        ]);
        DB::beginTransaction();
        try {
            // Handle new budget line code creation
            if (empty($validated['budget_line_id']) && !empty($validated['budget_line_code'])) {
                $budgetLine = BudgetLine::firstOrCreate(
                    ['budget_line_code' => $validated['budget_line_code']],
                    ['created_by' => $request->user()?->name ?? 'system']
                );
                $slot->budget_line_id = $budgetLine->id;
            } elseif (!empty($validated['budget_line_id'])) {
                $slot->budget_line_id = $validated['budget_line_id'];
            }
            if (!empty($validated['slot_number'])) {
                $slot->slot_number = $validated['slot_number'];
            }
            $slot->updated_by = $request->user()?->name ?? 'system';
            $slot->save();
            DB::commit();
            return response()->json(['success' => true, 'data' => $slot->load('budgetLine')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/position-slots/{id}",
     *     tags={"Position Slots"},
     *     summary="Delete a position slot",
     *     description="Delete a specific position slot by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Position slot ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Position slot deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Position slot not found"
     *     )
     * )
     */
    // Delete a position slot
    public function destroy($id)
    {
        $slot = PositionSlot::findOrFail($id);
        $slot->delete();
        return response()->json(['success' => true]);
    }
}
