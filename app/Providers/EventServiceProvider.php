<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use App\Listeners\NotifyOnImportFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        JobFailed::class => [
            NotifyOnImportFailed::class,
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
