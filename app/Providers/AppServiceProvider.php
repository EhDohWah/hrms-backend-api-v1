<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\JobOffer;
use App\Observers\EmployeeFundingAllocationObserver;
use App\Observers\EmployeeObserver;
use App\Observers\EmploymentObserver;
use App\Observers\JobOfferObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Force HTTPS in production environment
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Also force HTTPS if configured via environment variable
        // This is useful when behind a load balancer or reverse proxy
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Handle proxied HTTPS detection (when behind load balancer)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }

        // Register the EmployeeObserver to automatically create leave balances for new employees.
        Employee::observe(EmployeeObserver::class);
        JobOffer::observe(JobOfferObserver::class);

        // Register the EmploymentObserver for data validation and consistency
        Employment::observe(EmploymentObserver::class);

        // Register observer to track funding allocation changes for audit trail
        EmployeeFundingAllocation::observe(EmployeeFundingAllocationObserver::class);
    }
}
