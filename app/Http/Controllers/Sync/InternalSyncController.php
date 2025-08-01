<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use App\Jobs\PropagateUserUpdateToDomains;
use Illuminate\Support\Facades\Log;

class InternalSyncController extends Controller
{
    // Sync from subdomains
    public function store(Request $request): JsonResponse
    {
        try {

            // Verify signature
            $secret = base64_decode(config('services.sync.secret'));
            $signature = $request->header('X-Auth-Signature');
            $computed = hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Validate
            $data = $request->validate([
                'uuid'           => 'required|uuid',
                'full_name'      => 'required|string',
                'last_name'      => 'required|string',
                'role'           => 'nullable|string',
                'personal_email' => 'required|email',
                'password'       => 'required|string',
                'action'         => 'required|in:created,updated,activated,deactivated',
                'user_origin'    => 'required|in:ccms,jobfinder,solucomp',
            ]);

            $user = User::where('uuid', $data['uuid'])
                ->orWhere('email', $data['personal_email'])
                ->first();

            if (! $user) {
                // create new
                DB::transaction(function () use ($data, &$user) {
                    $user = User::create([
                        'uuid' => $data['uuid'],
                        'first_name' => $data['full_name'],
                        'last_name' => $data['last_name'],
                        'external_role' => $data['role'],
                        'email' => $data['personal_email'],
                        'password' => $data['password'],
                        'user_origin' => $data['user_origin'],
                        'status' => $data['action'] === 'deactivated' ? 'inactive' : 'active',
                    ]);
                });

                $domainIdsToAttach = [];
                $originKey = $data['user_origin'] ?? null;

                // Attach the domain from the request origin key, if provided and valid.
                if ($originKey) {
                    $originDomain = Domain::where('key', $originKey)->first();
                    if ($originDomain) {
                        $domainIdsToAttach[] = $originDomain->id;
                    } else {
                        Log::warning('Origin domain not found for key during sync', ['key' => $originKey]);
                    }
                }

                // Attach 'jobfinder' by default.
                $jobfinderDomain = Domain::where('key', 'jobfinder')->first();
                if ($jobfinderDomain) {
                    $domainIdsToAttach[] = $jobfinderDomain->id;
                } else {
                    Log::warning('Default domain "jobfinder" not found during sync.');
                }

                // Attach all unique domain IDs if the user was created successfully.
                if ($user && !empty($domainIdsToAttach)) {
                    try {
                        $uniqueDomainIds = array_unique($domainIdsToAttach);
                        $user->domains()->syncWithoutDetaching($uniqueDomainIds);
                        Log::info('User linked with domains on creation', ['user_id' => $user->id, 'domain_ids' => $uniqueDomainIds]);
                    } catch (\Exception $e) {
                        Log::error('Domain linking error on user creation', ['error' => $e->getMessage(), 'user_id' => $user->id, 'domain_ids' => $uniqueDomainIds ?? $domainIdsToAttach]);
                    }
                }
            } else {
                $user->update([
                    'uuid'     => $data['uuid'],
                    'first_name'  => $data['full_name'],
                    'last_name'   => $data['last_name'],
                    'external_role'   => $data['role'],
                    'email'    => $data['personal_email'],
                    'password' => $data['password'],
                ]);

                // Dispatch job for update in subdomains
                PropagateUserUpdateToDomains::dispatch([
                    'uuid' => $user->uuid,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'password' => $user->password,
                ], $data['user_origin']);
            }

            return response()->json(['message' => 'Synced']);
        } catch (\Throwable $e) {
            Log::error('InternalSyncController@store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Sync failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
