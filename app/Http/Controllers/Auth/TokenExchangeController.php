<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Http\Requests\Auth\ExchangeRequest;
use App\Models\BlacklistedJwt;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use App\Services\LogUserActivityService;
use Illuminate\Support\Facades\Log;

class TokenExchangeController extends Controller
{
    // POST token/exchange
    public function tokenExchange(ExchangeRequest $request, JwtService $jwt, LogUserActivityService $logger): JsonResponse
    {
        try {

            $payload = $jwt->decode($request->input('token'));

            if ($payload->aud !== config('app.name')) {
                return response()->json(['message' => 'Invalid audience'], 403);
            }

            if ($payload->iss !== config('services.jwt.issuer')) {
                return response()->json(['message' => 'Invalid issuer'], 403);
            }

            if ($payload->exp < time()) {
                return response()->json(['message' => 'Token expired'], 401);
            }

            // Check if blacklisted
            if (BlacklistedJwt::where('jti', $payload->jti)->exists()) {
                return response()->json(['message' => 'Token is blacklisted'], 401);
            }

            // Find user by UUID
            $user = User::where('uuid', $payload->sub)->firstOrFail();

            // Check if the user account has been approved.
            if (! $user->is_approved) {
                return response()->json([
                    'message' => 'Your account is in pending for approval. Please wait for confirmation or contact support for assistance.',
                    'error' => 'account_not_approved'
                ], 403);
            }

            // Get the domain key from the request.
            $domainKey = $request->input('key');
            if (!$domainKey) {
                return response()->json(['message' => 'Domain key is required.'], 422);
            }

            // Find the domain by its key.
            $domain = Domain::where('key', $domainKey)->first();
            if (!$domain) {
                return response()->json(['message' => 'Invalid domain key.'], 404);
            }

            // If user is not admin, check domain access.
            $isAdmin = method_exists($user, 'hasRole')
                ? $user->hasRole('admin')
                : (($user->role ?? null) === 'admin');

            if (!$isAdmin && !$user->domains()->where('domains.id', $domain->id)->exists()) {
                return response()->json(['message' => 'Forbidden for this domain.'], 403);
            }

            // Log the activity for the specific domain.
            $logger->log($user, 'login', $domain->id);

            return response()->json([
                'user' => [
                    'uuid'    => $user->uuid,
                    'first_name'  => $user->first_name,
                    'last_name'  => $user->last_name,
                    'email' => $user->email,
                    'roles' => $user->external_role,
                    'password' => $user->password,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Token exchange failed: ' . $e->getMessage());
            return response()->json(['message' => 'Invalid token'], 400);
        }
    }
}
