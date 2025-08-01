<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CcmsApiService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.base_url');
    }

    public function fetchSummary(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/sso/ccms/summary");

            if ($response->successful()) {
                return $response->json('data');
            }

            return [
                'total_users' => 0,
                'last_activity_at' => null,
                'uptime' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect to CCMS API', [
                'message' => $e->getMessage(),
            ]);

            return [
                'total_users' => 0,
                'last_activity_at' => null,
                'uptime' => null,
            ];
        }
    }
}
