<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use App\Listeners\NotifyUserOfFailedJob;

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
