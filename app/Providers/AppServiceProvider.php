<?php

namespace App\Providers;

use App\Models\Employee;
use App\Observers\EmployeeObserver;
use Illuminate\Support\ServiceProvider;
use App\Models\JobOffer;
use App\Observers\JobOfferObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the EmployeeObserver to automatically create leave balances for new employees.
        Employee::observe(EmployeeObserver::class);
        JobOffer::observe(JobOfferObserver::class);
    }
}
