<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Http\Resources\HolidayOptionResource;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * HolidayController
 *
 * Manages organization public holidays / traditional day-off calendar.
 * These holidays are excluded when calculating leave request working days.
 *
 * Standard RESTful Methods:
 * - index()   : List all holidays with filtering
 * - show()    : Get single holiday by ID
 * - store()   : Create new holiday
 * - update()  : Update holiday
 * - destroy() : Delete holiday
 * - options() : Get holidays for dropdowns
 *
 * @OA\Tag(
 *     name="Holidays",
 *     description="API Endpoints for managing organization holidays"
 * )
 */
class HolidayController extends Controller
{
    /**
     * Display a listing of holidays with filtering and sorting.
     *
     * @OA\Get(
     *     path="/holidays",
     *     summary="Get paginated holidays with filtering",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Search by name"),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer"), description="Filter by year"),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean"), description="Filter by active status"),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date"), description="Start date filter"),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date"), description="End date filter"),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"date_asc", "date_desc", "name_asc", "name_desc", "recently_added"})),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Holidays retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Holiday")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|nullable|max:255',
                'year' => 'integer|nullable',
                'is_active' => 'boolean|nullable',
                'from' => 'date|nullable',
                'to' => 'date|nullable',
                'sort_by' => 'string|nullable|in:date_asc,date_desc,name_asc,name_desc,recently_added',
            ]);

            $perPage = $validated['per_page'] ?? 15;
            $sortBy = $validated['sort_by'] ?? 'date_asc';

            $query = Holiday::query();

            // Apply search filter
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('name_th', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Apply year filter
            if (! empty($validated['year'])) {
                $query->where('year', $validated['year']);
            }

            // Apply active status filter
            if (isset($validated['is_active'])) {
                $query->where('is_active', $validated['is_active']);
            }

            // Apply date range filter
            if (! empty($validated['from'])) {
                $query->where('date', '>=', $validated['from']);
            }
            if (! empty($validated['to'])) {
                $query->where('date', '<=', $validated['to']);
            }

            // Apply sorting
            switch ($sortBy) {
                case 'date_desc':
                    $query->orderBy('date', 'desc');
                    break;
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                case 'recently_added':
                    $query->orderBy('created_at', 'desc');
                    break;
                default: // date_asc
                    $query->orderBy('date', 'asc');
                    break;
            }

            $holidays = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Holidays retrieved successfully',
                'data' => HolidayResource::collection($holidays),
                'pagination' => [
                    'current_page' => $holidays->currentPage(),
                    'per_page' => $holidays->perPage(),
                    'total' => $holidays->total(),
                    'last_page' => $holidays->lastPage(),
                    'from' => $holidays->firstItem(),
                    'to' => $holidays->lastItem(),
                    'has_more_pages' => $holidays->hasMorePages(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving holidays: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve holidays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified holiday.
     *
     * @OA\Get(
     *     path="/holidays/{id}",
     *     summary="Get a specific holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Holiday retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Holiday")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Holiday not found")
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $holiday = Holiday::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Holiday retrieved successfully',
                'data' => new HolidayResource($holiday),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Holiday not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Store a newly created holiday.
     *
     * @OA\Post(
     *     path="/holidays",
     *     summary="Create a new holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "date"},
     *
     *             @OA\Property(property="name", type="string", example="New Year's Day"),
     *             @OA\Property(property="name_th", type="string", example="วันขึ้นปีใหม่"),
     *             @OA\Property(property="date", type="string", format="date", example="2025-01-01"),
     *             @OA\Property(property="description", type="string", example="First day of the new year"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Holiday created successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreHolidayRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = auth()->user()->name ?? 'System';

            $holiday = Holiday::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Holiday created successfully',
                'data' => new HolidayResource($holiday),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating holiday: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create holiday',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified holiday.
     *
     * @OA\Put(
     *     path="/holidays/{id}",
     *     summary="Update a holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="name_th", type="string"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Holiday updated successfully"),
     *     @OA\Response(response=404, description="Holiday not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateHolidayRequest $request, $id): JsonResponse
    {
        try {
            $holiday = Holiday::findOrFail($id);
            $validated = $request->validated();
            $validated['updated_by'] = auth()->user()->name ?? 'System';

            $holiday->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Holiday updated successfully',
                'data' => new HolidayResource($holiday->fresh()),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating holiday: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update holiday',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified holiday.
     *
     * @OA\Delete(
     *     path="/holidays/{id}",
     *     summary="Delete a holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Holiday deleted successfully"),
     *     @OA\Response(response=404, description="Holiday not found")
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            $holiday = Holiday::findOrFail($id);

            // Check if holiday has compensation records
            if ($holiday->compensationRecords()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete holiday with existing compensation records. Deactivate it instead.',
                ], 422);
            }

            $holiday->delete();

            return response()->json([
                'success' => true,
                'message' => 'Holiday deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting holiday: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete holiday',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get holidays for dropdown selection.
     *
     * @OA\Get(
     *     path="/holidays/options",
     *     summary="Get holidays for dropdown",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer"), description="Filter by year"),
     *     @OA\Parameter(name="active_only", in="query", @OA\Schema(type="boolean"), description="Only return active holidays"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Holiday options retrieved successfully"
     *     )
     * )
     */
    public function options(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'integer|nullable',
                'active_only' => 'boolean|nullable',
            ]);

            $query = Holiday::query()->orderBy('date', 'asc');

            if (! empty($validated['year'])) {
                $query->where('year', $validated['year']);
            }

            if ($validated['active_only'] ?? true) {
                $query->where('is_active', true);
            }

            $holidays = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Holiday options retrieved successfully',
                'data' => HolidayOptionResource::collection($holidays),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving holiday options: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve holiday options',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create holidays for a year.
     *
     * @OA\Post(
     *     path="/holidays/bulk",
     *     summary="Bulk create holidays",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"holidays"},
     *
     *             @OA\Property(
     *                 property="holidays",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="name_th", type="string"),
     *                     @OA\Property(property="date", type="string", format="date"),
     *                     @OA\Property(property="description", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Holidays created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeBatch(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'holidays' => 'required|array|min:1',
                'holidays.*.name' => 'required|string|max:255',
                'holidays.*.name_th' => 'nullable|string|max:255',
                'holidays.*.date' => 'required|date|distinct',
                'holidays.*.description' => 'nullable|string|max:500',
            ]);

            $createdBy = auth()->user()->name ?? 'System';
            $createdHolidays = [];
            $skippedDates = [];

            foreach ($validated['holidays'] as $holidayData) {
                // Check if holiday already exists on this date
                $exists = Holiday::where('date', $holidayData['date'])->exists();

                if ($exists) {
                    $skippedDates[] = $holidayData['date'];

                    continue;
                }

                $holiday = Holiday::create([
                    'name' => $holidayData['name'],
                    'name_th' => $holidayData['name_th'] ?? null,
                    'date' => $holidayData['date'],
                    'year' => date('Y', strtotime($holidayData['date'])),
                    'description' => $holidayData['description'] ?? null,
                    'is_active' => true,
                    'created_by' => $createdBy,
                ]);

                $createdHolidays[] = $holiday;
            }

            return response()->json([
                'success' => true,
                'message' => 'Holidays created successfully',
                'data' => [
                    'created' => HolidayResource::collection($createdHolidays),
                    'created_count' => count($createdHolidays),
                    'skipped_dates' => $skippedDates,
                    'skipped_count' => count($skippedDates),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error bulk creating holidays: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create holidays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get holidays within a date range (for leave calculation).
     *
     * @OA\Get(
     *     path="/holidays/in-range",
     *     summary="Get holidays within a date range",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Holidays in range retrieved successfully"
     *     )
     * )
     */
    public function inRange(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $holidays = Holiday::active()
                ->betweenDates($validated['start_date'], $validated['end_date'])
                ->orderBy('date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Holidays in range retrieved successfully',
                'data' => HolidayResource::collection($holidays),
                'dates' => $holidays->pluck('date')->map(fn ($d) => $d->format('Y-m-d')),
                'count' => $holidays->count(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving holidays in range: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve holidays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
