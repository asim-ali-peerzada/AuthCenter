<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SamsungApiService
{
    protected string $baseUrl;
    protected string $summaryEndpoint;

    public function __construct()
    {
        $this->baseUrl = config('services.samsung_base_url');
        $this->summaryEndpoint = '/sso/samsung/summary';
    }

    public function fetchSummary(): array
    {
        try {
            $signature = config('services.sync.secret');

            if (!$signature) {
                Log::error('SYNC_SECRET is not configured');
                return ['error' => 'Sync signature configuration missing'];
            }

            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Sync-Signature' => $signature
                ])
                ->post($this->baseUrl . $this->summaryEndpoint);

            if ($response->successful()) {
                return $response->json();
            }

            return ['error' => 'Samsung API call failed', 'status' => $response->status()];
        } catch (\Exception $e) {
            Log::error('Samsung API exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return ['error' => 'Samsung API exception occurred'];
        }
    }
}
