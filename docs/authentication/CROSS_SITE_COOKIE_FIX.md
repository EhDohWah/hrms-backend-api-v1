# Fix: Cross-Site Cookie 401 Unauthorized Error

**Date**: 2026-02-12
**Commit**: `fcf7a5a` on `main`
**Affects**: Both development and production environments

## Problem

After a successful login (HTTP 200), **every subsequent API request returned 401 Unauthenticated**. The login worked, but the authentication cookie was silently dropped by the browser.

### Error Logs

```
GET http://127.0.0.1:8000/api/v1/admin/modules 401 (Unauthorized)
GET http://127.0.0.1:8000/api/v1/me/permissions 401 (Unauthorized)
GET http://127.0.0.1:8000/api/v1/notifications 401 (Unauthorized)
POST http://127.0.0.1:8000/api/v1/refresh-token 401 (Unauthorized)
```

## Root Cause

The `auth_token` HttpOnly cookie was set with `SameSite` values that **blocked cross-site delivery**.

### How Browsers Handle SameSite Cookies

When a page at `Origin A` makes a fetch request to `Origin B`, the browser checks whether A and B are the **same site**. If not, `SameSite=lax` and `SameSite=strict` cookies are **not sent** on AJAX/fetch requests.

### Development Issue

The frontend ran on `http://localhost:8080` but API requests were sent to `http://127.0.0.1:8000`.

Even though `localhost` and `127.0.0.1` resolve to the same machine, **browsers treat them as different sites**. The `auth_token` cookie (set with `SameSite=lax` by `127.0.0.1`) was not sent on requests originating from `localhost`.

```
Page origin:    http://localhost:8080      ← site = "localhost"
API origin:     http://127.0.0.1:8000     ← site = "127.0.0.1"
SameSite=lax:   Different sites → cookie NOT sent on fetch → 401
```

**Why did `.env` changes not work at first?**

Vite uses `.env.development` in dev mode, which **overrides** `.env`. Only `.env` was updated initially, but `.env.development` still had `127.0.0.1`.

### Production Issue

The frontend is hosted on Netlify (`https://hrmsfe.netlify.app`) and the backend is on a separate API domain. The original code set:

```php
$sameSite = $secure ? 'strict' : 'lax';  // Production = 'strict'
```

`SameSite=strict` **never** sends cookies on cross-site requests, not even top-level navigations. This guaranteed 401 errors in production.

## Changes Made

### 1. Backend - `AuthController.php`

**Before** (broken): Cookie params were duplicated in `login()`, `refreshToken()`, and `logout()` with incorrect SameSite logic.

```php
// OLD - in login() and refreshToken()
$secure = app()->environment('production');
$sameSite = $secure ? 'strict' : 'lax';  // strict in production = always blocked

// OLD - in logout()
->withoutCookie('auth_token');  // didn't match original cookie params
```

**After** (fixed): Centralized in `authCookieParams()` helper with correct SameSite logic.

```php
// NEW - shared helper used by login(), refreshToken(), and logout()
protected function authCookieParams(string $token, int $minutes): array
{
    $isProduction = app()->environment('production');
    $secure = $isProduction;
    $sameSite = $isProduction ? 'none' : 'lax';
    //          ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    //          'none' allows cross-site cookies (required for different domains)
    //          'lax' is sufficient when frontend & backend share the same site

    return [
        'auth_token', $token, $minutes, '/', null,
        $secure, true, false, $sameSite,
    ];
}
```

| Method | Before | After |
|--------|--------|-------|
| `login()` | Inline cookie params, `SameSite=strict` in prod | `authCookieParams($token, $minutes)` |
| `refreshToken()` | Inline cookie params, `SameSite=strict` in prod | `authCookieParams($token, $minutes)` |
| `logout()` | `withoutCookie('auth_token')` (params didn't match) | `authCookieParams('', -1)` (matching params, expired) |

### 2. Frontend - `.env.development`

Changed all `127.0.0.1` references to `localhost`:

```env
# Before
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_PUBLIC_URL=http://127.0.0.1:8000
VITE_REVERB_HOST=127.0.0.1
VITE_BROADCASTING_AUTH_ENDPOINT=http://127.0.0.1:8000/broadcasting/auth

# After
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_PUBLIC_URL=http://localhost:8000
VITE_REVERB_HOST=localhost
VITE_BROADCASTING_AUTH_ENDPOINT=http://localhost:8000/broadcasting/auth
```

### 3. Frontend - `.env`

Same `127.0.0.1` to `localhost` change (fallback file).

## Verification

### Development

1. Restart Vite dev server (required for `.env` changes)
2. Clear browser cookies for both `localhost` and `127.0.0.1`
3. Login → subsequent requests should now succeed
4. Check DevTools > Network: requests should go to `localhost:8000`, not `127.0.0.1:8000`

### Production

1. Deploy the `AuthController.php` change
2. Ensure `.env.production` has correct `VITE_API_BASE_URL` pointing to your API domain
3. Login → verify cookies are set with `SameSite=none; Secure` in DevTools > Application > Cookies

## Lessons Learned

1. **`localhost` and `127.0.0.1` are different sites** in browser cookie policy
2. **`SameSite=strict` breaks cross-origin SPA authentication** entirely
3. **`SameSite=none` + `Secure=true`** is required when frontend and backend are on different domains
4. **Vite `.env.development` overrides `.env`** — always check both files
5. **Centralize cookie parameters** in a helper to avoid inconsistencies between login/refresh/logout
