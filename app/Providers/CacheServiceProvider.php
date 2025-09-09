<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Observers\CacheInvalidationObserver;
use App\Services\CacheManagerService;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the cache manager as a singleton
        $this->app->singleton(CacheManagerService::class, function ($app) {
            return new CacheManagerService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register cache invalidation observers for all models
        $this->registerCacheObservers();
    }

    /**
     * Register cache invalidation observers
     */
    private function registerCacheObservers(): void
    {
        $models = [
            Employee::class,
            Employment::class,
            LeaveRequest::class,
            LeaveBalance::class,
            Interview::class,
            JobOffer::class,
        ];

        foreach ($models as $model) {
            $model::observe(CacheInvalidationObserver::class);
        }
    }
}
