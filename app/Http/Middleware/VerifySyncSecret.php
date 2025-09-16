<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class VerifySyncSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $configuredSecret = config('services.sync.secret');

        if (Str::startsWith($configuredSecret, 'base64:')) {
            $configuredSecret = base64_decode(substr($configuredSecret, 7));
        }

        $requestSecretRaw = $request->header('X-Sync-Secret');
        $requestSecret = $requestSecretRaw;

        // Decode the incoming secret
        if ($requestSecret && Str::startsWith($requestSecret, 'base64:')) {
            $requestSecret = base64_decode(substr($requestSecret, 7));
        }

        if (!$requestSecret || !hash_equals((string) $configuredSecret, (string) $requestSecret)) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        
        return $next($request);
    }
}