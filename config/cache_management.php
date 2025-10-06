<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the cache management system
    |
    */

    'default_ttl' => [
        'short' => 15,    // 15 minutes for frequently changing data
        'medium' => 60,   // 1 hour for standard data
        'long' => 1440,   // 24 hours for rarely changing data
    ],

    'cache_tags' => [
        'employees' => 'emp',
        'leave_requests' => 'leave_req',
        'leave_balances' => 'leave_bal',
        'interviews' => 'interview',
        'job_offers' => 'job_offer',
        'employments' => 'employment',
        'reports' => 'reports',
        'departments' => 'dept',
        'positions' => 'pos',
        'work_locations' => 'work_loc',
    ],

    'auto_invalidation' => [
        'enabled' => true,
        'log_operations' => true,
    ],

    'cache_warming' => [
        'enabled' => false,
        'schedule' => [
            'employees_list' => 'hourly',
            'reports' => 'daily',
        ],
    ],

    'performance_monitoring' => [
        'enabled' => true,
        'log_slow_queries' => true,
        'slow_query_threshold' => 1000, // milliseconds
    ],
];
