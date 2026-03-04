<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\BatchStoreHolidayRequest;
use App\Http\Requests\IndexHolidayRequest;
use App\Http\Requests\InRangeHolidayRequest;
use App\Http\Requests\OptionsHolidayRequest;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Http\Resources\HolidayOptionResource;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use App\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * HolidayController
 *
 * Manages organization public holidays / traditional day-off calendar.
 * These holidays are excluded when calculating leave request working days.
 */
#[OA\Tag(
    name: 'Holidays',
    description: 'API Endpoints for managing organization holidays'
)]
class HolidayController extends BaseApiController
{
    public function __construct(
        private readonly HolidayService $holidayService
    ) {}

    /**
     * Display a listing of holidays with filtering and sorting.
     */
    #[OA\Get(
        path: '/holidays',
        summary: 'Get paginated holidays with filtering',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by name', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'year', in: 'query', description: 'Filter by year', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'is_active', in: 'query', description: 'Filter by active status', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'from', in: 'query', description: 'Start date filter', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to', in: 'query', description: 'End date filter', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['date_asc', 'date_desc', 'name_asc', 'name_desc', 'recently_added']))]
    #[OA\Response(
        response: 200,
        description: 'Holidays retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Holiday')),
                new OA\Property(property: 'meta', type: 'object'),
            ]
        )
    )]
    public function index(IndexHolidayRequest $request): JsonResponse
    {
        $paginator = $this->holidayService->list($request->validated());

        return HolidayResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Holidays retrieved successfully',
            ])
            ->response();
    }

    /**
     * Display the specified holiday.
     */
    #[OA\Get(
        path: '/holidays/{holiday}',
        summary: 'Get a specific holiday',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'holiday', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Holiday retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Holiday'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Holiday not found')]
    public function show(Holiday $holiday): JsonResponse
    {
        return HolidayResource::make($holiday)
            ->additional([
                'success' => true,
                'message' => 'Holiday retrieved successfully',
            ])
            ->response();
    }

    /**
     * Store a newly created holiday.
     */
    #[OA\Post(
        path: '/holidays',
        summary: 'Create a new holiday',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'date'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: "New Year's Day"),
                new OA\Property(property: 'name_th', type: 'string', example: 'วันขึ้นปีใหม่'),
                new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-01-01'),
                new OA\Property(property: 'description', type: 'string', example: 'First day of the new year'),
                new OA\Property(property: 'is_active', type: 'boolean', example: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Holiday created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = $this->holidayService->create(
            $request->validated(),
            $request->user()
        );

        return HolidayResource::make($holiday)
            ->additional([
                'success' => true,
                'message' => 'Holiday created successfully',
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified holiday.
     */
    #[OA\Put(
        path: '/holidays/{holiday}',
        summary: 'Update a holiday',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'holiday', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'name_th', type: 'string'),
                new OA\Property(property: 'date', type: 'string', format: 'date'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Holiday updated successfully')]
    #[OA\Response(response: 404, description: 'Holiday not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $holiday = $this->holidayService->update(
            $holiday,
            $request->validated(),
            $request->user()
        );

        return HolidayResource::make($holiday)
            ->additional([
                'success' => true,
                'message' => 'Holiday updated successfully',
            ])
            ->response();
    }

    /**
     * Remove the specified holiday.
     */
    #[OA\Delete(
        path: '/holidays/{holiday}',
        summary: 'Delete a holiday',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'holiday', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Holiday deleted successfully')]
    #[OA\Response(response: 404, description: 'Holiday not found')]
    #[OA\Response(response: 422, description: 'Cannot delete holiday with compensation records')]
    public function destroy(Holiday $holiday): JsonResponse
    {
        $result = $this->holidayService->delete($holiday);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], $result['status']);
        }

        return $this->successResponse(null, $result['message']);
    }

    /**
     * Get holidays for dropdown selection.
     */
    #[OA\Get(
        path: '/holidays/options',
        summary: 'Get holidays for dropdown',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'year', in: 'query', description: 'Filter by year', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'active_only', in: 'query', description: 'Only return active holidays', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(
        response: 200,
        description: 'Holiday options retrieved successfully'
    )]
    public function options(OptionsHolidayRequest $request): JsonResponse
    {
        $holidays = $this->holidayService->options($request->validated());

        return $this->successResponse(
            HolidayOptionResource::collection($holidays),
            'Holiday options retrieved successfully'
        );
    }

    /**
     * Bulk create holidays for a year.
     */
    #[OA\Post(
        path: '/holidays/bulk',
        summary: 'Bulk create holidays',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['holidays'],
            properties: [
                new OA\Property(
                    property: 'holidays',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'name_th', type: 'string'),
                            new OA\Property(property: 'date', type: 'string', format: 'date'),
                            new OA\Property(property: 'description', type: 'string'),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Holidays created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function storeBatch(BatchStoreHolidayRequest $request): JsonResponse
    {
        $result = $this->holidayService->storeBatch(
            $request->validated()['holidays'],
            $request->user()
        );

        return $this->createdResponse([
            'created' => HolidayResource::collection($result['created']),
            'created_count' => $result['created_count'],
            'skipped_dates' => $result['skipped_dates'],
            'skipped_count' => $result['skipped_count'],
        ], 'Holidays created successfully');
    }

    /**
     * Get holidays within a date range (for leave calculation).
     */
    #[OA\Get(
        path: '/holidays/in-range',
        summary: 'Get holidays within a date range',
        tags: ['Holidays'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Response(
        response: 200,
        description: 'Holidays in range retrieved successfully'
    )]
    public function inRange(InRangeHolidayRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $holidays = $this->holidayService->inRange($validated['start_date'], $validated['end_date']);

        return response()->json([
            'success' => true,
            'message' => 'Holidays in range retrieved successfully',
            'data' => HolidayResource::collection($holidays),
            'dates' => $holidays->pluck('date')->map(fn ($d) => $d->format('Y-m-d')),
            'count' => $holidays->count(),
        ]);
    }

    /**
     * Batch delete multiple holidays.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Holiday::findOrFail($id);
                $this->holidayService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} holiday(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
