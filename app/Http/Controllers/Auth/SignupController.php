<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\User;
use App\Models\Domain;
use App\Services\JwtService;
use Illuminate\Support\Facades\Log;
use App\Jobs\SyncNewUserJob;

class SignupController extends Controller
{
    // User Signup
    public function store(SignupRequest $request, JwtService $jwt)
    {
        $key = $request->input('key');

        $user = User::create([
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'email'    => $request->email,
            'user_origin'    => $request->key,
            'password' => bcrypt($request->password),
        ]);

        // dispatch a job to sync the new user
        if ($key) {
            SyncNewUserJob::dispatch($user->uuid, $key);
        }

        $domainIdsToAttach = [];

        // Attach 'jobfinder' by default.
        $jobfinderDomain = Domain::where('key', 'jobfinder')->first();
        if ($jobfinderDomain) {
            $domainIdsToAttach[] = $jobfinderDomain->id;
        } else {
            Log::warning('Default domain "jobfinder" not found.');
        }

        // Also attach the domain from the request key, if provided and valid.
        if ($key) {
            if (in_array($key, ['jobfinder', 'ccms', 'solucomp'])) {
                $domain = Domain::where('key', $key)->first();
                if ($domain) {
                    $domainIdsToAttach[] = $domain->id;
                } else {
                    Log::warning('Domain not found for key: ' . $key);
                }
            } else {
                Log::warning('Invalid key provided: ' . $key);
            }
        }

        // Attach all unique domain IDs.
        if (!empty($domainIdsToAttach)) {
            $user->domains()->attach(array_unique($domainIdsToAttach));
        }

        $token = $jwt->issue(['sub' => $user->uuid, 'email' => $user->email]);

        return response()->json(['token' => $token], 201);
    }
}
