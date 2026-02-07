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

        // Purge expired recycle bin items daily at 02:00 (30-day retention)
        $schedule->command('recycle-bin:purge --days=30')
            ->daily()
            ->at('02:00')
            ->timezone('Asia/Bangkok')
            ->name('recycle-bin-purge');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Using Laravel's default exception handling
    })->create();
