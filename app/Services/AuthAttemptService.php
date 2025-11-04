<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class AuthAttemptService
{
    public const MAX_ATTEMPTS = 5;

    public function recordFailure(User $user): void
    {
        $failedAttempts = $user->failed_attempts + 1;

        if ($failedAttempts >= self::MAX_ATTEMPTS) {
            $user->update([
                'failed_attempts' => $failedAttempts,
                'locked_until' => now()->addYears(10), // Lock for 10 years, effectively "permanent"
            ]);
        } else {
            $user->update([
                'failed_attempts' => $failedAttempts
            ]);
        }
    }

    public function clearFailures(User $user): void
    {
        if ($user->locked_until && now()->lt($user->locked_until)) {
            return;
        }

        $user->update([
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    public function isLocked(User $user): bool
    {
        return $user->locked_until && now()->lt($user->locked_until);
    }
}