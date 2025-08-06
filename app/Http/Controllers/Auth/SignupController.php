<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Requests\Auth\SignupWith2FARequest;
use App\Models\User;
use App\Models\Domain;
use App\Services\JwtService;
use App\Services\TwoFactorAuthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\SyncNewUserJob;

class SignupController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService,
        protected JwtService $jwtService
    ) {}

    /**
     * Step 1: Generate 2FA QR Code for signup
     */
    public function initiate2FA(SignupRequest $request)
    {
        // Generate 2FA secret
        $secret = $this->twoFactorService->generateSecretKey();
        
        // Store signup data temporarily with the secret
        $sessionId = $this->twoFactorService->storeTempSignupData([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'key' => $request->key,
        ], $secret);

        // Generate QR code
        $qrCodeSvg = $this->twoFactorService->getQRCodeSvg(
            'AuthCenter',
            $request->email,
            $secret
        );

        return response()->json([
            'session_id' => $sessionId,
            'qr_code_svg' => $qrCodeSvg,
            'message' => 'Scan the QR code with your Google Authenticator app and enter the 6-digit code to complete signup.'
        ]);
    }

    /**
     * Step 2: Verify 2FA code and complete signup
     */
    public function completeSignup(SignupWith2FARequest $request)
    {
        // Retrieve temporary signup data
        $tempData = $this->twoFactorService->getTempSignupData($request->session_id);
        
        if (!$tempData) {
            return response()->json([
                'message' => 'Session expired or invalid. Please start the signup process again.'
            ], 422);
        }

        $signupData = $tempData['signup_data'];
        $secret = $tempData['secret'];

        // Verify 2FA code
        if (!$this->twoFactorService->verifyCode($secret, $request->code)) {
            return response()->json([
                'message' => 'Invalid 2FA code. Please try again.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if email is still unique (in case someone else registered during 2FA setup)
            if (User::where('email', $signupData['email'])->exists()) {
                return response()->json([
                    'message' => 'Email address is already registered.'
                ], 422);
            }

            $userData = [
                'first_name' => $signupData['first_name'],
                'last_name' => $signupData['last_name'],
                'email' => $signupData['email'],
                'user_origin' => $signupData['key'],
                'password' => bcrypt($signupData['password']),
                'google2fa_secret' => $secret,
                'is_2fa_enabled' => true,
                'is_2fa_verified' => true,
            ];

            if (isset($signupData['key']) && $signupData['key'] === 'jobfinder') {
                $userData['is_approved'] = true;
            }

            // Create user with 2FA enabled
            $user = User::create($userData);

            // Attach default domain
            $jobfinderDomain = Domain::where('key', 'jobfinder')->first();
            if ($jobfinderDomain) {
                $user->domains()->attach($jobfinderDomain->id);
            } else {
                Log::warning('Default domain "jobfinder" not found.');
            }

            // Clear temporary data
            $this->twoFactorService->clearTempSignupData($request->session_id);

            DB::commit();

            // Generate JWT token
            $token = $this->jwtService->issue(['sub' => $user->uuid, 'email' => $user->email]);

            Log::info('User registered successfully with 2FA', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'token' => $token,
                'message' => 'Account created successfully with 2FA enabled.',
                'user' => [
                    'uuid' => $user->uuid,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'is_2fa_enabled' => true,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clear temporary data on error
            $this->twoFactorService->clearTempSignupData($request->session_id);
            
            Log::error('Signup with 2FA failed', [
                'error' => $e->getMessage(),
                'email' => $signupData['email']
            ]);

            return response()->json([
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }

    // Legacy User Signup
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

        // // dispatch a job to sync the new user
        // if ($key) {
        //     SyncNewUserJob::dispatch($user->uuid, $key);
        // }

        // Attach 'jobfinder' by default.
        $jobfinderDomain = Domain::where('key', 'jobfinder')->first();
        if ($jobfinderDomain) {
            $user->domains()->attach($jobfinderDomain->id);
        } else {
            Log::warning('Default domain "jobfinder" not found.');
        }

        $token = $jwt->issue(['sub' => $user->uuid, 'email' => $user->email]);

        return response()->json(['token' => $token], 201);
    }
}
