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

            Log::warning('Solucomp API error: ' . $response->body());
            return ['error' => 'Solucomp API call failed'];
        } catch (\Exception $e) {
            Log::error('Solucomp API exception: ' . $e->getMessage());
            return ['error' => 'Solucomp API exception occurred'];
        }
    }
}
