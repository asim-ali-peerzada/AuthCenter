<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\BlacklistedJwt;
use Illuminate\Support\Facades\Log;

class CheckJwtBlacklist
{
    public function __construct(private JwtService $jwtService) {}
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token missing'], 401);
        }

        try {

            $payload = $this->jwtService->decode($token);

            if (BlacklistedJwt::where('jti', $payload->jti)->exists()) {
                Log::warning('Token found in blacklist', ['jti' => $payload->jti]);
                return response()->json(['message' => 'Token blacklisted'], 401);
            }
        } catch (\Throwable $e) {
            Log::error('Token validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Invalid token: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
