<?php

namespace App\Jobs;

use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessUserActivationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $accessRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(AccessRequest $accessRequest)
    {
        $this->accessRequest = $accessRequest;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = User::where('uuid', $this->accessRequest->user_uuid)->first();

            if (!$user) {
                Log::warning('User not found for activation request', [
                    'access_request_id' => $this->accessRequest->id,
                    'user_uuid' => $this->accessRequest->user_uuid
                ]);
                return;
            }

            $syncSecret = config('services.sync.secret');

            if (!$syncSecret) {
                Log::error('SYNC_SECRET not configured');
                return;
            }

            $activationResult = $this->activateUser($user->uuid, $syncSecret);

            if ($activationResult) {
                Log::info('User activation request processed successfully', [
                    'access_request_id' => $this->accessRequest->id,
                    'user_uuid' => $user->uuid,
                    'domain_key' => $this->accessRequest->domain->key ?? 'unknown'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process user activation', [
                'access_request_id' => $this->accessRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Activate user on the appropriate service
     */
    private function activateUser(string $userUuid, string $syncSecret): bool
    {
        // Get the domain for this access request
        $domain = $this->accessRequest->domain;

        if (!$domain) {
            Log::warning('Domain not found for access request', [
                'access_request_id' => $this->accessRequest->id,
                'domain_id' => $this->accessRequest->domain_id
            ]);
            return false;
        }

        $domainKey = $domain->key;
        $baseUrl = null;
        $serviceName = null;

        // Determine which service to call based on domain key
        if ($domainKey === 'ccms') {
            $baseUrl = config('services.domain_urls.ccms');
            $serviceName = 'ccms';
        } elseif ($domainKey === 'jobfinder') {
            $baseUrl = config('services.domain_urls.jobfinder');
            $serviceName = 'jobfinder';
        } else {
            Log::info("Domain key '{$domainKey}' does not support user activation", [
                'domain_key' => $domainKey,
                'access_request_id' => $this->accessRequest->id
            ]);
            return false;
        }

        if (!$baseUrl) {
            Log::warning("Domain URL not configured for {$serviceName}");
            return false;
        }

        try {
            // Construct the URL for user activation
            $url = rtrim($baseUrl, '/') . "/users/{$userUuid}/activate";

            Log::info("Activating user", [
                'domain_key' => $domainKey,
                'service' => $serviceName,
                'base_url' => $baseUrl,
                'constructed_url' => $url,
                'user_uuid' => $userUuid
            ]);

            $response = Http::withToken($syncSecret)
                ->acceptJson()
                ->timeout(10)
                ->post($url, [
                    'user_uuid' => $userUuid,
                    'need_active' => true
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("User activation successful for {$serviceName}", [
                    'user_uuid' => $userUuid,
                    'response' => $data
                ]);

                // Update the user's external active status for this domain
                $user = User::where('uuid', $this->accessRequest->user_uuid)->first();
                if ($user) {
                    $externalStatus = $user->external_active_status ?? [];
                    $externalStatus[$this->accessRequest->domain->key] = 'active';

                    $user->update([
                        'external_active_status' => $externalStatus
                    ]);
                }

                // Also update the access request's external active status
                $this->accessRequest->update([
                    'external_active_status' => 'active'
                ]);

                Log::info('Access request external status updated', [
                    'access_request_id' => $this->accessRequest->id,
                    'external_active_status' => 'active'
                ]);

                return true;
            } else {
                Log::warning("Failed to activate user on {$serviceName}", [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'user_uuid' => $userUuid
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception activating user on {$serviceName}", [
                'error' => $e->getMessage(),
                'url' => $url,
                'user_uuid' => $userUuid
            ]);
            return false;
        }
    }
}
