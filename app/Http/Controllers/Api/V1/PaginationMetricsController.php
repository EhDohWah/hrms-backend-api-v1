<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PaginationMetricsService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Provides pagination performance monitoring and slow query reporting.
 */
#[OA\Tag(name: 'Pagination Metrics', description: 'API Endpoints for pagination performance monitoring')]
class PaginationMetricsController extends BaseApiController
{
    public function __construct(
        private readonly PaginationMetricsService $metricsService,
    ) {}

    #[OA\Get(
        path: '/pagination-metrics/statistics',
        summary: 'Get comprehensive pagination performance statistics',
        operationId: 'getPaginationStatistics',
        security: [['bearerAuth' => []]],
        tags: ['Pagination Metrics'],
        responses: [
            new OA\Response(response: 200, description: 'Pagination statistics retrieved successfully'),
        ]
    )]
    public function statistics(): JsonResponse
    {
        $statistics = $this->metricsService->getStatistics();

        return response()->json([
            'success' => true,
            'message' => 'Pagination statistics retrieved successfully',
            'data' => $statistics,
        ]);
    }

    #[OA\Get(
        path: '/pagination-metrics/daily/{date}',
        summary: 'Get pagination metrics for a specific date',
        operationId: 'getDailyPaginationMetrics',
        security: [['bearerAuth' => []]],
        tags: ['Pagination Metrics'],
        parameters: [
            new OA\Parameter(name: 'date', in: 'path', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daily metrics retrieved successfully'),
        ]
    )]
    public function dailyMetrics(?string $date = null): JsonResponse
    {
        $metrics = $this->metricsService->getDailyMetrics($date);

        return response()->json([
            'success' => true,
            'message' => 'Daily metrics retrieved successfully',
            'data' => [
                'date' => $date ?: now()->format('Y-m-d'),
                'metrics' => $metrics,
            ],
        ]);
    }

    #[OA\Get(
        path: '/pagination-metrics/slow-queries',
        summary: 'Get a report of slow pagination queries',
        operationId: 'getSlowQueriesReport',
        security: [['bearerAuth' => []]],
        tags: ['Pagination Metrics'],
        responses: [
            new OA\Response(response: 200, description: 'Slow queries report retrieved successfully'),
        ]
    )]
    public function slowQueriesReport(): JsonResponse
    {
        $report = $this->metricsService->getSlowQueriesReport();

        return response()->json([
            'success' => true,
            'message' => 'Slow queries report retrieved successfully',
            'data' => $report,
        ]);
    }

    #[OA\Delete(
        path: '/pagination-metrics/{date}',
        summary: 'Clear pagination metrics for a specific date',
        operationId: 'clearPaginationMetrics',
        security: [['bearerAuth' => []]],
        tags: ['Pagination Metrics'],
        parameters: [
            new OA\Parameter(name: 'date', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Metrics cleared successfully'),
        ]
    )]
    public function clearMetrics(string $date): JsonResponse
    {
        $this->metricsService->clearMetrics($date);

        return response()->json([
            'success' => true,
            'message' => "Metrics for {$date} cleared successfully",
        ]);
    }
}
