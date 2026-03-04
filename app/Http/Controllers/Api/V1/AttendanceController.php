<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\IndexAttendanceRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD operations for employee attendance records.
 */
class AttendanceController extends BaseApiController
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Return dropdown options for the attendance modal (employee list + statuses).
     */
    public function options(): JsonResponse
    {
        $options = $this->attendanceService->options();

        return response()->json([
            'success' => true,
            'message' => 'Options retrieved successfully',
            'data' => $options,
        ]);
    }

    /**
     * List all attendance records with pagination, filtering, and sorting.
     */
    public function index(IndexAttendanceRequest $request): JsonResponse
    {
        $paginator = $this->attendanceService->list($request->validated());

        return AttendanceResource::collection($paginator)
            ->additional(['success' => true, 'message' => 'Attendance records retrieved successfully'])
            ->response();
    }

    /**
     * Get a specific attendance record.
     */
    public function show(Attendance $attendance): JsonResponse
    {
        $attendance = $this->attendanceService->show($attendance);

        return AttendanceResource::make($attendance)
            ->additional(['success' => true, 'message' => 'Attendance record retrieved successfully'])
            ->response();
    }

    /**
     * Create a new attendance record.
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $attendance = $this->attendanceService->create($request->validated());

        return AttendanceResource::make($attendance)
            ->additional(['success' => true, 'message' => 'Attendance record created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing attendance record.
     */
    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $attendance = $this->attendanceService->update($attendance, $request->validated());

        return AttendanceResource::make($attendance)
            ->additional(['success' => true, 'message' => 'Attendance record updated successfully'])
            ->response();
    }

    /**
     * Delete an attendance record.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->attendanceService->delete($attendance);

        return $this->successResponse(null, 'Attendance record deleted successfully');
    }

    /**
     * Batch delete multiple attendance records.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Attendance::findOrFail($id);
                $this->attendanceService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} attendance(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
