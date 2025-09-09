<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\CacheServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RateLimitServiceProvider::class,
    Laravel\Sanctum\SanctumServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
];
