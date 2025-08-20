<?php

namespace App\Providers;

use App\Listeners\NotifyUserOfFailedJob;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        JobFailed::class => [
            NotifyUserOfFailedJob::class,
        ],
    ];

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
        //
    }
}
