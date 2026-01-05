<?php

namespace App\Http\Middleware;

use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * DynamicModulePermission Middleware
 *
 * Enforces module-based permissions dynamically based on HTTP method.
 *
 * Usage in routes:
 * Route::get('/employees', [EmployeeController::class, 'index'])
 *     ->middleware('module.permission:employee');
 *
 * Logic:
 * - GET requests require read permission
 * - POST/PUT/PATCH/DELETE requests require edit permissions
 * - Permissions are retrieved from the Module model dynamically
 */
class DynamicModulePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $moduleName  The module name (e.g., 'employee', 'user_management')
     */
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Get module from cache or database
        $module = Cache::remember(
            "module:{$moduleName}",
            now()->addHours(24),
            fn () => Module::where('name', $moduleName)->where('is_active', true)->first()
        );

        if (! $module) {
            return response()->json([
                'success' => false,
                'message' => "Module '{$moduleName}' not found or inactive",
            ], 404);
        }

        // Determine required permission based on HTTP method
        $method = $request->method();
        $requiredPermissions = $this->getRequiredPermissions($module, $method);

        // Check if user has any of the required permissions
        if (empty($requiredPermissions)) {
            // No permissions required for this method (shouldn't happen)
            return $next($request);
        }

        // Check if user has at least one of the required permissions
        foreach ($requiredPermissions as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        // User doesn't have required permission
        return response()->json([
            'success' => false,
            'message' => $this->getPermissionDeniedMessage($method, $module->display_name),
            'required_permissions' => $requiredPermissions,
        ], 403);
    }

    /**
     * Get required permissions based on HTTP method
     */
    protected function getRequiredPermissions(Module $module, string $method): array
    {
        return match ($method) {
            'GET', 'HEAD' => [$module->read_permission],
            'POST', 'PUT', 'PATCH', 'DELETE' => $module->edit_permissions ?? ["{$module->name}.edit"],
            default => [],
        };
    }

    /**
     * Get user-friendly permission denied message
     */
    protected function getPermissionDeniedMessage(string $method, string $moduleName): string
    {
        return match ($method) {
            'GET', 'HEAD' => "You do not have permission to view {$moduleName} records",
            'POST' => "You do not have permission to create {$moduleName} records",
            'PUT', 'PATCH' => "You do not have permission to update {$moduleName} records",
            'DELETE' => "You do not have permission to delete {$moduleName} records",
            default => "You do not have permission to perform this action on {$moduleName}",
        };
    }
}
