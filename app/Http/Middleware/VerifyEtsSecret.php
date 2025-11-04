<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class VerifyEtsSecret
{
    /**
     * Validate ETS secret passed via X-ETS-Secret header.
     */
    public function handle(Request $request, Closure $next)
    {
        $configuredSecret = config('services.ets.secret');

        if (Str::startsWith((string) $configuredSecret, 'base64:')) {
            $configuredSecret = base64_decode(substr((string) $configuredSecret, 7));
        }

        $requestSecret = $request->header('X-ETS-Secret');
        if (Str::startsWith((string) $requestSecret, 'base64:')) {
            $requestSecret = base64_decode(substr((string) $requestSecret, 7));
        }

        if (! $requestSecret || ! hash_equals((string) $configuredSecret, (string) $requestSecret)) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}