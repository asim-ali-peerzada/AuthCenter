<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\UserActivity;
use Illuminate\Auth\Events\Logout;

class LogUserLogoutActivity
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        // Check if a logout activity was already recorded
        $recentActivity = UserActivity::where('user_id', $event->user->getAuthIdentifier())
            ->where('event_type', 'logout')
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();

        if (!$recentActivity) {
            UserActivity::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'event_type' => 'logout',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'event_time' => now(),
            ]);
        }
    }
}
