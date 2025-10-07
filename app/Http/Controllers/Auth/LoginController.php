<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\JwtService;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Domain;
use App\Models\SystemSetting;
use App\Models\AccessRequest;
use Illuminate\Http\Request;
use App\Services\TwoFactorAuthService;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Services\AuthAttemptService;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function login(
        LoginRequest       $request,
        JwtService         $jwt,
        AuthAttemptService $attempts
    ) {

        $captcha = $this->verifyRecaptcha($request->recaptcha);

        /** @var User|null $user */
        $user = User::where('email', $request->email)->first();

        /* check lock */
        if ($user && $attempts->isLocked($user)) {
            return response()->json(['message' => 'Account locked. Please contact an administrator.'], 423);
        }

        /* try credentials */
        if (! Auth::attempt($request->only('email', 'password'))) {
            if ($user) {
                $attempts->recordFailure($user);
            }
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $attempts->clearFailures($user);

        // Check if the user account has been approved.
        if (! $user->is_approved) {
            return response()->json([
                'message' => 'Your account is in pending for approval. Please wait for confirmation or contact support for assistance.',
                'error' => 'account_not_approved'
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account inactive'], 403);
        }

        if (! $user->is_2fa_verified) {
            return response()->json([
                'message' => 'Please configure Two-Factor Authentication using Google Authenticator.',
                'error'   => '2fa_not_configured',
                'action'  => 'trigger_2fa_setup_modal',
                'uuid'    => $user->uuid,
            ], 403);
        }

        if ($request->input('admin_panel')) {
            $isAdmin = method_exists($user, 'hasRole')
                ? $user->hasRole('admin')
                : ($user->role === 'admin');

            if (!$isAdmin) {
                if (!$user->isAdmin()) {
                    return response()->json([
                        'message' => 'Access Denied: Insufficient privileges to access the administrative panel.',
                        'error' => 'unauthorized_access'
                    ], 403);
                }
            }
        }

        /* issue JWT */
        $token = $jwt->issue(['sub' => $user->uuid, 'email' => $user->email]);

        $refreshToken = $jwt->issueRefreshToken($user->uuid);

        // Load user domains to include in the response
        $user->load('domains:id');

        // Get enforce_2fa_login setting from system_settings
        $enforce2FA = SystemSetting::get('enforce_2fa_login', 'false') === 'true';
        $unapprovedUserCount = User::where('is_approved', false)->count();
        $unapprovedRequestCount = AccessRequest::where('status', 'pending')->count();

        $response = [
            'uuid' => $user->uuid,
            'token' => $token,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->external_role,
            'email' => $user->email,
            'refresh_token' => $refreshToken,
            'enforce_2fa_login' => $enforce2FA,
        ];

        if ($request->input('admin_panel')) {
            $response['user'] = [
                'uuid' => $user->uuid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'image_full_url' => $user->image_url
                    ? asset('storage/' . $user->image_url)
                    : null,
                'total_users'    => User::count(),
                'total_domains'  => Domain::count(),
                'un_approved_user_count' => $unapprovedUserCount,
                'un_approved_request_count' => $unapprovedRequestCount,
            ];
        }

        return response()->json($response);
    }

    /**
     * Generate a new 2FA secret and QR code for an existing user.
     *
     * @param Request $request
     * @param TwoFactorAuthService $twoFactorService
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate2FASecret(Request $request, TwoFactorAuthService $twoFactorService)
    {
        $request->validate([
            'uuid' => 'required|uuid|exists:users,uuid',
        ]);

        $user = User::where('uuid', $request->uuid)->firstOrFail();

        $secret = $twoFactorService->generateSecretKey();

        // Store the secret and mark 2FA as enabled but not yet verified.
        $user->update([
            'google2fa_secret' => $secret,
            'is_2fa_enabled' => true,
            'is_2fa_verified' => false,
        ]);

        $qrCodeSvg = $twoFactorService->getQRCodeSvg(
            config('app.name', 'AuthCenter'),
            $user->email,
            $secret
        );

        return response()->json([
            'qr_code_svg' => $qrCodeSvg,
            'message' => 'Scan this QR code with your authenticator app, then verify the code to complete setup.'
        ]);
    }


    /**
     * Verify 2FA code during login process
     */
    public function verify2FA(Request $request, TwoFactorAuthService $twoFactorService, JwtService $jwt)
    {
        $request->validate([
            'uuid' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('uuid', $request->uuid)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if (!$user->google2fa_secret) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled for this user'
            ], 400);
        }

        $isValid = $twoFactorService->verifyCode($user->google2fa_secret, $request->code);

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid 2FA code'
            ], 422);
        }

        // If this is the first time they are verifying, mark it as such.
        if (! $user->is_2fa_verified) {
            $user->update(['is_2fa_verified' => true]);
        }

        // Issue fresh JWT tokens after successful 2FA verification
        $token = $jwt->issue(['sub' => $user->uuid, 'email' => $user->email]);
        $refreshToken = $jwt->issueRefreshToken($user->uuid);

        // Load user domains
        $user->load('domains:id');

        return response()->json([
            'success' => true,
            'message' => '2FA verification successful',
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->external_role,
            'token' => $token,
            'refresh_token' => $refreshToken,
            'domains' => $user->domains->pluck('id')->toArray(),
        ]);
    }


    /**
     * Validate user token and return user information
     */
    public function validate(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'uuid'   => $user->uuid,
                'email'  => $user->email,
                'full_name'   => $user->first_name,
                'last_name'   => $user->last_name,
                'status' => $user->status,
                'image_full_url' => $user->image_url
                    ? asset('storage/app/public/' . $user->image_url)
                    : null,
            ]
        ]);
    }

    /**
     * Verify the Google reCAPTCHA token.
     */
    private function verifyRecaptcha(string $token): array
    {
        $secretKey = config('services.recaptcha.secret');

        if (! $token) {
            return ['success' => false, 'message' => 'Token missing'];
        }

        $response = Http::asForm()->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret'   => $secretKey,
                'response' => $token,
            ]
        );

        return $response->successful() ? $response->json() : ['success' => false];
    }
}
