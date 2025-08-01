<?php

namespace App\Services;

use App\Models\User;
use App\Models\Domain;
use App\Models\UserActivity;

class LogUserActivityService
{
    public function log(User $user, string $eventType, ?int $domainId = null): void
    {
        $domainName = $domainId
            ? optional(Domain::find($domainId))->name ?? 'Unknown Domain'
            : null;

        $fullEventType = $domainName
            ? "{$domainName} {$eventType}"
            : $eventType;

        UserActivity::create([
            'user_id'    => $user->id,
            'event_type' => $fullEventType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'event_time' => now(),
        ]);
    }
}
