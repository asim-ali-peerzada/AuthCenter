<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    // GET domains for authenticated user
    public function index(): JsonResponse
    {
        /** @var \App\Models\User|null $user */

        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $domains = Domain::all(['id', 'name', 'url']);

        if ($user->isAdmin()) {
            $assigned = $domains->pluck('id')->all();
        } else {
            $assigned = $user->domains()->pluck('domains.id')->all();
        }

        return response()->json([
            'domains'          => $domains,
            'assigned_domains' => $assigned,
        ]);
    }
}
