<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Info(title: 'HRMS API Documentation', version: '1.0.0', description: 'HRMS Backend API documentation')]
#[OA\Server(url: 'http://localhost:8000/api/v1')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer')]
class AuthController extends Controller
{
    /**
     * Handle user login and return an API token.
     */
    #[OA\Post(
        path: '/login',
        summary: 'User login',
        description: 'Authenticates user and returns access token',
        operationId: 'login',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful login',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1...'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_in', type: 'integer', example: 21600),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'No account found with this email address.'),
                new OA\Property(property: 'error_type', type: 'string', example: 'EMAIL_NOT_FOUND'),
            ]
        )
    )]
    #[OA\Response(
        response: 429,
        description: 'Too many login attempts',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Too many login attempts. Please try again in 60 seconds.'),
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Something went wrong'),
                new OA\Property(property: 'error', type: 'string', example: 'Server error message'),
            ]
        )
    )]
    public function login(Request $request)
    {
        // Validate the request
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check for too many login attempts
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $seconds = $this->limiter()->availableIn($this->throttleKey($request));

            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                'error_type' => 'RATE_LIMIT_ERROR',
            ], 429);
        }

        // First check if user exists
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            $this->incrementLoginAttempts($request);
            Log::warning('Login attempt with non-existent email', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.',
                'error_type' => 'EMAIL_NOT_FOUND',
            ], 401);
        }

        // Check if user account is active
        if ($user->status !== 'active') {
            $this->incrementLoginAttempts($request);
            Log::warning('Login attempt on inactive account', [
                'email' => $request->email,
                'user_id' => $user->id,
                'status' => $user->status,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact the administrator.',
                'error_type' => 'ACCOUNT_INACTIVE',
            ], 401);
        }

        // Attempt login with password verification
        if (! Auth::attempt($credentials)) {
            $this->incrementLoginAttempts($request);
            Log::warning('Failed login attempt - invalid password', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The password you entered is incorrect.',
                'error_type' => 'INVALID_PASSWORD',
            ], 401);
        }

        // Clear login attempts on success
        $this->clearLoginAttempts($request);

        // also get permission and role of the user
        $user = Auth::user();

        // Update last login timestamp
        $user->last_login_at = Carbon::now();
        $user->last_login_ip = $request->ip();
        $user->save();

        $user->load('permissions', 'roles');

        // Create an API token
        $token = $user->createToken($user->name.'-api-token')->plainTextToken;

        // Set token expiration to 6 hours
        $expiresIn = 21600; // 6 hours in seconds

        Log::info('User logged in', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'user' => $user,
        ]);
    }

    /**
     * Logout the user by revoking tokens.
     */
    #[OA\Post(
        path: '/logout',
        summary: 'User logout',
        description: 'Revokes the current access token',
        operationId: 'logout',
        security: [['bearerAuth' => []]],
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Successfully logged out',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
            ]
        )
    )]
    public function logout(Request $request): JsonResponse
    {
        Log::info('User logged out', [
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Refresh the user's token.
     */
    #[OA\Post(
        path: '/refresh-token',
        summary: 'Refresh authentication token',
        description: 'Generates a new token for the authenticated user',
        operationId: 'refreshToken',
        security: [['bearerAuth' => []]],
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Token refreshed successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1...'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_in', type: 'integer', example: 21600),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
            ]
        )
    )]
    public function refreshToken(Request $request): JsonResponse
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        // Create a new token with expiration set to 6 hours
        $expiresIn = 21600; // 6 hours in seconds
        $token = $request->user()->createToken($request->user()->name.'-api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
        ]);
    }

    // Rate limiting helper methods...

    protected function hasTooManyLoginAttempts(Request $request)
    {
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey($request),
            $this->maxAttempts()
        );
    }

    protected function incrementLoginAttempts(Request $request)
    {
        $this->limiter()->hit(
            $this->throttleKey($request),
            $this->decayMinutes() * 60
        );
    }

    protected function clearLoginAttempts(Request $request)
    {
        $this->limiter()->clear($this->throttleKey($request));
    }

    protected function fireLockoutEvent(Request $request)
    {
        Log::warning('User account locked due to too many login attempts', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    protected function limiter()
    {
        return app(\Illuminate\Cache\RateLimiter::class);
    }

    protected function maxAttempts()
    {
        return 5;
    }

    protected function decayMinutes()
    {
        return 1;
    }

    protected function throttleKey(Request $request)
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }

    /**
     * Send password reset link to email.
     */
    #[OA\Post(
        path: '/forgot-password',
        summary: 'Request password reset',
        description: 'Sends a password reset link to the user email',
        operationId: 'forgotPassword',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Reset link sent successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'We have emailed your password reset link!'),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Your account is not active. Please contact the administrator.'),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'The email field is required.'),
                new OA\Property(property: 'errors', type: 'object'),
            ]
        )
    )]
    #[OA\Response(
        response: 429,
        description: 'Too many requests',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Too many password reset attempts. Please try again in 3600 seconds.'),
            ]
        )
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Rate limiting: max 3 attempts per email per hour
        $throttleKey = 'forgot-password:'.Str::lower($request->email);

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "Too many password reset attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        // Check if user account is active
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact the administrator.',
            ], 400);
        }

        // Generate random 64-character token
        $token = Str::random(64);

        // Store hashed token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );

        // Send notification
        $user->notify(new ResetPasswordNotification($token, $request->email));

        RateLimiter::hit($throttleKey, 3600); // 1 hour decay

        Log::info('Password reset requested', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'We have emailed your password reset link!',
        ]);
    }

    /**
     * Reset password with token.
     */
    #[OA\Post(
        path: '/reset-password',
        summary: 'Reset password',
        description: 'Resets user password with valid token',
        operationId: 'resetPassword',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'abc123def456...'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewPassword123!'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NewPassword123!'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Password reset successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Your password has been reset successfully!'),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid or expired token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired reset token.'),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                new OA\Property(property: 'errors', type: 'object'),
            ]
        )
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        // Get the password reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        // Check if token is expired (60 minutes)
        $tokenAge = Carbon::parse($resetRecord->created_at)->diffInMinutes(Carbon::now());
        if ($tokenAge > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        // Verify token matches
        if (! Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token.',
            ], 400);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete all password reset tokens for this email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revoke all existing tokens for security
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

        Log::info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset successfully!',
        ]);
    }
}
