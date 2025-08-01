<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncUserDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $userUuid;
    protected array $domainKeys;

    /**
     * Create a new job instance.
     *
     * @param string $userUuid The UUID of the user who was deleted.
     * @param array $domainKeys The keys of the domains to notify (e.g., ['ccms', 'jobfinder','solucomp]).
     */
    public function __construct(string $userUuid, array $domainKeys)
    {
        $this->userUuid = $userUuid;
        $this->domainKeys = $domainKeys;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $targets = [
            'ccms'      => config('services.delete_user_routes.ccms'),
            'jobfinder' => config('services.delete_user_routes.jobfinder'),
            'solucomp'  => config('services.delete_user_routes.solucomp'),
        ];

        $syncSecret = config('services.sync.secret');
        if (!$syncSecret) {
            Log::critical('SYNC_SECRET is not configured. User deletion propagation will fail.');
            return;
        }

        foreach ($this->domainKeys as $key) {
            if (!isset($targets[$key]) || !$targets[$key]) {
                Log::warning("No sync deletion endpoint configured for domain key: {$key}");
                continue;
            }

            $deleteUrl = rtrim($targets[$key], '/') . '/' . $this->userUuid;

            try {
                $response = Http::withToken($syncSecret)->timeout(10)->delete($deleteUrl);

                if ($response->successful()) {
                    Log::info("User deletion sync successful to {$key} for user: {$this->userUuid}");
                } else {
                    Log::error("User deletion sync failed to {$key} for user: {$this->userUuid}", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("Exception during user deletion sync to {$key}", [
                    'error' => $e->getMessage(),
                    'user_uuid' => $this->userUuid,
                ]);
            }
        }
    }
}
