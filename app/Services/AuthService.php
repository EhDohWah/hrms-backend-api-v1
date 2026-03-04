<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthService
{
    private const TOKEN_EXPIRY_SECONDS = 21600; // 6 hours

    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_DECAY_MINUTES = 1;

    private const MAX_FORGOT_PASSWORD_ATTEMPTS = 3;

    private const FORGOT_PASSWORD_DECAY_SECONDS = 3600; // 1 hour

    private const RESET_TOKEN_EXPIRY_MINUTES = 60;

    /**
     * Attempt to authenticate the user with the given credentials.
     *
     * Returns a success array with user, token, and expiry on success.
     * Returns an error array with message, error_type, and status on failure.
     */
    public function attemptLogin(array $credentials, string $ip, string $userAgent): array
    {
        $email = $credentials['email'];
        $throttleKey = $this->loginThrottleKey($email, $ip);

        // Check rate limit
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $this->logLockout($email, $ip, $userAgent);
            $seconds = RateLimiter::availableIn($throttleKey);

            return [
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                'error_type' => 'RATE_LIMIT_ERROR',
                'status' => 429,
            ];
        }

        // Check if user exists
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->incrementLoginAttempts($throttleKey);
            Log::warning('Login attempt with non-existent email', [
                'email' => $email,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            return [
                'success' => false,
                'message' => 'No account found with this email address.',
                'error_type' => 'EMAIL_NOT_FOUND',
                'status' => 401,
            ];
        }

        // Check if account is active
        if ($user->status !== 'active') {
            $this->incrementLoginAttempts($throttleKey);
            Log::warning('Login attempt on inactive account', [
                'email' => $email,
                'user_id' => $user->id,
                'status' => $user->status,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            return [
                'success' => false,
                'message' => 'Your account is not active. Please contact the administrator.',
                'error_type' => 'ACCOUNT_INACTIVE',
                'status' => 401,
            ];
        }

        // Attempt authentication
        if (! Auth::attempt($credentials)) {
            $this->incrementLoginAttempts($throttleKey);
            Log::warning('Failed login attempt - invalid password', [
                'email' => $email,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            return [
                'success' => false,
                'message' => 'The password you entered is incorrect.',
                'error_type' => 'INVALID_PASSWORD',
                'status' => 401,
            ];
        }

        // Success — clear rate limit and record login
        RateLimiter::clear($throttleKey);

        $user = Auth::user();
        $user->last_login_at = Carbon::now();
        $user->last_login_ip = $ip;
        $user->save();

        $user->load('permissions', 'roles');

        $token = $user->createToken($user->name.'-api-token')->plainTextToken;

        Log::info('User logged in', [
            'user_id' => $user->id,
            'ip' => $ip,
        ]);

        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
            'expires_in' => self::TOKEN_EXPIRY_SECONDS,
        ];
    }

    /**
     * Revoke the user's current token and log the action.
     */
    public function logout(User $user, string $ip): void
    {
        Log::info('User logged out', [
            'user_id' => $user->id,
            'ip' => $ip,
        ]);

        $user->currentAccessToken()->delete();
    }

    /**
     * Refresh the user's token: revoke current, create new.
     *
     * @return array{token: string, expires_in: int}
     */
    public function refreshToken(User $user): array
    {
        $user->currentAccessToken()->delete();

        $token = $user->createToken($user->name.'-api-token')->plainTextToken;

        return [
            'token' => $token,
            'expires_in' => self::TOKEN_EXPIRY_SECONDS,
        ];
    }

    /**
     * Send a password reset link to the given email.
     *
     * Returns a result array with success, message, and status.
     */
    public function sendPasswordResetLink(string $email, string $ip): array
    {
        $throttleKey = 'forgot-password:'.Str::lower($email);

        // Rate limiting: max 3 attempts per email per hour
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FORGOT_PASSWORD_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return [
                'success' => false,
                'message' => "Too many password reset attempts. Please try again in {$seconds} seconds.",
                'status' => 429,
            ];
        }

        $user = User::where('email', $email)->first();

        // Check if account is active
        if ($user->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Your account is not active. Please contact the administrator.',
                'status' => 400,
            ];
        }

        // Generate and store hashed reset token
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );

        // Send notification and record rate limit hit
        $user->notify(new ResetPasswordNotification($token, $email));

        RateLimiter::hit($throttleKey, self::FORGOT_PASSWORD_DECAY_SECONDS);

        Log::info('Password reset requested', [
            'email' => $email,
            'ip' => $ip,
        ]);

        return [
            'success' => true,
            'message' => 'We have emailed your password reset link!',
            'status' => 200,
        ];
    }

    /**
     * Reset user password with the provided token.
     *
     * Returns a result array with success, message, and status.
     */
    public function resetPassword(string $email, string $token, string $password, string $ip): array
    {
        // Find the reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $resetRecord) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token.',
                'status' => 400,
            ];
        }

        // Check if token is expired
        $tokenAge = Carbon::parse($resetRecord->created_at)->diffInMinutes(Carbon::now());

        if ($tokenAge > self::RESET_TOKEN_EXPIRY_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return [
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.',
                'status' => 400,
            ];
        }

        // Verify token matches
        if (! Hash::check($token, $resetRecord->token)) {
            return [
                'success' => false,
                'message' => 'Invalid reset token.',
                'status' => 400,
            ];
        }

        // Update password
        $user = User::where('email', $email)->first();
        $user->password = Hash::make($password);
        $user->save();

        // Cleanup: delete reset tokens and revoke all sessions
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

        Log::info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $ip,
        ]);

        return [
            'success' => true,
            'message' => 'Your password has been reset successfully!',
            'status' => 200,
        ];
    }

    /**
     * Build the login throttle key from email and IP.
     */
    private function loginThrottleKey(string $email, string $ip): string
    {
        return Str::lower($email).'|'.$ip;
    }

    /**
     * Increment login attempts for the throttle key.
     */
    private function incrementLoginAttempts(string $throttleKey): void
    {
        RateLimiter::hit($throttleKey, self::LOGIN_DECAY_MINUTES * 60);
    }

    /**
     * Log a lockout event for the given email.
     */
    private function logLockout(string $email, string $ip, string $userAgent): void
    {
        Log::warning('User account locked due to too many login attempts', [
            'email' => $email,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
