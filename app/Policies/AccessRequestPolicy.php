<?php

namespace App\Policies;

use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class AccessRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any access requests.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view access requests (their own or all if admin)
        return true;
    }

    /**
     * Determine whether the user can view the access request.
     */
    public function view(User $user, AccessRequest $accessRequest): bool
    {
        // Users can view their own requests, admins can view all
        return $user->uuid === $accessRequest->user_uuid ||
            $user->role === 'admin';
    }

    /**
     * Determine whether the user can create access requests.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create access requests
        return true;
    }

    /**
     * Determine whether the user can update the access request.
     */
    public function update(User $user, AccessRequest $accessRequest): bool
    {
        // Only admins can update access requests (approve/reject)
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the access request.
     */
    public function delete(User $user, AccessRequest $accessRequest): bool
    {
        // Users can delete their own pending requests, admins can delete any
        if ($user->role === 'admin') {
            return true;
        }

        return $user->uuid === $accessRequest->user_uuid &&
            $accessRequest->status === 'pending';
    }

    /**
     * Determine whether the user can approve the access request.
     */
    public function approve(User $user, AccessRequest $accessRequest): bool
    {
        // Only admins can approve requests
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can reject the access request.
     */
    public function reject(User $user, AccessRequest $accessRequest): bool
    {
        // Only admins can reject requests
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can resubmit the access request.
     */
    public function resubmit(User $user, AccessRequest $accessRequest): bool
    {
        // Users can resubmit their own rejected requests
        return $user->uuid === $accessRequest->user_uuid &&
            $accessRequest->status === 'rejected';
    }
}
