<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders Middleware
 *
 * Adds essential security headers to all HTTP responses.
 * This protects against common web vulnerabilities including:
 * - Clickjacking (X-Frame-Options)
 * - MIME type sniffing (X-Content-Type-Options)
 * - XSS attacks (X-XSS-Protection - legacy browsers)
 * - SSL stripping (Strict-Transport-Security)
 * - Content injection (Content-Security-Policy)
 * - Information leakage (Referrer-Policy)
 * - Browser feature abuse (Permissions-Policy)
 */
class SecurityHeaders
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
        $response = $next($request);

        // Prevent clickjacking - only allow same-origin framing
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // XSS Protection for legacy browsers (modern browsers have built-in protection)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy - control information sent in Referer header
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy - restrict browser features
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS - Force HTTPS (only in production with HTTPS)
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Content Security Policy
        $csp = $this->buildContentSecurityPolicy();
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }

    /**
     * Build Content Security Policy header value
     *
     * @return string
     */
    protected function buildContentSecurityPolicy(): string
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'", // unsafe-inline needed for many CSS frameworks
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        // Allow additional sources in development for debugging tools
        if (app()->environment('local', 'development')) {
            // Allow eval for Vue devtools and hot reload
            $policies[1] = "script-src 'self' 'unsafe-eval'";
            // Allow WebSocket connections for Vite HMR and Laravel Reverb
            $policies[5] = "connect-src 'self' ws: wss: http://localhost:* http://127.0.0.1:*";
        }

        return implode('; ', $policies);
    }
}
