<?php

return [
    'default_per_page' => 10,
    'max_per_page' => 100,
    'default_sort_direction' => 'desc',

    // Performance settings
    'query_timeout' => 30,
    'enable_query_logging' => env('PAGINATION_QUERY_LOGGING', false),
    'cache_filter_options' => true,
    'cache_duration' => 3600,

    // MSSQL specific tuning
    'mssql' => [
        'enable_fulltext_search' => env('MSSQL_FULLTEXT_ENABLED', false),
        'query_hints' => ['OPTION (RECOMPILE)'],
        'index_maintenance' => [
            'auto_update_statistics' => true,
            'rebuild_threshold' => 30,
        ],
    ],

    // Rate limiting
    'rate_limiting' => [
        'enabled' => true,
        'max_requests_per_minute' => 60,
        'max_requests_per_hour' => 1000,
    ],

    // Monitoring and logging
    'monitoring' => [
        'log_slow_queries' => true,
        'slow_query_threshold' => 2000,
        'track_usage_metrics' => true,
    ],
];
