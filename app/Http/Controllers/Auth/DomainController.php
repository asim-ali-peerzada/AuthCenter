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

        $domains = Domain::all(['id', 'name', 'url','key']);

        if ($user->isAdmin()) {
            $assigned = $domains->pluck('id')->all();
        } else {
            $assigned = $user->domains()->pluck('domains.id')->all();
        }

        $permissions = [];
        $permissionsUrl = config('services.solucomp_page_permissions.permissions');

        if (!$permissionsUrl) {
            Log::warning('SOLUCOMP_USERPAGE_PERMISSION environment variable is not set. Cannot fetch page permissions.');
        } elseif (!$request->bearerToken()) {
            Log::warning('Request is missing Bearer token. Cannot fetch page permissions.');
        }

        if ($permissionsUrl && $request->bearerToken()) {
            try {
                log::info('coming insider the if condition');
                $response = Http::withToken($request->bearerToken())
                    ->acceptJson()
                    ->get($permissionsUrl);

                if ($response->successful()) {
                    $permissions = $response->json('page_permissions', []);
                } else {
                    Log::warning('Failed to fetch page permissions for user from SoluComp.', [
                        'user_uuid' => $user->uuid,
                        'status' => $response->status(),
                        'body' => $response->body(),
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

        log::info($permissions);

        return response()->json([
            'domains'          => $domains,
            'assigned_domains' => $assigned,
            'page_permissions' => $permissions,
        ]);
    }
}
