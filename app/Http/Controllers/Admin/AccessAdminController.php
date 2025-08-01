<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AccessAdminController extends Controller
{
    // grant access
    public function grant(User $user, Domain $domain): JsonResponse
    {
        $user->domains()->syncWithoutDetaching($domain->id);

        return response()->json(['message' => 'Access granted']);
    }

    // revoke access
    public function revoke(User $user, Domain $domain): JsonResponse
    {


        $user->domains()->detach($domain->id);

        return response()->json(['message' => 'Access revoked']);
    }
}
