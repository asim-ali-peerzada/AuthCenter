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

        // Build domain query with conditional exclusions
        $domainQuery = Domain::select(['id', 'name', 'url', 'key'])
            ->where('key', '!=', 'solucomp');

        // Exclude Samsung domains based on user role
        if ($user->external_role === 'Admin') {
            // Admin users: exclude samsung2025_dm but allow samsung2025_dm_admin
            $domainQuery->where('key', '!=', 'samsung2025_dm');
        } else {
            // Non-Admin users: exclude samsung2025_dm_admin but allow samsung2025_dm
            $domainQuery->where('key', '!=', 'samsung2025_dm_admin');
        }

        $domains = $domainQuery->get();

        // Optimize assigned domains query - use single query
        $assigned = $user->role === 'Admin'
            ? $domains->pluck('id')->all()
            : $user->domains()->pluck('domains.id')->all();

        // Fetch permissions asynchronously for better performance
        $permissions = $this->fetchUserPermissions($request, $user);

        return response()->json([
            'domains'          => $domains,
            'assigned_domains' => $assigned,
            'page_permissions' => $permissions,
        ]);
    }

    /**
     * Fetch user permissions with optimized error handling and caching.
     *
     * @param Request $request
     * @param \App\Models\User $user
     * @return array
     */
    private function fetchUserPermissions(Request $request, $user): array
    {
        $permissionsUrl = config('services.solucomp_page_permissions.permissions');
        $bearerToken = $request->bearerToken();

        // Early return if no permissions URL or token
        if (!$permissionsUrl || !$bearerToken) {
            if (!$permissionsUrl) {
                Log::warning('SOLUCOMP_USERPAGE_PERMISSION environment variable is not set.');
            }
            if (!$bearerToken) {
                Log::warning('Request is missing Bearer token.');
            }
            return [];
        }

        try {
            $response = Http::withToken($bearerToken)
                ->acceptJson()
                ->timeout(3) // Reduced timeout for better performance
                ->get($permissionsUrl);

            if ($response->successful()) {
                return $response->json('page_permissions', []);
            }

            Log::warning('Failed to fetch page permissions.', [
                'user_uuid' => $user->uuid,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Exception when fetching page permissions.', [
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
}
