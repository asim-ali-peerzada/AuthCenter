<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncNewUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $userUuid;
    protected string $originKey;

    /**
     * Create a new job instance.
     *
     * @param string $userUuid
     * @param string $originKey
     */
    public function __construct(string $userUuid, string $originKey)
    {
        $this->userUuid = $userUuid;
        $this->originKey = $originKey;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = User::where('uuid', $this->userUuid)->first();

        if (!$user) {
            Log::error("User with UUID {$this->userUuid} not found for new user sync.");
            return;
        }

        $syncUrl = config("services.new_user_sync_routes.{$this->originKey}");

        if (!$syncUrl) {
            Log::warning("No sync URL configured for origin key: {$this->originKey}");
            return;
        }

        $userData = [
            'uuid'        => $user->uuid,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'email'       => $user->email,
            'password'    => $user->password,
            'user_origin' => $user->user_origin,
            'role'        => $user->role,
        ];

        $syncSecret = config('services.sync.secret');
        if (!$syncSecret) {
            Log::critical('SYNC_SECRET is not configured. New user sync will be insecure and likely fail.');
            return;
        }

        $secret = base64_decode($syncSecret);

        try {

            $requestBody = json_encode($userData);
            $signature = hash_hmac('sha256', $requestBody, $secret);

            $response = Http::withBody($requestBody, 'application/json')
                ->withHeaders(['X-Auth-Signature' => $signature])
                ->timeout(10)
                ->post($syncUrl);

            if ($response->successful()) {
                Log::info("Successfully synced new user to {$this->originKey}", ['uuid' => $user->uuid]);
            } else {
                Log::error("Failed to sync new user to {$this->originKey}", [
                    'uuid'     => $user->uuid,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Exception while syncing new user to {$this->originKey}", [
                'uuid'  => $user->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
