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
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'module.permission' => \App\Http\Middleware\DynamicModulePermission::class,
            'cors' => \Illuminate\Http\Middleware\HandleCors::class,
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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Using Laravel's default exception handling
    })->create();
