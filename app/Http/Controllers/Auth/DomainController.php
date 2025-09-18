<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    /**
     * GET domains for authenticated user and their page permissions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Build domain query with conditional exclusion
        $domainQuery = Domain::select(['id', 'name', 'url', 'key'])->where('key', '!=', 'solucomp');

        // Exclude Samsung domain for external admin users
        if ($user->external_role === 'Admin') {
            $domainQuery->where('key', '!=', 'samsung2025_dm');
        }

        $domains = $domainQuery->get();

        // Optimize assigned domains query
        if ($user->isAdmin()) {
            $assigned = $domains->pluck('id')->all();
        } else {
            // Use direct pluck instead of loading relationships
            $assigned = $user->domains()->pluck('domains.id')->all();
        }

        // Initialize permissions early
        $permissions = [];
        $permissionsUrl = config('services.solucomp_page_permissions.permissions');
        $bearerToken = $request->bearerToken();

        // Early return if no permissions URL or token
        if (!$permissionsUrl || !$bearerToken) {
            if (!$permissionsUrl) {
                Log::warning('SOLUCOMP_USERPAGE_PERMISSION environment variable is not set. Cannot fetch page permissions.');
            }
            if (!$bearerToken) {
                Log::warning('Request is missing Bearer token. Cannot fetch page permissions.');
            }
        } else {
            // Fetch permissions with timeout and error handling
            try {
                $response = Http::withToken($bearerToken)
                    ->acceptJson()
                    ->timeout(5) // Add timeout to prevent hanging
                    ->get($permissionsUrl);

                if ($response->successful()) {
                    $permissions = $response->json('page_permissions', []);
                } else {
                    Log::warning('Failed to fetch page permissions for user from SoluComp.', [
                        'user_uuid' => $user->uuid,
                        'status' => $response->status(),
                        'url' => $permissionsUrl,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception when fetching page permissions from SoluComp.', [
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage(),
                    'url' => $permissionsUrl,
                ]);
            }
        }

        return response()->json([
            'domains'          => $domains,
            'assigned_domains' => $assigned,
            'page_permissions' => $permissions,
        ]);
    }
}
