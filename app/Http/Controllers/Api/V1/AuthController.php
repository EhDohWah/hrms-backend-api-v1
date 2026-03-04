<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Info(title: 'HRMS API Documentation', version: '1.0.0', description: 'HRMS Backend API documentation')]
#[OA\Server(url: 'http://localhost:8000/api/v1')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer')]
class AuthController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

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
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->attemptLogin(
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error_type' => $result['error_type'],
            ], $result['status']);
        }

        $response = response()->json([
            'success' => true,
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
            'user' => $result['user'],
        ]);

        return $response->cookie(...$this->authCookieParams(
            $result['token'],
            (int) ceil($result['expires_in'] / 60)
        ));
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
        $this->authService->logout($request->user(), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ])->cookie(...$this->authCookieParams('', -1));
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
        $result = $this->authService->refreshToken($request->user());

        $response = response()->json([
            'success' => true,
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
        ]);

        return $response->cookie(...$this->authCookieParams(
            $result['token'],
            (int) ceil($result['expires_in'] / 60)
        ));
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
        $result = $this->authService->sendPasswordResetLink(
            $request->validated()['email'],
            $request->ip()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status']);
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
        $validated = $request->validated();

        $result = $this->authService->resetPassword(
            $validated['email'],
            $validated['token'],
            $validated['password'],
            $request->ip()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status']);
    }

    /**
     * Build the auth_token cookie parameters.
     *
     * When the frontend and backend are on different domains (cross-site),
     * the cookie MUST use SameSite=none + Secure=true, otherwise the browser
     * will refuse to send it on cross-origin fetch requests.
     *
     * @param  string  $token  The plain-text Sanctum token (or '' to clear)
     * @param  int  $minutes  Lifetime in minutes (negative to expire/clear)
     * @return array Positional args for response()->cookie(...)
     */
    protected function authCookieParams(string $token, int $minutes): array
    {
        $isProduction = app()->environment('production');
        $secure = $isProduction;
        $sameSite = $isProduction ? 'none' : 'lax';

        return [
            'auth_token',   // name
            $token,         // value
            $minutes,       // minutes
            '/',            // path
            null,           // domain (current domain)
            $secure,        // secure (HTTPS only in production)
            true,           // httpOnly
            false,          // raw
            $sameSite,      // sameSite
        ];
    }
}
