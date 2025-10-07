<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuth2Controller extends Controller
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    /**
     * OAuth2 Authorization endpoint
     * GET /authorize
     */
    public function authorize(Request $request)
    {
        try {
            // Validate required parameters
            $request->validate([
                'client_id' => 'required|string',
                'redirect_uri' => 'required|url',
                'response_type' => 'required|in:code',
                'scope' => 'required|string',
                'state' => 'required|string',
                'code_challenge' => 'required|string',
                'code_challenge_method' => 'required|in:S256',
            ]);

            $clientId = $request->input('client_id');
            $redirectUri = $request->input('redirect_uri');
            $responseType = $request->input('response_type');
            $scope = $request->input('scope');
            $state = $request->input('state');
            $codeChallenge = $request->input('code_challenge');
            $codeChallengeMethod = $request->input('code_challenge_method');

            // Find user by client_id (UUID)
            $user = User::where('uuid', $clientId)->first();

            if (!$user) {
                return $this->redirectWithError($redirectUri, 'invalid_client', 'Invalid client ID', $state);
            }

            // Check if user is approved and active
            if (!$user->is_approved || $user->status !== 'active') {
                return $this->redirectWithError($redirectUri, 'access_denied', 'User account not approved or inactive', $state);
            }

            // Check if user has admin role for admin scope
            if (str_contains($scope, 'admin:access')) {
                Log::info('Checking admin access for user:', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'external_role' => $user->external_role,
                ]);

                // Check external_role field for admin access
                $isAdmin = $user->external_role === 'Admin';

                Log::info('Admin check results:', [
                    'external_role_check' => $isAdmin,
                ]);

                if (!$isAdmin) {
                    return $this->redirectWithError($redirectUri, 'access_denied', 'Insufficient privileges for admin access', $state);
                }
            }

            // Generate authorization code
            $authCode = Str::random(32);

            // Store authorization code with metadata in cache (expires in 10 minutes)
            $cacheData = [
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => $scope,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'state' => $state,
                'created_at' => now(),
            ];

            $cacheKey = "oauth_code_{$authCode}";
            Cache::put($cacheKey, $cacheData, 600);

            Log::info('OAuth2 authorization code stored:', [
                'auth_code' => $authCode,
                'cache_key' => $cacheKey,
                'cache_data' => $cacheData,
                'expires_in_seconds' => 600
            ]);

            // Redirect back to client with authorization code
            $redirectParams = [
                'code' => $authCode,
                'state' => $state,
            ];

            // Pass through code_verifier and admin_client_id if they exist
            if ($request->has('code_verifier')) {
                $redirectParams['code_verifier'] = $request->input('code_verifier');
            }
            if ($request->has('admin_client_id')) {
                $redirectParams['admin_client_id'] = $request->input('admin_client_id');
            }

            $redirectUrl = $redirectUri . '?' . http_build_query($redirectParams);

            return redirect($redirectUrl);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('OAuth2 validation error: ' . $e->getMessage());
            return $this->redirectWithError(
                $request->input('redirect_uri', ''),
                'invalid_request',
                'Invalid request parameters',
                $request->input('state', '')
            );
        } catch (\Exception $e) {
            Log::error('OAuth2 authorization error: ' . $e->getMessage());
            return $this->redirectWithError(
                $request->input('redirect_uri', ''),
                'server_error',
                'Internal server error',
                $request->input('state', '')
            );
        }
    }

    /**
     * OAuth2 Token endpoint
     * POST /token
     */
    public function token(Request $request)
    {
        try {
            $request->validate([
                'grant_type' => 'required|in:authorization_code',
                'code' => 'required|string',
                'redirect_uri' => 'required|url',
                'client_id' => 'required|string',
                'code_verifier' => 'required|string',
            ]);

            $grantType = $request->input('grant_type');
            $code = $request->input('code');
            $redirectUri = $request->input('redirect_uri');
            $clientId = $request->input('client_id');
            $codeVerifier = $request->input('code_verifier');

            // Retrieve authorization code from cache
            $cacheKey = "oauth_code_{$code}";
            $authCodeData = Cache::get($cacheKey);

            if (!$authCodeData) {
                Log::error('Authorization code not found in cache:', [
                    'code' => $code,
                    'cache_key' => $cacheKey
                ]);
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'Authorization code not found or expired'
                ], 400);
            }

            // Verify client_id matches
            if ($authCodeData['client_id'] !== $clientId) {
                return response()->json([
                    'error' => 'invalid_client',
                    'error_description' => 'Client ID mismatch'
                ], 400);
            }

            // Verify redirect_uri matches
            if ($authCodeData['redirect_uri'] !== $redirectUri) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'Redirect URI mismatch'
                ], 400);
            }

            // Verify PKCE code challenge
            $expectedChallenge = hash('sha256', $codeVerifier, true);
            $expectedChallenge = rtrim(strtr(base64_encode($expectedChallenge), '+/', '-_'), '=');

            if ($authCodeData['code_challenge'] !== $expectedChallenge) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'Invalid code verifier'
                ], 400);
            }

            // Get user
            $user = User::find($authCodeData['user_id']);
            if (!$user) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'User not found'
                ], 400);
            }

            // Generate access token
            $accessToken = $this->jwtService->issue([
                'sub' => $user->uuid,
                'email' => $user->email,
                'scope' => $authCodeData['scope'],
                'client_id' => $clientId,
            ]);

            // Generate refresh token
            $refreshToken = $this->jwtService->issueRefreshToken($user->uuid);

            // Clean up authorization code
            Cache::forget("oauth_code_{$code}");

            return response()->json([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600, // 1 hour
                'refresh_token' => $refreshToken,
                'scope' => $authCodeData['scope'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('OAuth2 token validation error: ' . $e->getMessage());
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Invalid request parameters'
            ], 400);
        } catch (\Exception $e) {
            Log::error('OAuth2 token error: ' . $e->getMessage());
            return response()->json([
                'error' => 'server_error',
                'error_description' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * OAuth2 Token Validation endpoint
     * GET /oauth/validate
     */
    public function validate(Request $request)
    {
        try {
            $authHeader = $request->header('Authorization');

            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'error' => 'invalid_request',
                    'error_description' => 'Authorization header missing or invalid'
                ], 401);
            }

            $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

            // Validate the OAuth2 token using JWT service
            try {
                $decodedToken = $this->jwtService->decode($token);
                $payload = (array) $decodedToken;
            } catch (\Exception $e) {
                Log::error('OAuth2 token decode error: ' . $e->getMessage());
                return response()->json([
                    'error' => 'invalid_token',
                    'error_description' => 'Token is invalid or expired'
                ], 401);
            }

            // Get user from token payload
            $userUuid = $payload['sub'] ?? null;
            if (!$userUuid) {
                return response()->json([
                    'error' => 'invalid_token',
                    'error_description' => 'Token does not contain user information'
                ], 401);
            }

            // Find user by UUID
            $user = User::where('uuid', $userUuid)->first();
            if (!$user) {
                return response()->json([
                    'error' => 'invalid_token',
                    'error_description' => 'User not found'
                ], 401);
            }

            // Check if user is active and approved
            if (!$user->is_approved || $user->status !== 'active') {
                return response()->json([
                    'error' => 'access_denied',
                    'error_description' => 'User account not approved or inactive'
                ], 403);
            }

            // Return user information
            return response()->json([
                'user' => [
                    'uuid' => $user->uuid,
                    'email' => $user->email,
                    'full_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'status' => $user->status,
                    'image_full_url' => $user->image_url
                        ? asset('storage/' . $user->image_url)
                        : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth2 token validation error: ' . $e->getMessage());
            return response()->json([
                'error' => 'server_error',
                'error_description' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Redirect with error parameters
     */
    private function redirectWithError(string $redirectUri, string $error, string $errorDescription, string $state = null): \Illuminate\Http\RedirectResponse
    {
        $params = [
            'error' => $error,
            'error_description' => $errorDescription,
        ];

        if ($state) {
            $params['state'] = $state;
        }

        $redirectUrl = $redirectUri . '?' . http_build_query($params);
        return redirect($redirectUrl);
    }
}
