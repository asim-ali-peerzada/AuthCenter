<?php

namespace App\Http\Controllers\Admin;

use App\Models\Domain;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Admin\UpdateUserApprovalRequest;

class UserApprovalController extends Controller
{
    /**
     * Fetch all users pending approval
    */
    public function index(): JsonResponse
    {
        $pendingUsers = User::with('domains:id,name,url')
            ->where('is_approved', false)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'uuid',
                'first_name',
                'last_name',
                'email',
                'status',
                'is_approved',
                'user_origin',
                'created_at'
            ]);

        // Get unique origin keys from the pending users to perform an efficient lookup.
        $originKeys = $pendingUsers->pluck('user_origin')->filter()->unique();
        $domainIdMap = collect();

        // If there are any origin keys, fetch their corresponding domain IDs.
        if ($originKeys->isNotEmpty()) {
            $domainIdMap = Domain::whereIn('key', $originKeys)->pluck('id', 'key');

            // Append the domain ID to each user record.
            $pendingUsers->each(function ($user) use ($domainIdMap) {
                $user->origin_domain_key = $domainIdMap->get($user->user_origin);
            });
        }

        // Transform the collection to prevent mutating the User model directly.
        $transformedUsers = $pendingUsers->map(function ($user) use ($domainIdMap) {
            $userArray = $user->toArray();
            $userArray['origin_domain_key'] = $domainIdMap->get($user->user_origin);

            return $userArray;
        });

        return response()->json($transformedUsers);
    }

    /**
     * Approve or disapprove one or multiple users
     */
    public function updateApprovalStatus(UpdateUserApprovalRequest $request): JsonResponse
    {
        $updatedCount = User::whereIn('uuid', $request->user_ids)
            ->update(['is_approved' => $request->is_approved]);

        return response()->json([
            'message' => "Updated approval status for {$updatedCount} user(s)."
        ]);
    }
}
