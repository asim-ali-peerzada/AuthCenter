<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;

class UserActivityController extends Controller
{
    // GET user activities
    public function index()
    {
        $activities = UserActivity::with('user')
            ->latest()
            ->get()
            ->map(function ($activity) {
                return [
                    'user_name' => $activity->user?->first_name . ' ' . $activity->user?->last_name ?? null,
                    'event'      => $activity->event_type,
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                    'timestamp'   => $activity->event_time->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $activities,
        ]);
    }
}
