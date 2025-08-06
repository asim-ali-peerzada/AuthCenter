<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class TwoFactorAuthService
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQRCodeSvg(string $companyName, string $userEmail, string $secret): string
    {
        $qrCodeUrl = $this->google2fa->getQRCodeUrl($companyName, $userEmail, $secret);

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            )
        );

        return $writer->writeString($qrCodeUrl);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Store temporary 2FA data for signup process
     */
    public function storeTempSignupData(array $signupData, string $secret): string
    {
        $sessionId = Str::uuid();
        $cacheKey = "signup_2fa_{$sessionId}";
        
        // Store for 10 minutes
        Cache::put($cacheKey, [
            'signup_data' => $signupData,
            'secret' => $secret,
            'created_at' => now()
        ], 600);

        return $sessionId;
    }

    /**
     * Retrieve temporary signup data
     */
    public function getTempSignupData(string $sessionId): ?array
    {
        $cacheKey = "signup_2fa_{$sessionId}";
        return Cache::get($cacheKey);
    }

    /**
     * Clear temporary signup data
     */
    public function clearTempSignupData(string $sessionId): void
    {
        $cacheKey = "signup_2fa_{$sessionId}";
        Cache::forget($cacheKey);
    }
}