<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUserUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userUuid;
    public bool $syncAllDomains;


    public function __construct(string $userUuid, bool $syncAllDomains = false)
    {
        $this->userUuid = $userUuid;
        $this->syncAllDomains = $syncAllDomains;
    }

    public function handle()
    {
        $user = User::where('uuid', $this->userUuid)->first();

        if (!$user) {
            Log::error("User with UUID {$this->userUuid} not found.");
            return;
        }

        $userData = [
            'uuid'       => $user->uuid,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'password'   => $user->password,
        ];

        $targets = [
            'ccms' => config('services.ccms_sync'),
            'jobfinder' => config('services.job_finder_sync'),
        ];

        if ($this->syncAllDomains) {
            // Sync to all configured domains
            foreach ($targets as $domainKey => $url) {
                $this->syncWithApp($url, $userData, $domainKey);
            }
            return;
        }

        // Get domains the user has access to
        $userDomains = $user->domains()
            ->whereIn('key', array_keys($targets))
            ->get();

        if ($userDomains->isEmpty()) {
            Log::info("No linked domains found for user sync", [
                'user_uuid' => $user->uuid,
            ]);
            return;
        }

        // Sync only to domains the user has access to
        foreach ($userDomains as $domain) {
            $domainKey = $domain->key;
            $url = $targets[$domainKey];
            $this->syncWithApp($url, $userData, $domainKey);
        }
    }

    protected function syncWithApp($url, $data, $app)
    {
        Log::info("Syncing user data to {$app}:", $data);
        try {
            Http::timeout(5)->post($url, $data);
            Log::info("User sync successful to {$app} for user: {$data['uuid']}");
        } catch (\Throwable $e) {
            Log::error("Failed to sync user to {$app}", [
                'error' => $e->getMessage(),
                'user_uuid' => $data['uuid'],
            ]);
        }
    }
}
