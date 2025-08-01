<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolucompApiService
{
    protected string $baseUrl;
    protected string $summaryEndpoint;

    public function __construct()
    {
        $this->baseUrl = config('services.solucomp_base_url');
        $this->summaryEndpoint = '/sso/solucomp/summary';
    }

    public function fetchSummary(): array
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . $this->summaryEndpoint);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('JobFinder API error: ' . $response->body());
            return ['error' => 'JobFinder API call failed'];
        } catch (\Exception $e) {
            Log::error('JobFinder API exception: ' . $e->getMessage());
            return ['error' => 'JobFinder API exception occurred'];
        }
    }
}
