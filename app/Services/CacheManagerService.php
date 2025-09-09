<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheManagerService
{
    /**
     * Default cache TTL in minutes
     */
    const DEFAULT_TTL = 60; // 1 hour

    const SHORT_TTL = 15;   // 15 minutes

    const LONG_TTL = 1440;  // 24 hours

    /**
     * Cache tag prefixes for different models
     */
    const CACHE_TAGS = [
        'employees' => 'emp',
        'leave_requests' => 'leave_req',
        'leave_balances' => 'leave_bal',
        'interviews' => 'interview',
        'job_offers' => 'job_offer',
        'employments' => 'employment',
        'reports' => 'reports',
    ];

    /**
     * Remember data with proper cache key and tags
     */
    public function remember(string $key, $callback, ?int $ttl = null, array $tags = [])
    {
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            if (! empty($tags)) {
                return Cache::tags($tags)->remember($key, now()->addMinutes($ttl), $callback);
            }

            return Cache::remember($key, now()->addMinutes($ttl), $callback);
        } catch (\Exception $e) {
            Log::warning('Cache remember failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct callback execution
            return $callback();
        }
    }

    /**
     * Generate consistent cache key
     */
    public function generateKey(string $prefix, array $params = []): string
    {
        $key = $prefix;

        if (! empty($params)) {
            ksort($params); // Ensure consistent ordering
            $key .= '_'.md5(serialize($params));
        }

        return $key;
    }

    /**
     * Clear all caches for a specific model
     */
    public function clearModelCaches(string $modelType, $modelId = null): void
    {
        try {
            $tag = self::CACHE_TAGS[$modelType] ?? $modelType;

            // Clear tagged caches
            Cache::tags([$tag])->flush();

            // Clear specific model caches if ID provided
            if ($modelId) {
                $patterns = [
                    "{$tag}_{$modelId}_*",
                    "{$tag}_list_*",
                    "{$tag}_paginated_*",
                    "reports_{$tag}_*",
                ];

                foreach ($patterns as $pattern) {
                    $this->clearByPattern($pattern);
                }
            }

            // Clear related caches
            $this->clearRelatedCaches($modelType);

            Log::info('Cache cleared for model', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'tag' => $tag,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear model caches', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear caches by pattern (for Redis driver)
     */
    private function clearByPattern(string $pattern): void
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Cache::getRedis();
                $keys = $redis->keys(config('cache.prefix').':'.$pattern);

                if (! empty($keys)) {
                    $redis->del($keys);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Pattern cache clear failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear related model caches
     */
    private function clearRelatedCaches(string $modelType): void
    {
        $relationships = [
            'employees' => ['employments', 'leave_requests', 'leave_balances'],
            'leave_requests' => ['employees', 'leave_balances'],
            'leave_balances' => ['employees', 'leave_requests'],
            'employments' => ['employees'],
            'interviews' => ['reports'],
            'job_offers' => ['reports'],
        ];

        if (isset($relationships[$modelType])) {
            foreach ($relationships[$modelType] as $relatedModel) {
                if (isset(self::CACHE_TAGS[$relatedModel])) {
                    Cache::tags([self::CACHE_TAGS[$relatedModel]])->flush();
                }
            }
        }
    }

    /**
     * Clear all report caches
     */
    public function clearReportCaches(): void
    {
        try {
            Cache::tags(['reports'])->flush();

            Log::info('All report caches cleared');
        } catch (\Exception $e) {
            Log::error('Failed to clear report caches', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear paginated list caches
     */
    public function clearListCaches(string $modelType): void
    {
        try {
            $tag = self::CACHE_TAGS[$modelType] ?? $modelType;

            $patterns = [
                "{$tag}_list_*",
                "{$tag}_paginated_*",
                "{$tag}_search_*",
                "{$tag}_filtered_*",
            ];

            foreach ($patterns as $pattern) {
                $this->clearByPattern($pattern);
            }

        } catch (\Exception $e) {
            Log::error('Failed to clear list caches', [
                'model_type' => $modelType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Warm up cache with fresh data
     */
    public function warmCache(string $key, $callback, ?int $ttl = null, array $tags = []): void
    {
        try {
            $this->remember($key, $callback, $ttl, $tags);

            Log::info('Cache warmed', ['key' => $key]);
        } catch (\Exception $e) {
            Log::warning('Cache warming failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
