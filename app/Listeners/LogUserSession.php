<?php

namespace App\Listeners;


use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class LogUserSession
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
    public function handle(Login $event)
    {
        $sessionId = session()->getId();
        $sessionExists = DB::table('sessions')->where('id', $sessionId)->exists();

        if (!$sessionExists) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $event->user->getAuthIdentifier(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'payload' => 'User Loged In',
                'last_activity' => now()->timestamp,
            ]);
        } else {
            session()->put('last_activity', now());
        }
    }
}
