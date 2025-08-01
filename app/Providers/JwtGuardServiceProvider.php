<?php

namespace App\Providers;

use App\Services\JwtService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use App\Models\User;

class JwtGuardServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Auth::extend('jwt-request', function ($app, $name, array $config) {
            return new class($app['request'], resolve(JwtService::class)) implements Guard {
                private $user = null;

                public function __construct(private $request, private JwtService $jwt) {}

                public function user(): ?Authenticatable
                {
                    if ($this->user !== null) {
                        return $this->user;
                    }

                    $header = $this->request->bearerToken();
                    if (!$header) {
                        return null;
                    }

                    try {
                        $payload = $this->jwt->decode($header);

                        if ($payload->exp < time()) {
                            return null;
                        }

                        $this->user = User::where('uuid', $payload->sub)->first();
                        return $this->user;
                    } catch (\Throwable) {
                        return null;
                    }
                }

                public function check(): bool
                {
                    return $this->user() !== null;
                }

                public function guest(): bool
                {
                    return !$this->check();
                }

                public function id()
                {
                    return $this->user()?->getAuthIdentifier();
                }

                public function validate(array $credentials = []): bool
                {
                    return false;
                }

                public function hasUser(): bool
                {
                    return $this->user !== null;
                }

                public function setUser(Authenticatable $user)
                {
                    $this->user = $user;
                    return $this;
                }
            };
        });
    }
}
