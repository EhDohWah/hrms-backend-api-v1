<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaginationMetricsService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Pagination Metrics', description: 'API endpoints for pagination performance monitoring')]
class PaginationMetricsController extends Controller
{
    protected PaginationMetricsService $metricsService;

    public function __construct(PaginationMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    #[OA\Get(
        path: '/pagination-metrics/statistics',
        operationId: 'getPaginationStatistics',
        summary: 'Get comprehensive pagination performance statistics',
        description: 'Returns detailed pagination metrics including daily, weekly, and performance statistics',
        tags: ['Pagination Metrics'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Pagination statistics retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getStatistics()
    {
        try {
            $statistics = $this->metricsService->getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Pagination statistics retrieved successfully',
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pagination statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/pagination-metrics/daily/{date}',
        operationId: 'getDailyPaginationMetrics',
        summary: 'Get daily pagination metrics',
        description: 'Returns pagination metrics for a specific date',
        tags: ['Pagination Metrics'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'path', required: false, description: 'Date in Y-m-d format', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daily metrics retrieved successfully'),
        ]
    )]
    public function getDailyMetrics(Request $request, ?string $date = null)
    {
        try {
            $metrics = $this->metricsService->getDailyMetrics($date);

            return response()->json([
                'success' => true,
                'message' => 'Daily metrics retrieved successfully',
                'data' => [
                    'date' => $date ?: now()->format('Y-m-d'),
                    'metrics' => $metrics,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/pagination-metrics/slow-queries',
        operationId: 'getSlowQueriesReport',
        summary: 'Get slow queries report',
        description: 'Returns information about slow pagination queries',
        tags: ['Pagination Metrics'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Slow queries report retrieved successfully'),
        ]
    )]
    public function getSlowQueriesReport()
    {
        try {
            $report = $this->metricsService->getSlowQueriesReport();

            return response()->json([
                'success' => true,
                'message' => 'Slow queries report retrieved successfully',
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve slow queries report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/pagination-metrics/clear/{date}',
        operationId: 'clearPaginationMetrics',
        summary: 'Clear pagination metrics for a specific date',
        description: 'Clears all pagination metrics for the specified date',
        tags: ['Pagination Metrics'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'path', required: true, description: 'Date in Y-m-d format', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Metrics cleared successfully'),
        ]
    )]
    public function clearMetrics(string $date)
    {
        try {
            $cleared = $this->metricsService->clearMetrics($date);

            return response()->json([
                'success' => $cleared,
                'message' => $cleared
                    ? "Metrics for {$date} cleared successfully"
                    : "No metrics found for {$date}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
