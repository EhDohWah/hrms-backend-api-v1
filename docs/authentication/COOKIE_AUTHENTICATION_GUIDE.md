# Cookie-Based Authentication Guide

## Overview

The HRMS application uses **Sanctum personal access tokens** transported via **HttpOnly cookies** for API authentication. This approach prevents XSS attacks from stealing tokens via JavaScript, while still working as a standard Bearer token on the backend.

## Architecture

```
Frontend (SPA)                          Backend (Laravel API)
==================                      ====================

1. POST /login  ───────────────────────> AuthController@login
   (email + password)                    - Validates credentials
                                         - Creates Sanctum token
                                         - Returns JSON response
   <─────────────────────────────────── + Set-Cookie: auth_token=<token>
   (stores user data in localStorage)     (HttpOnly, Secure, SameSite)

2. GET /api/v1/admin/modules ──────────> AuthenticateFromCookie middleware
   Cookie: auth_token=<token>            - Reads cookie
   (browser sends cookie automatically)  - Sets Authorization: Bearer <token>
                                         ───> auth:sanctum middleware
                                              - Validates token
                                              ───> Controller
   <──────────────────────────────────── JSON response
```

## Key Components

### Backend

| Component | File | Role |
|-----------|------|------|
| `AuthController` | `app/Http/Controllers/Api/AuthController.php` | Sets/clears `auth_token` cookie on login/logout/refresh |
| `AuthenticateFromCookie` | `app/Http/Middleware/AuthenticateFromCookie.php` | Reads cookie, injects `Authorization: Bearer` header |
| `bootstrap/app.php` | `bootstrap/app.php` | Registers middleware, excludes `auth_token` from encryption |

### Frontend

| Component | File | Role |
|-----------|------|------|
| `api.config.js` | `src/config/api.config.js` | Configures `BASE_URL` from `VITE_API_BASE_URL` |
| `api.service.js` | `src/services/api.service.js` | All requests use `credentials: 'include'` |
| `auth.service.js` | `src/services/auth.service.js` | Login/logout flow, expiration tracking |
| `module.service.js` | `src/services/module.service.js` | Module API calls with `credentials: 'include'` |

## Cookie Parameters

All cookie parameters are centralized in `AuthController::authCookieParams()`:

```php
protected function authCookieParams(string $token, int $minutes): array
{
    $isProduction = app()->environment('production');
    $secure       = $isProduction;
    $sameSite     = $isProduction ? 'none' : 'lax';

    return [
        'auth_token',   // name
        $token,         // value
        $minutes,       // minutes (negative = expire/clear)
        '/',            // path
        null,           // domain (current domain)
        $secure,        // secure (HTTPS only in production)
        true,           // httpOnly (not accessible via JS)
        false,          // raw
        $sameSite,      // sameSite policy
    ];
}
```

| Parameter | Development | Production |
|-----------|-------------|------------|
| `secure` | `false` (HTTP) | `true` (HTTPS required) |
| `sameSite` | `lax` | `none` |
| `httpOnly` | `true` | `true` |
| `domain` | `null` (current) | `null` (current) |

## Why These SameSite Values?

### The Problem

The frontend and backend run on **different origins**:

| Environment | Frontend | Backend | Same Site? |
|-------------|----------|---------|------------|
| Development | `http://localhost:8080` | `http://localhost:8000` | Yes (both `localhost`) |
| Production | `https://hrmsfe.netlify.app` | `https://your-api-domain.com` | **No** |

When the browser considers two URLs as **different sites**, it applies `SameSite` cookie restrictions:

| SameSite Value | Cross-Site AJAX (fetch) | Cross-Site Navigation | Requires Secure |
|----------------|------------------------|-----------------------|-----------------|
| `strict` | Blocked | Blocked | No |
| `lax` | Blocked | Allowed (GET only) | No |
| `none` | Allowed | Allowed | **Yes** |

### The Solution

- **Production** (`SameSite=none` + `Secure=true`): The cookie is sent on cross-site fetch requests. This is required because the frontend (Netlify) and backend (API server) are on different domains. `Secure=true` ensures the cookie is only sent over HTTPS.

- **Development** (`SameSite=lax`): Both frontend and backend use `localhost`, making them the same site. `lax` is sufficient and doesn't require HTTPS.

## Configuration Checklist

### Backend `.env`

```env
# Sanctum must know which domains are "stateful" (same-site SPA origins)
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,localhost:3000

# Session cookie settings
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
```

### Backend `config/cors.php`

```php
'allowed_origins' => [
    'http://localhost:8080',              // Local dev
    'https://hrmsfe.netlify.app',         // Production
],
'supports_credentials' => true,           // Required for cookies
```

### Backend `bootstrap/app.php`

```php
// Exclude auth_token from cookie encryption (it's set as plain text)
$middleware->encryptCookies(except: ['auth_token']);

// Prepend cookie-to-header middleware to API and web groups
$middleware->api(prepend: [
    \App\Http\Middleware\AuthenticateFromCookie::class,
]);
```

### Frontend `.env.development`

```env
# MUST use "localhost" (not "127.0.0.1") to match the Vite dev server origin
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_PUBLIC_URL=http://localhost:8000
```

### Frontend API Requests

All fetch requests **must** include `credentials: 'include'`:

```javascript
fetch(url, {
    method: 'GET',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'include'  // Required to send/receive cookies
});
```

## Troubleshooting

### All requests return 401 after successful login

**Symptom**: Login returns 200, but every subsequent API call returns 401 Unauthenticated.

**Cause**: The `auth_token` cookie is not being sent by the browser.

**Debug steps**:

1. **Check browser DevTools > Application > Cookies**: Is the `auth_token` cookie present after login?
   - If **missing**: The browser rejected the `Set-Cookie` header. Check SameSite/Secure mismatch.
   - If **present**: The cookie exists but isn't being sent. Check origin mismatch.

2. **Check the request origin vs API domain**:
   - Open DevTools > Network tab, click on a failing request
   - Compare the page URL (e.g., `localhost:8080`) with the request URL (e.g., `127.0.0.1:8000`)
   - If they differ (`localhost` vs `127.0.0.1`), they are **different sites** and cookies won't be sent with `SameSite=lax`

3. **Check `credentials: 'include'`**: Missing this means the browser won't send cookies at all.

### Common Pitfalls

| Pitfall | Cause | Fix |
|---------|-------|-----|
| Frontend uses `127.0.0.1`, page is `localhost` | Different sites, cookie blocked | Use `localhost` consistently in `.env.development` |
| `.env` changed but still broken | Vite has `.env.development` which overrides `.env` | Edit `.env.development` too, then restart Vite |
| Production 401 after deploy | `SameSite=strict` blocks all cross-site cookies | Use `SameSite=none` + `Secure=true` (already fixed in `authCookieParams()`) |
| Logout doesn't clear cookie | `withoutCookie()` doesn't match original cookie params | Use `authCookieParams('', -1)` to expire with matching params |
| Cookie works in Postman but not browser | Postman ignores SameSite/CORS rules | This is expected; test in the actual browser |

### Vite `.env` File Priority

Vite loads environment files in this order (highest priority first):

```
.env.development.local   ← highest in dev mode
.env.development         ← used by "vite dev" (THIS IS THE ONE THAT MATTERS)
.env.local
.env                     ← lowest priority, easily overridden
```

Always check `.env.development` when debugging dev environment issues.
