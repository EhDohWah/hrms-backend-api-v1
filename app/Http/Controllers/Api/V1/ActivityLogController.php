<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ActivityLog\ForSubjectRequest;
use App\Http\Requests\ActivityLog\ListActivityLogsRequest;
use App\Http\Requests\ActivityLog\RecentActivityLogsRequest;
use App\Http\Resources\ActivityLogResource;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends BaseApiController
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function index(ListActivityLogsRequest $request): JsonResponse
    {
        $logs = $this->activityLogService->list($request->validated());

        return ActivityLogResource::collection($logs)
            ->additional(['success' => true, 'message' => 'Activity logs retrieved successfully'])
            ->response();
    }

    public function forSubject(ForSubjectRequest $request, string $type, int $id): JsonResponse
    {
        $logs = $this->activityLogService->forSubject($type, $id, $request->validated('per_page'));

        return ActivityLogResource::collection($logs)
            ->additional(['success' => true, 'message' => 'Activity logs for subject retrieved successfully'])
            ->response();
    }

    public function recent(RecentActivityLogsRequest $request): JsonResponse
    {
        $logs = $this->activityLogService->recent($request->validated('limit'));

        return ActivityLogResource::collection($logs)
            ->additional(['success' => true, 'message' => 'Recent activity logs retrieved successfully'])
            ->response();
    }
}
