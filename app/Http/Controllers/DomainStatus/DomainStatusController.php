<?php

namespace App\Http\Controllers\DomainStatus;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DomainStatus;


class DomainStatusController extends Controller
{
    public function jobFinderStatus(Request $request, DomainStatus $domainStatusService)
    {
        try {

            $user = $request->user();

            // Validate required fields
            if (!$user || !$user->uuid || !$user->email) {
                Log::warning('User information missing for domain status check', [
                    'user_exists' => !!$user,
                    'has_uuid' => !!($user?->uuid),
                    'has_email' => !!($user?->email)
                ]);

                return response()->json([
                    'user_status' => 'error',
                    'message' => 'User information not found'
                ], 400);
            }

            $result = $domainStatusService->getUserStatus(
                $user->uuid,
                $user->email
            );

            return response()->json($result['body'], $result['status_code']);
        } catch (\Exception $e) {
            Log::error('Domain status controller error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'user_status' => 'error',
                'message' => 'Internal server error occurred'
            ], 500);
        }
    }
}
