<?php

namespace App\Providers;

use App\Models\AccessRequest;
use App\Policies\AccessRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        AccessRequest::class => AccessRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define additional gates for access requests
        Gate::define('view-access-requests', function ($user) {
            return $user->role === 'admin' || $user->external_role === 'admin';
        });

        Gate::define('manage-access-requests', function ($user) {
            return $user->role === 'admin' || $user->external_role === 'admin';
        });

        Gate::define('create-access-request', function ($user) {
            // All authenticated users can create access requests
            return true;
        });

        Gate::define('view-own-access-requests', function ($user) {
            // Users can always view their own requests
            return true;
        });
    }
}
