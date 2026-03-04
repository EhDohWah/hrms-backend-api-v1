<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Add security headers to all responses (global middleware)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Exclude auth_token from cookie encryption
        // The auth_token cookie is set as plain text on API routes (no EncryptCookies).
        // Web routes (like /broadcasting/auth) have EncryptCookies, which would try to
        // decrypt the plain-text cookie and nullify it. Excluding it ensures consistent behavior.
        $middleware->encryptCookies(except: ['auth_token']);

        // Add cookie-to-header middleware to both API and web middleware groups
        // This allows authentication via HttpOnly cookie for XSS protection
        // Needed on web group for /broadcasting/auth route
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\AuthenticateFromCookie::class,
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\AuthenticateFromCookie::class,
        ]);

        // Apply global rate limiting to all API routes
        $middleware->api(append: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'module.permission' => \App\Http\Middleware\DynamicModulePermission::class,
            'cors' => \Illuminate\Http\Middleware\HandleCors::class,
            'swagger.auth' => \App\Http\Middleware\SwaggerAuth::class,
        ]);

        // Exempt broadcasting/auth from CSRF verification (uses cookie-based auth)
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Process probation transitions daily at 00:01
        $schedule->call(function () {
            $service = app(\App\Services\ProbationTransitionService::class);
            $results = $service->processTransitions();
            \Illuminate\Support\Facades\Log::info('Probation transition scheduled task completed', $results);
        })
            ->daily()
            ->at('00:01')
            ->timezone('Asia/Bangkok')
            ->name('probation-transition-service');

        // Permanently delete soft-deleted Employee/Grant/Department records after 90 days
        $schedule->command('model:prune')
            ->daily()
            ->at('02:00')
            ->timezone('Asia/Bangkok')
            ->name('model-prune');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Validation errors — return field-level errors
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Authentication errors
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }
        });

        // Authorization errors
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        });

        // Model not found
        $exceptions->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        // Route not found
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        // Rate limiting
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ], 429);
            }
        });

        // Generic catch-all for API errors
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Self-rendering exceptions (have their own render() method)
            if (method_exists($e, 'render')) {
                return null;
            }

            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'An unexpected error occurred' : $e->getMessage(),
            ], $status);
        });
    })->create();
