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

class ProcessActivationRequestJob implements ShouldQueue
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

            $deactivateInfo = $this->fetchDeactivationInfo($user->uuid, $syncSecret);

            if ($deactivateInfo) {
                $this->accessRequest->update([
                    'deactivate_info' => $deactivateInfo
                ]);

                Log::info('Deactivation info updated for activation request', [
                    'access_request_id' => $this->accessRequest->id,
                    'user_uuid' => $user->uuid
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process activation request', [
                'access_request_id' => $this->accessRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Fetch deactivation info based on domain key
     */
    private function fetchDeactivationInfo(string $userUuid, string $syncSecret): ?array
    {
        // Get the domain for this access request
        $domain = $this->accessRequest->domain;

        if (!$domain) {
            Log::warning('Domain not found for access request', [
                'access_request_id' => $this->accessRequest->id,
                'domain_id' => $this->accessRequest->domain_id
            ]);
            return null;
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
            Log::info("Domain key '{$domainKey}' does not require deactivation info check", [
                'domain_key' => $domainKey,
                'access_request_id' => $this->accessRequest->id
            ]);
            return null;
        }

        if (!$baseUrl) {
            Log::warning("Deactivate domain endpoint not configured for {$serviceName}");
            return null;
        }

        try {
            // Construct the URL properly
            $url = rtrim($baseUrl, '/') . "/users/{$userUuid}/deactivated-by";

            Log::info("Fetching deactivation info", [
                'domain_key' => $domainKey,
                'service' => $serviceName,
                'base_url' => $baseUrl,
                'constructed_url' => $url,
                'user_uuid' => $userUuid
            ]);

            $response = Http::withToken($syncSecret)
                ->acceptJson()
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'success' && isset($data['data']['deactivated_by'])) {
                    $deactivateInfo = $data['data']['deactivated_by'];
                    $deactivateInfo['domain'] = $serviceName;
                    $deactivateInfo['deactivation_date'] = $data['data']['deactivation_date'] ?? null;

                    Log::info("Deactivation info found for user {$userUuid} from {$serviceName}");
                    return $deactivateInfo;
                }
            } else {
                Log::warning("Failed to fetch deactivation info from {$serviceName}", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Exception fetching deactivation info from {$serviceName}", [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
        }

        return null;
    }
}
