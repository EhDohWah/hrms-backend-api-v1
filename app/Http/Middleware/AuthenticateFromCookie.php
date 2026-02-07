<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthenticateFromCookie Middleware
 *
 * Extracts the authentication token from HttpOnly cookie and adds it
 * to the Authorization header if not already present.
 *
 * This middleware enables dual authentication modes:
 * 1. Traditional Bearer token in Authorization header (for backward compatibility)
 * 2. HttpOnly cookie (for enhanced XSS protection)
 *
 * The cookie approach is preferred as it prevents JavaScript from accessing
 * the token, making it immune to XSS attacks.
 */
class AuthenticateFromCookie
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
        // If no Authorization header but auth_token cookie exists, use the cookie
        if (!$request->bearerToken() && $request->cookie('auth_token')) {
            $request->headers->set(
                'Authorization',
                'Bearer ' . $request->cookie('auth_token')
            );
        }

        return $next($request);
    }
}
