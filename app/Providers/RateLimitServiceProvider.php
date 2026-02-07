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
        // Global API rate limiter - applied to all API routes
        // Configurable via environment variables for flexibility
        RateLimiter::for('api', function (Request $request) {
            $maxPerMinute = (int) config('app.rate_limit_per_minute', 120);
            $maxPerHour = (int) config('app.rate_limit_per_hour', 3600);

            $identifier = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute($maxPerMinute)
                    ->by($identifier)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Too many requests. Please slow down.',
                            'error' => 'Rate limit exceeded',
                            'retry_after' => $headers['Retry-After'] ?? 60,
                        ], 429);
                    }),
                Limit::perHour($maxPerHour)
                    ->by($identifier)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Hourly request limit exceeded. Please try again later.',
                            'error' => 'Rate limit exceeded',
                            'retry_after' => $headers['Retry-After'] ?? 3600,
                        ], 429);
                    }),
            ];
        });

        // Stricter rate limiter for authentication endpoints
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many authentication attempts. Please wait before trying again.',
                        'error' => 'Rate limit exceeded',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Stricter rate limiter for sensitive operations (password reset, etc.)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perHour(10)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many sensitive operation attempts.',
                        'error' => 'Rate limit exceeded',
                        'retry_after' => 3600,
                    ], 429);
                });
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

        // Upload rate limiter
        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many upload attempts. Please wait.',
                        'error' => 'Rate limit exceeded',
                        'retry_after' => 60,
                    ], 429);
                });
        });
    }
}
