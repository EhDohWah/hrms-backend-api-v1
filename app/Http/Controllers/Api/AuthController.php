<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="HRMS API Documentation",
 *     description="HRMS Backend API documentation with OpenAPI/Swagger",
 *     @OA\Contact(
 *         email="admin@example.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication"
 * )
 */
class AuthController extends Controller
{
    /**
     * Handle user login and return an API token.
     *
     * @OA\Post(
     *     path="/login",
     *     summary="User login",
     *     description="Authenticates user and returns access token",
     *     operationId="login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *                 @OA\Property(
     *                     property="roles",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many login attempts",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Too many login attempts. Please try again in 60 seconds.")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        // Validate the request
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Check for too many login attempts
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $seconds = $this->limiter()->availableIn($this->throttleKey($request));
            return response()->json([
                'message' => "Too many login attempts. Please try again in {$seconds} seconds."
            ], 429);
        }

        // Attempt login
        if (!Auth::attempt($credentials)) {
            $this->incrementLoginAttempts($request);
            Log::warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return response()->json(['message' => 'Invalid credentials'], 401);
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
        $token = $user->createToken($user->name . '-api-token')->plainTextToken;

        // Set token expiration to 6 hours
        $expiresIn = 21600; // 6 hours in seconds

        Log::info('User logged in', [
            'user_id' => $user->id,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $expiresIn,
            'user'         => $user
        ]);
    }

    /**
     * Logout the user by revoking tokens.
     *
     * @OA\Post(
     *     path="/logout",
     *     summary="User logout",
     *     description="Revokes the current access token",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        Log::info('User logged out', [
            'user_id' => $request->user()->id,
            'ip' => $request->ip()
        ]);

        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }



    /**
     * Refresh the user's token.
     *
     * @OA\Post(
     *     path="/refresh-token",
     *     summary="Refresh authentication token",
     *     description="Generates a new token for the authenticated user",
     *     operationId="refreshToken",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function refreshToken(Request $request)
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        // Create a new token with expiration set to 6 hours
        $expiresIn = 21600; // 6 hours in seconds
        $token = $request->user()->createToken($request->user()->name . '-api-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $expiresIn
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
            'user_agent' => $request->userAgent()
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
        return Str::lower($request->input('email')) . '|' . $request->ip();
    }
}