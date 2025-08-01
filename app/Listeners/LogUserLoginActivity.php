<?php

namespace App\Listeners;

use App\Models\UserActivity;
use Illuminate\Auth\Events\Login;

class LogUserLoginActivity
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
    public function handle(Login $event): void
    {
        // to prevent duplicate entries
        $recentActivity = UserActivity::where('user_id', $event->user->getAuthIdentifier())
            ->where('event_type', 'login')
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();

        if (!$recentActivity) {
            UserActivity::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'event_type' => 'login',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'event_time' => now(),
            ]);
        }
    }
}
