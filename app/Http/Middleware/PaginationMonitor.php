<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PaginationMonitor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Log request details if monitoring is enabled
        if (config('pagination.monitoring.track_usage_metrics', true)) {
            $this->logRequestStart($request);
        }

        $response = $next($request);

        // Calculate performance metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = $endMemory - $startMemory;

        // Log slow queries if enabled
        if (config('pagination.monitoring.log_slow_queries', true)) {
            $threshold = config('pagination.monitoring.slow_query_threshold', 2000);
            
            if ($executionTime > $threshold) {
                Log::warning('Slow pagination query detected', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'execution_time_ms' => round($executionTime, 2),
                    'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                    'user_id' => $request->user()?->id,
                    'ip' => $request->ip(),
                    'params' => $request->all(),
                ]);
            }
        }

        // Track usage metrics in cache
        if (config('pagination.monitoring.track_usage_metrics', true)) {
            $this->trackUsageMetrics($request, $executionTime, $memoryUsage);
        }

        // Add performance headers to response
        $response->headers->set('X-Pagination-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Pagination-Memory', round($memoryUsage / 1024 / 1024, 2) . 'MB');

        return $response;
    }

    /**
     * Log the start of a pagination request
     */
    private function logRequestStart(Request $request): void
    {
        Log::info('Pagination request started', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'params' => $request->all(),
        ]);
    }

    /**
     * Track usage metrics in cache
     */
    private function trackUsageMetrics(Request $request, float $executionTime, int $memoryUsage): void
    {
        $date = now()->format('Y-m-d');
        $hour = now()->format('H');
        
        // Track daily metrics
        $dailyKey = "pagination_metrics:daily:{$date}";
        $dailyMetrics = Cache::get($dailyKey, [
            'total_requests' => 0,
            'total_execution_time' => 0,
            'total_memory_usage' => 0,
            'slow_queries' => 0,
        ]);

        $dailyMetrics['total_requests']++;
        $dailyMetrics['total_execution_time'] += $executionTime;
        $dailyMetrics['total_memory_usage'] += $memoryUsage;

        if ($executionTime > config('pagination.monitoring.slow_query_threshold', 2000)) {
            $dailyMetrics['slow_queries']++;
        }

        Cache::put($dailyKey, $dailyMetrics, now()->addDays(7));

        // Track hourly metrics
        $hourlyKey = "pagination_metrics:hourly:{$date}:{$hour}";
        $hourlyMetrics = Cache::get($hourlyKey, [
            'requests' => 0,
            'avg_execution_time' => 0,
            'avg_memory_usage' => 0,
        ]);

        $hourlyMetrics['requests']++;
        $hourlyMetrics['avg_execution_time'] = (
            ($hourlyMetrics['avg_execution_time'] * ($hourlyMetrics['requests'] - 1)) + $executionTime
        ) / $hourlyMetrics['requests'];
        $hourlyMetrics['avg_memory_usage'] = (
            ($hourlyMetrics['avg_memory_usage'] * ($hourlyMetrics['requests'] - 1)) + $memoryUsage
        ) / $hourlyMetrics['requests'];

        Cache::put($hourlyKey, $hourlyMetrics, now()->addDays(1));

        // Track user-specific metrics
        if ($userId = $request->user()?->id) {
            $userKey = "pagination_metrics:user:{$userId}:{$date}";
            $userMetrics = Cache::get($userKey, ['requests' => 0, 'total_time' => 0]);
            $userMetrics['requests']++;
            $userMetrics['total_time'] += $executionTime;
            Cache::put($userKey, $userMetrics, now()->addDays(1));
        }
    }
}