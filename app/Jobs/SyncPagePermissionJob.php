<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPagePermissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $userUuid;
    protected string $permission;
    protected string $action;
    protected string $domainKey;

    /**
     * Create a new job instance.
     *
     * @param string $userUuid
     * @param string $permission
     * @param string $action
     * @param string $domainKey
     */
    public function __construct(string $userUuid, string $permission, string $action, string $domainKey)
    {
        $this->userUuid = $userUuid;
        $this->permission = $permission;
        $this->action = $action;
        $this->domainKey = $domainKey;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $pagePermissionUrl = config('services.solucomp_page_permissions.page_permissions');

        if (!$pagePermissionUrl) {
            Log::warning('SOLUCOMP_PAGE_PERMISSION URL is not configured.');
            return;
        }

        $payload = [
            'uuid' => $this->userUuid,
            'permission' => $this->permission,
            'action' => $this->action,
        ];

        $syncSecret = config('services.sync.secret');
        if (!$syncSecret) {
            Log::critical('SYNC_SECRET is not configured. Page permission sync will be insecure and likely fail.');
            return;
        }

        $secret = base64_decode($syncSecret);

        try {
            $requestBody = json_encode($payload);
            $signature = hash_hmac('sha256', $requestBody, $secret);

            $response = Http::withBody($requestBody, 'application/json')
                ->withHeaders(['X-Auth-Signature' => $signature])
                ->timeout(10)
                ->post($pagePermissionUrl);

            if ($response->successful()) {
                Log::info("Successfully synced page permission for domain {$this->domainKey}", [
                    'uuid' => $this->userUuid,
                    'permission' => $this->permission,
                    'action' => $this->action,
                ]);
            } else {
                Log::error("Failed to sync page permission for domain {$this->domainKey}", [
                    'uuid' => $this->userUuid,
                    'permission' => $this->permission,
                    'action' => $this->action,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Exception while syncing page permission for domain {$this->domainKey}", [
                'uuid' => $this->userUuid,
                'permission' => $this->permission,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}