<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainStatus
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url   = config('services.domain_urls.jobfinder');
        $this->token = config('services.sso.shared_token');
    }

    public function getUserStatus(string $uuid, string $email): array
    {
        try {
            $url = $this->url . '/user/status';
            $payload = [
                'user_uuid' => $uuid,
                'email'     => $email,
            ];

            Log::info('Making domain status request', [
                'url' => $url,
                'payload' => $payload,
                'has_token' => !empty($this->token)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, $payload);

            Log::info('Domain status response received', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            return [
                'status_code' => $response->status(),
                'body'        => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Domain status check failed: ' . $e->getMessage(), [
                'url' => $this->url . '/api/user/status',
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status_code' => 500,
                'body'        => [
                    'user_status' => 'error',
                    'message' => 'Unable to check user status. Please try again later.'
                ],
            ];
        }
    }
}
