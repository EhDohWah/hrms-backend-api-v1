<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Default API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // Grants pagination rate limiter
        RateLimiter::for('grants', function (Request $request) {
            $userId = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(config('pagination.rate_limiting.max_requests_per_minute', 60))
                    ->by($userId)
                    ->response(function () {
                        return response()->json([
                            'success' => false,
                            'message' => 'Too many pagination requests. Please slow down.',
                            'error' => 'Rate limit exceeded',
                            'retry_after' => 60,
                        ], 429);
                    }),
                Limit::perHour(config('pagination.rate_limiting.max_requests_per_hour', 1000))
                    ->by($userId)
                    ->response(function () {
                        return response()->json([
                            'success' => false,
                            'message' => 'Hourly pagination limit exceeded. Please try again later.',
                            'error' => 'Rate limit exceeded',
                            'retry_after' => 3600,
                        ], 429);
                    }),
            ];
        });

        // Additional rate limiters for other endpoints
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
