<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\JwtService;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Domain;
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
            return response()->json([
                'message' => 'Account locked. Try again at ' . (Carbon::parse($user->locked_until)->format('Y-m-d H:i:s')),
            ], 423);
        }

        /* try credentials */
        if (! Auth::attempt($request->only('email', 'password'))) {
            if ($user) {
                $attempts->recordFailure($user);
            }
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $attempts->clearFailures($user);

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account inactive'], 403);
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

        $response = [
            'uuid' => $user->uuid,
            'token' => $token,
            'refresh_token' => $refreshToken,
            'domains' => $user->domains->pluck('id')->toArray(),

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
            ];
        }

        return response()->json($response);
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
