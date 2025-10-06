## API Versioning (Non-Breaking) – Implementation Notes

### Goals
- Preserve all existing endpoints exactly as they are reachable today under `/api/v1/...`.
- Introduce a clean path to add new, mobile-focused endpoints under `/api/v2/...` without touching v1.

### What Changed
- Global API prefix updated to `api` (from `api/v1`). Effective v1 paths remain identical because existing routes are now grouped under `v1`.

```12:20:bootstrap/app.php
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
```

- Existing API files are wrapped in a `v1` route group. A `v2` group was added that includes a new `routes/api-v2.php` file.

```11:28:routes/api.php
// Export route (outside of auth middleware)
Route::get('/export-employees', [EmployeeController::class, 'exportEmployees']);

// Versioned groups (non-breaking): keep all existing routes under v1
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/employees.php';
    require __DIR__ . '/api/grants.php';
    require __DIR__ . '/api/payroll.php';
    require __DIR__ . '/api/employment.php';
});

// v2 scaffold for mobile-focused endpoints
Route::prefix('v2')->group(function () {
    $v2 = __DIR__ . '/api-v2.php';
    if (file_exists($v2)) {
        require $v2;
    }
});
```

- New v2 scaffold for mobile:

```1:17:routes/api-v2.php
<?php

use Illuminate\Support\Facades\Route;

// All v2 endpoints should be explicitly grouped and typically protected with auth + permissions
// Example mobile-focused endpoints (to be implemented as needed)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('mobile')->group(function () {
        // e.g., GET /api/v2/mobile/ping
        Route::get('/ping', function () {
            return ['success' => true, 'version' => 'v2', 'service' => 'mobile'];
        });
    });
});
```

### Backward Compatibility
- Before: Global prefix `api/v1` + ungrouped routes → endpoints at `/api/v1/...`.
- After: Global prefix `api` + `Route::prefix('v1')` → endpoints still at `/api/v1/...`.
- No controllers or middleware were changed; RBAC (`permission:*`) and `auth:sanctum` remain intact.

### How To Add v2 Endpoints
1) Implement new controllers/resources optimized for mobile (lean payloads, cursor pagination, signed URLs for files).
2) Register routes in `routes/api-v2.php` under the appropriate prefixes.
3) Keep `auth:sanctum` and `permission:*` middleware consistent with v1.

Example add:
```
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('mobile')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V2\MobileMeController::class, 'show'])
            ->middleware('permission:employee.read');
    });
});
```

### Verification
- Hit an existing endpoint (e.g., `GET /api/v1/payrolls`) — should behave exactly the same as before.
- Hit v2 scaffold (e.g., `GET /api/v2/mobile/ping`) — returns a small health payload.
- Optional: `php artisan route:list | findstr /i ": v1"` and `": v2"` to confirm both groups.

### Rollout Notes
- v1 remains stable for current clients.
- v2 is isolated; you can iterate on mobile-first shapes without breaking v1.
- Consider separate OpenAPI groups for v1/v2 when documenting endpoints.


