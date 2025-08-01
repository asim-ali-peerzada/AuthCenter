<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JwtService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RefreshTokenController extends Controller
{
    // Token Refresh
    public function refresh(Request $request, JwtService $jwt): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
            'uuid' => 'required|uuid',
        ]);

        $valid = $jwt->validateRefreshToken($request->uuid, $request->refresh_token);

        if (! $valid) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        $user = User::where('uuid', $request->uuid)->firstOrFail();

        $token = $jwt->issue(['sub' => $user->uuid, 'email' => $user->email]);

        return response()->json([
            'token' => $token,
        ]);
    }
}
