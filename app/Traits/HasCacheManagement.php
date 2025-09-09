<?php

namespace App\Traits;

use App\Services\CacheManagerService;
use Illuminate\Support\Facades\Auth;

trait HasCacheManagement
{
    /**
     * Get cache manager instance
     */
    protected function getCacheManager(): CacheManagerService
    {
        return app(CacheManagerService::class);
    }

    /**
     * Generate cache key for model operations
     */
    protected function getModelCacheKey(string $operation, array $params = []): string
    {
        $modelName = $this->getModelName();
        $prefix = "{$modelName}_{$operation}";

        // Add user context if available
        if (Auth::check()) {
            $params['user_id'] = Auth::id();
        }

        return $this->getCacheManager()->generateKey($prefix, $params);
    }

    /**
     * Generate cache key for list operations
     */
    protected function getListCacheKey(array $filters = [], int $page = 1, int $perPage = 10): string
    {
        $modelName = $this->getModelName();

        $params = array_merge($filters, [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $this->getModelCacheKey('list', $params);
    }

    /**
     * Get model name from controller class
     */
    protected function getModelName(): string
    {
        $className = class_basename($this);

        // Remove 'Controller' suffix and convert to snake_case
        $modelName = str_replace('Controller', '', $className);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
    }

    /**
     * Get cache tags for this model
     */
    protected function getCacheTags(): array
    {
        $modelName = $this->getModelName();

        return [
            CacheManagerService::CACHE_TAGS[$modelName] ?? $modelName,
        ];
    }

    /**
     * Cache and return paginated results
     */
    protected function cacheAndPaginate($query, array $filters = [], int $perPage = 10)
    {
        $page = request('page', 1);
        $cacheKey = $this->getListCacheKey($filters, $page, $perPage);

        return $this->getCacheManager()->remember(
            $cacheKey,
            function () use ($query, $perPage) {
                return $query->paginate($perPage);
            },
            CacheManagerService::SHORT_TTL,
            $this->getCacheTags()
        );
    }

    /**
     * Cache single model result
     */
    protected function cacheModel($model, string $operation = 'show')
    {
        if (! $model) {
            return null;
        }

        $cacheKey = $this->getModelCacheKey($operation, ['id' => $model->id]);

        return $this->getCacheManager()->remember(
            $cacheKey,
            function () use ($model) {
                return $model;
            },
            CacheManagerService::DEFAULT_TTL,
            $this->getCacheTags()
        );
    }

    /**
     * Clear all caches for this model
     */
    protected function clearModelCaches($modelId = null): void
    {
        $modelName = $this->getModelName();
        $this->getCacheManager()->clearModelCaches($modelName, $modelId);
    }

    /**
     * Clear list caches only
     */
    protected function clearListCaches(): void
    {
        $modelName = $this->getModelName();
        $this->getCacheManager()->clearListCaches($modelName);
    }

    /**
     * Invalidate cache after successful operations
     */
    protected function invalidateCacheAfterWrite($model = null): void
    {
        $modelId = $model?->id ?? null;

        // Clear specific model caches
        $this->clearModelCaches($modelId);

        // Clear list caches to ensure index reflects changes
        $this->clearListCaches();

        // Clear report caches if this affects reports
        if ($this->affectsReports()) {
            $this->getCacheManager()->clearReportCaches();
        }
    }

    /**
     * Determine if this model affects reports
     */
    protected function affectsReports(): bool
    {
        $reportAffectingModels = [
            'employee', 'leave_request', 'leave_balance',
            'interview', 'job_offer', 'employment',
        ];

        return in_array($this->getModelName(), $reportAffectingModels);
    }

    /**
     * Get cache statistics for debugging
     */
    protected function getCacheStats(): array
    {
        $modelName = $this->getModelName();

        return [
            'model' => $modelName,
            'cache_tags' => $this->getCacheTags(),
            'cache_driver' => config('cache.default'),
            'affects_reports' => $this->affectsReports(),
        ];
    }
}
