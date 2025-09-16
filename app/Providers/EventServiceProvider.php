<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Listeners\LogUserLoginActivity;
use App\Listeners\LogUserLogoutActivity;
use App\Listeners\LogUserSession;
use Illuminate\Container\Attributes\Log;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\SiteAccessFile;
use App\Observers\SiteAccessFileObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            LogUserLoginActivity::class,
            LogUserSession::class,
        ],
        Logout::class => [
            LogUserLogoutActivity::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        SiteAccessFile::observe(SiteAccessFileObserver::class);
    }
}
