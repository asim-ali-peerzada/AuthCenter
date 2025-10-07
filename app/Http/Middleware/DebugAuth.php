<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DebugAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        Log::info('DebugAuth middleware check', [
            'user_authenticated' => $user ? true : false,
            'user_id' => $user ? $user->id : null,
            'user_uuid' => $user ? $user->uuid : null,
            'user_origin' => $user ? $user->user_origin : null,
            'external_role' => $user ? $user->external_role : null,
            'role' => $user ? $user->role : null,
            'is_approved' => $user ? $user->is_approved : null,
            'request_url' => $request->fullUrl(),
            'request_method' => $request->method(),
            'auth_guard' => Auth::getDefaultDriver(),
            'auth_check' => Auth::check()
        ]);

        return $next($request);
    }
}
