<?php

namespace App\Http\Middleware;

use App\Models\AccessRequest;
use App\Models\Domain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AccessRequestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $action = 'create'): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        switch ($action) {
            case 'create':
                return $this->handleCreate($request, $user, $next);
            case 'view':
                return $this->handleView($request, $user, $next);
            case 'manage':
                return $this->handleManage($request, $user, $next);
            default:
                return $next($request);
        }
    }

    /**
     * Handle access request creation validation
     */
    private function handleCreate(Request $request, $user, Closure $next): Response
    {
        $domainId = $request->input('domain_id');

        if (!$domainId) {
            return response()->json(['message' => 'Domain ID is required'], 400);
        }

        // Check if domain exists
        $domain = Domain::find($domainId);
        if (!$domain) {
            return response()->json(['message' => 'Domain not found'], 404);
        }

        // Check if this is an activation request (from activation-requests endpoint)
        $isActivationRequest = $request->routeIs('activation-requests.store');

        if ($isActivationRequest) {
            // For activation requests, user MUST have access to the domain
            if (!$user->domains()->where('domain_id', $domainId)->exists()) {
                return response()->json(['message' => 'You do not have access to this domain. Please request access first.'], 409);
            }

            // Check if user already has a pending activation request for this domain
            $existingRequest = AccessRequest::where('user_uuid', $user->uuid)
                ->where('domain_id', $domainId)
                ->where('request_type', 'activation')
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'message' => 'You already have a pending activation request for this domain',
                    'request_id' => $existingRequest->id,
                    'created_at' => $existingRequest->created_at
                ], 409);
            }
        } else {
            // For access requests, user must NOT have access to the domain
            if ($user->domains()->where('domain_id', $domainId)->exists()) {
                return response()->json(['message' => 'You already have access to this domain'], 409);
            }

            // Check if user already has a pending access request for this domain
            $existingRequest = AccessRequest::where('user_uuid', $user->uuid)
                ->where('domain_id', $domainId)
                ->where('request_type', 'access')
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'message' => 'You already have a pending access request for this domain',
                    'request_id' => $existingRequest->id,
                    'created_at' => $existingRequest->created_at
                ], 409);
            }
        }

        // Check if user has reached the maximum number of pending requests (optional limit)
        $pendingCount = AccessRequest::where('user_uuid', $user->uuid)
            ->where('status', 'pending')
            ->count();

        if ($pendingCount >= 10) { // Configurable limit
            return response()->json(['message' => 'You have reached the maximum number of pending requests'], 429);
        }

        return $next($request);
    }

    /**
     * Handle access request viewing validation
     */
    private function handleView(Request $request, $user, Closure $next): Response
    {
        // For index requests, ensure user can only see their own requests unless admin
        if ($request->routeIs('access-requests.index')) {
            if (!($user->role === 'admin' || $user->external_role === 'admin')) {
                // Non-admin users can only see their own requests
                $request->merge(['user_uuid' => $user->uuid]);
            }
        }

        // For show requests, check if user can view the specific request
        if ($request->routeIs('access-requests.show')) {
            $accessRequestId = $request->route('accessRequest');
            $accessRequest = AccessRequest::findOrFail($accessRequestId);

            if (
                $accessRequest->user_uuid !== $user->uuid &&
                !($user->role === 'admin' || $user->external_role === 'admin')
            ) {
                return response()->json(['message' => 'Unauthorized to view this request'], 403);
            }
        }

        return $next($request);
    }

    /**
     * Handle access request management validation (approve/reject)
     */
    private function handleManage(Request $request, $user, Closure $next): Response
    {
        // Only admins can manage access requests
        if (!($user->role === 'admin' || $user->external_role === 'admin')) {
            return response()->json(['message' => 'Unauthorized to manage access requests'], 403);
        }

        $accessRequestId = $request->route('accessRequestId');
        $accessRequest = AccessRequest::findOrFail($accessRequestId);

        // Check if enable_user_activation parameter is passed
        $enableUserActivation = $request->input('enable_user_activation', false);

        // Check if request is still pending
        if ($accessRequest->status !== 'pending' && !$enableUserActivation) {
            return response()->json([
                'message' => 'This request has already been processed',
                'current_status' => $accessRequest->status
            ], 409);
        }

        return $next($request);
    }
}
