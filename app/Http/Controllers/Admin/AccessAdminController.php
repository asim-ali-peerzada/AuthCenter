<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPagePermissionJob;
use App\Models\AccessRequest;
use App\Models\Domain;
use App\Models\User;

use Illuminate\Http\JsonResponse;

class AccessAdminController extends Controller
{
    // grant access
    public function grant(User $user, Domain $domain): JsonResponse
    {
        $user->domains()->syncWithoutDetaching($domain->id);

        if (in_array($domain->key, ['solucomp_cop', 'solucomp_compare'])) {
            $mainDomain = Domain::where('key', 'solucomp')->first(); // or ->value('id');
            if ($mainDomain) {
                $user->domains()->syncWithoutDetaching($mainDomain->id);
            }
        }

        // Update access request status to approved if any pending request exists
        AccessRequest::where('user_uuid', $user->uuid)
            ->where('domain_id', $domain->id)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        // Dispatch job for solucomp domains
        $this->dispatchPagePermissionJob($user, $domain, 'assign');

        return response()->json(['message' => 'Access granted']);
    }

    // revoke access
    public function revoke(User $user, Domain $domain): JsonResponse
    {
        $user->domains()->detach($domain->id);

        // Update access request status to rejected if any pending request exists
        AccessRequest::where('user_uuid', $user->uuid)
            ->where('domain_id', $domain->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        // Dispatch job for solucomp domains
        $this->dispatchPagePermissionJob($user, $domain, 'revoke');

        return response()->json(['message' => 'Access revoked']);
    }

    /**
     * Dispatch page permission job for solucomp domains
     *
     * @param User $user
     * @param Domain $domain
     * @param string $action
     * @return void
     */
    private function dispatchPagePermissionJob(User $user, Domain $domain, string $action): void
    {
        // Check if domain key is one of the solucomp domains
        if (!in_array($domain->key, ['solucomp_cop', 'solucomp_compare'])) {
            return;
        }

        // Determine permission based on domain key
        $permission = match ($domain->key) {
            'solucomp_cop' => '/Admin/cop',
            'solucomp_compare' => '/Admin/compare',
            default => null,
        };

        if ($permission) {
            SyncPagePermissionJob::dispatch(
                $user->uuid,
                $permission,
                $action,
                $domain->key
            );
        }
    }
}
