<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        'api/*',
        'login',
        'logout',
        'register',
        'user',
        'sanctum/csrf-cookie',
        'broadcasting/auth',

        // Add more if you have custom endpoints
    ],

    'allowed_methods' => ['*'],

    // List all *specific* frontend origins here (no wildcard '*')
    'allowed_origins' => [
        'http://localhost:8080',                  // Local Vue dev
        'http://127.0.0.1:8080',                  // Local fallback
        'https://hrmsfe.netlify.app',             // Netlify production
        // Add any preview branches if needed, eg:
        // 'https://deploy-preview-123--hrmsfe.netlify.app'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Disposition',
        'Content-Type',
        'Content-Length',
    ],

    'max_age' => 0,

    // Essential for Sanctum, cookies, or any session auth
    'supports_credentials' => true,

];
