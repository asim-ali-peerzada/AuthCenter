<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\JwtService;
use App\Models\BlacklistedJwt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Logout;

class LogoutController extends Controller
{
    // POST logout
    public function logout(JwtService $jwt)
    {
        $token = request()->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Token missing'], 400);
        }

        try {

            $payload = $jwt->decode($token);
            $user = Auth::user();

            BlacklistedJwt::create([
                'jti'        => $payload->jti,
                'user_id'    => Auth::id(),
                'expires_at' => Carbon::createFromTimestamp($payload->exp),
            ]);

            // Fire the logout event manually
            event(new Logout('web', $user));

            return response()->json(['message' => 'Logged out']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid token'], 400);
        }
    }
}
