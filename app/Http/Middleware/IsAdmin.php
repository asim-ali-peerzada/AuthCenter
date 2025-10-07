<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware to check if the authenticated user is an admin.
 */

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Check if user is admin through role OR through site_access_info origin with Admin external_role
        $isAdmin = $user->role === 'admin' ||
            ($user->user_origin === 'site_access_info' && $user->external_role === 'Admin');

        if (! $isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
