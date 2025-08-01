<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Log;

class JwtService
{
    public function issue(array $payload): string
    {
        $ttl   = (int) config('services.jwt.ttl');
        $now   = time();
        $jti   = (string) Str::uuid();

        $claims = array_merge($payload, [
            'iss' => config('services.jwt.issuer'),
            'aud' => 'authcenter',
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => $jti,
        ]);

        $path = storage_path(env('JWT_PRIVATE_KEY_PATH', 'oauth-keys/private.pem'));
        if (!file_exists($path)) {
            throw new \RuntimeException("JWT private key file not found at: $path");
        }

        $privateKey = file_get_contents($path);

        return JWT::encode($claims, $privateKey, 'RS256');
    }

    public function decode(string $jwt): object
    {
        $publicKey = file_get_contents(storage_path('oauth-keys/public.pem'));

        // Allow 10 seconds of clock skew
        JWT::$leeway = 10;

        return JWT::decode($jwt, new Key($publicKey, 'RS256'));
    }


    public function issueRefreshToken(string $userUuid): string
    {
        $ttl = (int) config('services.jwt.refresh_ttl', 1209600);
        $expiresAt = now()->addSeconds($ttl);
        $refreshToken = Str::random(128);

        RefreshToken::create([
            'user_uuid' => $userUuid,
            'token' => hash('sha256', $refreshToken),
            'expires_at' => $expiresAt,
        ]);

        log::info('Issued refresh token', [
            'uuid' => $userUuid,
            'expires_at' => $expiresAt->toDateTimeString()
        ]);
        return $refreshToken;
    }

    public function validateRefreshToken(string $userUuid, string $refreshToken): bool
    {
        $hashed = hash('sha256', $refreshToken);

        log::info('Checking refresh token', [
            'user_uuid' => $userUuid,
            'hashed' => $hashed,
        ]);

        $token = RefreshToken::where('user_uuid', $userUuid)
            ->where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();

        return (bool) $token;
    }
}
