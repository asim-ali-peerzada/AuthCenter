<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PropagateUserUpdateToDomains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $origin;

    /**
     * Create a new job instance.
     *
     * @param  array  $user
     * @param  string $origin
     */
    public function __construct(array $user, string $origin)
    {
        $this->user = $user;
        $this->origin = $origin;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Find the user by UUID to check domain access
        $user = User::where('uuid', $this->user['uuid'])->first();

        if (!$user) {
            Log::warning("User not found for sync propagation", ['uuid' => $this->user['uuid']]);
            return;
        }

        $targets = [
            'ccms' => config('services.ccms_sync'),
            'jobfinder' => config('services.job_finder_sync'),
            'solucomp'  => config('services.solucomp_sync'),
        ];

        // Get all domains the user has access to (excluding origin domain)
        $userDomains = $user->domains()
            ->where('key', '!=', $this->origin)
            ->whereIn('key', array_keys($targets))
            ->get();

        foreach ($userDomains as $domain) {
            $domainKey = $domain->key;
            $url = $targets[$domainKey];

            try {
                Http::timeout(10)->post($url, [
                    'uuid'       => $this->user['uuid'],
                    'email'      => $this->user['email'],
                    'first_name' => $this->user['first_name'],
                    'last_name'  => $this->user['last_name'],
                    'password'   => $this->user['password'],
                ]);

                Log::info("User sync successful to {$domainKey} for user: {$this->user['uuid']}");
            } catch (\Throwable $e) {
                Log::error("Failed to sync user to {$domainKey}", [
                    'error' => $e->getMessage(),
                    'user_uuid' => $this->user['uuid'],
                ]);
            }
        }

        // Log if user has no linked domains to sync to
        if ($userDomains->isEmpty()) {
            Log::info("No linked domains found for user sync propagation", [
                'user_uuid' => $this->user['uuid'],
                'origin' => $this->origin,
            ]);
        }
    }
}
