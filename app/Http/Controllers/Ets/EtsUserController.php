<?php

namespace App\Http\Controllers\Ets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ets\EtsUserStatusRequest;
use App\Http\Requests\Ets\EtsUserUnlockRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EtsUserController extends Controller
{
    /**
     * POST /auth/users/status
     * Check lock status for a list of user UUIDs.
     */
    public function status(EtsUserStatusRequest $request): JsonResponse
    {
        $userIds = $request->input('user_ids', []);

        $users = User::whereIn('uuid', $userIds)
            ->get(['uuid', 'failed_attempts', 'locked_until']);

        $lockedUserIds = [];
        $statuses = [];

        foreach ($users as $user) {
            $isLocked = $user->locked_until && now()->lt($user->locked_until);

            if ($isLocked) {
                $lockedUserIds[] = $user->uuid;
            }

            $statuses[] = [
                'uuid' => $user->uuid,
                'failed_attempts' => $user->failed_attempts,
                'locked_until' => $user->locked_until,
                'is_locked' => $isLocked,
            ];
        }

        $foundUuids = $users->pluck('uuid')->all();
        $notFound = array_values(array_diff($userIds, $foundUuids));

        return response()->json([
            'locked_user_ids' => $lockedUserIds,
            'statuses' => $statuses,
            'not_found_user_ids' => $notFound,
            'checked_count' => count($userIds),
        ]);
    }

    /**
     * POST /auth/users/unlock
     * Unlock a user, validating the ETS secret for security.
     */
    public function unlock(EtsUserUnlockRequest $request): JsonResponse
    {
        // Secret is validated via middleware 'auth.ets.secret'

        $uuid = $request->input('user_id');

        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        try {
            $user->update([
                'locked_until' => null,
                'failed_attempts' => 0,
            ]);

            return response()->json([
                'message' => 'User unlocked successfully',
                'user_uuid' => $uuid,
            ]);
        } catch (\Throwable $e) {
            Log::error('ETS unlock failed', [
                'user_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to unlock user',
            ], 500);
        }
    }
}