<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SwaggerAuth Middleware
 *
 * Protects Swagger API documentation in production environments.
 * Can be configured to:
 * - Completely disable Swagger in production
 * - Require authentication (admin role) to access documentation
 *
 * Configure via environment variables:
 * - L5_SWAGGER_ENABLE_IN_PRODUCTION=false (disable in production)
 * - L5_SWAGGER_REQUIRE_AUTH=true (require authentication)
 */
class SwaggerAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if Swagger should be enabled in production
        $enableInProduction = env('L5_SWAGGER_ENABLE_IN_PRODUCTION', false);

        // Disable Swagger completely in production if not explicitly enabled
        if (app()->environment('production') && !$enableInProduction) {
            return $this->denyAccess('API documentation is not available in production.');
        }

        // Check if authentication is required for Swagger access
        $requireAuth = env('L5_SWAGGER_REQUIRE_AUTH', false);

        if ($requireAuth) {
            // User must be authenticated
            if (!auth()->check()) {
                return $this->denyAccess('Authentication required to access API documentation.');
            }

            // Optionally require admin role
            $requireAdmin = env('L5_SWAGGER_REQUIRE_ADMIN', true);
            if ($requireAdmin && !$this->isAdmin($request->user())) {
                return $this->denyAccess('Admin access required to view API documentation.');
            }
        }

        return $next($request);
    }

    /**
     * Check if the user has admin role.
     *
     * @param  mixed  $user
     * @return bool
     */
    protected function isAdmin($user): bool
    {
        if (!$user) {
            return false;
        }

        // Check using Spatie Permission if available
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin');
        }

        // Fallback: check role directly
        if (isset($user->role)) {
            return strtolower($user->role) === 'admin';
        }

        return false;
    }

    /**
     * Return a deny access response.
     *
     * @param  string  $message
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function denyAccess(string $message): Response
    {
        // Return 404 to avoid revealing the existence of documentation
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 404);
    }
}
