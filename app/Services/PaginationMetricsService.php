<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PaginationMetricsService
{
    /**
     * Get daily pagination metrics
     */
    public function getDailyMetrics(?string $date = null): array
    {
        $date = $date ?: Carbon::now()->format('Y-m-d');
        $key = "pagination_metrics:daily:{$date}";

        return Cache::get($key, [
            'total_requests' => 0,
            'total_execution_time' => 0,
            'total_memory_usage' => 0,
            'slow_queries' => 0,
            'avg_execution_time' => 0,
            'avg_memory_usage' => 0,
        ]);
    }

    /**
     * Get hourly pagination metrics
     */
    public function getHourlyMetrics(?string $date = null, ?int $hour = null): array
    {
        $date = $date ?: Carbon::now()->format('Y-m-d');
        $hour = $hour ?? Carbon::now()->hour;
        $key = "pagination_metrics:hourly:{$date}:{$hour}";

        return Cache::get($key, [
            'requests' => 0,
            'avg_execution_time' => 0,
            'avg_memory_usage' => 0,
        ]);
    }

    /**
     * Get user-specific pagination metrics
     */
    public function getUserMetrics(int $userId, ?string $date = null): array
    {
        $date = $date ?: Carbon::now()->format('Y-m-d');
        $key = "pagination_metrics:user:{$userId}:{$date}";

        return Cache::get($key, [
            'requests' => 0,
            'total_time' => 0,
            'avg_time' => 0,
        ]);
    }

    /**
     * Get metrics for the last 7 days
     */
    public function getWeeklyMetrics(): array
    {
        $metrics = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $metrics[$date] = $this->getDailyMetrics($date);
        }

        return $metrics;
    }

    /**
     * Get comprehensive statistics
     */
    public function getStatistics(): array
    {
        $today = $this->getDailyMetrics();
        $weekly = $this->getWeeklyMetrics();

        // Calculate weekly totals
        $weeklyTotals = [
            'total_requests' => array_sum(array_column($weekly, 'total_requests')),
            'total_execution_time' => array_sum(array_column($weekly, 'total_execution_time')),
            'total_memory_usage' => array_sum(array_column($weekly, 'total_memory_usage')),
            'slow_queries' => array_sum(array_column($weekly, 'slow_queries')),
        ];

        // Calculate averages
        $avgExecutionTime = $weeklyTotals['total_requests'] > 0
            ? $weeklyTotals['total_execution_time'] / $weeklyTotals['total_requests']
            : 0;

        $avgMemoryUsage = $weeklyTotals['total_requests'] > 0
            ? $weeklyTotals['total_memory_usage'] / $weeklyTotals['total_requests']
            : 0;

        return [
            'today' => $today,
            'weekly_totals' => $weeklyTotals,
            'weekly_averages' => [
                'avg_execution_time_ms' => round($avgExecutionTime, 2),
                'avg_memory_usage_mb' => round($avgMemoryUsage / 1024 / 1024, 2),
                'slow_query_percentage' => $weeklyTotals['total_requests'] > 0
                    ? round(($weeklyTotals['slow_queries'] / $weeklyTotals['total_requests']) * 100, 2)
                    : 0,
            ],
            'daily_breakdown' => $weekly,
        ];
    }

    /**
     * Clear metrics for a specific date
     */
    public function clearMetrics(string $date): bool
    {
        $dailyKey = "pagination_metrics:daily:{$date}";

        // Clear hourly metrics for the day
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyKey = "pagination_metrics:hourly:{$date}:{$hour}";
            Cache::forget($hourlyKey);
        }

        return Cache::forget($dailyKey);
    }

    /**
     * Get top slow queries from logs (if enabled)
     */
    public function getSlowQueriesReport(int $limit = 10): array
    {
        // This would need to be implemented based on your logging strategy
        // For now, return basic slow query count from metrics
        $dailyMetrics = $this->getDailyMetrics();

        return [
            'total_slow_queries_today' => $dailyMetrics['slow_queries'],
            'slow_query_threshold_ms' => config('pagination.monitoring.slow_query_threshold', 2000),
            'details' => 'Enable detailed logging to get specific slow query information',
        ];
    }
}
