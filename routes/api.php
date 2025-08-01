<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TokenExchangeController;
use App\Http\Controllers\Auth\DomainController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\DomainAdminController;
use App\Http\Controllers\Admin\AccessAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserActivityController;
use App\Http\Controllers\Sync\InternalSyncController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Export\ExportController;

Route::prefix('auth')->group(function () {

    /* ── public endpoints ── */
    Route::post('signup', [SignupController::class, 'store'])->name('auth.signup');
    Route::post('login',  [LoginController::class,  'login']);

    /* JWT redemption (rate‑limited) */
    Route::post('token/exchange', [TokenExchangeController::class, 'tokenExchange'])
         ->middleware('throttle:10,1');

    /* ── protected by jwt guard + blacklist check ── */
    Route::middleware(['auth:jwt', 'jwt.blacklist'])->group(function () {

        // 1. Domain list for “choose‑domain” UI
        Route::get('domains', [DomainController::class, 'index']);

        // 2. Logout: revoke JWT immediately (blacklist JTI)
        Route::post('logout', [LogoutController::class, 'logout']);

        Route::get('validate', function (Request $request) {
            $user = $request->user();

            return response()->json([
                'user' => [
                    'uuid'   => $user->uuid,
                    'email'  => $user->email,
                    'full_name'   => $user->first_name,
                    'last_name'   => $user->last_name,
                    'status' => $user->status,
                    'image_full_url' => $user->image_url
                        ? asset('storage/' . $user->image_url)
                        : null,
                ]
            ]);
        });
    });
});

Route::prefix('admin')
      ->middleware(['auth:jwt', 'jwt.blacklist', 'is.admin'])
      ->group(function () {

    // ── Users ───────────────────────────
    Route::get('users',              [UserAdminController::class, 'index']);
    Route::get('users/{uuid}',              [UserAdminController::class, 'show']);
    Route::post('users',             [UserAdminController::class, 'store']);
    Route::put('users/{user}',       [UserAdminController::class, 'update']);
    Route::patch('users/status', [UserAdminController::class, 'toggleStatus']);
    Route::delete('users/{user}', [UserAdminController::class, 'destroy']);
    Route::post('users/{user}/reset-password', [UserAdminController::class, 'resetPassword']);
    Route::post('users/{user}/change-password', [UserAdminController::class, 'changePassword']);
    Route::get('search/users', [UserAdminController::class, 'search']);
    Route::get('filtered/users/', [UserAdminController::class, 'filtered']);
    Route::get('dashboard-summary', [DashboardController::class, 'summary']);
    Route::get('/user-activities', [UserActivityController::class, 'index']);
    Route::get('/export-summary-activities', [ExportController::class, 'exportSummaryWithActivities']);

    // ── Domains ─────────────────────────
    Route::get('domains',            [DomainAdminController::class, 'index']);
    Route::post('domains',           [DomainAdminController::class, 'store']);
    Route::put('domains/{domain}',   [DomainAdminController::class, 'update']);
    Route::get('detail/{domain}', [DomainAdminController::class, 'show']);
    Route::get('users/{uuid}/domains', [DomainAdminController::class, 'user_domains']);
    Route::delete('domains/{domain}',[DomainAdminController::class, 'destroy']);

    // ── Access (user ↔ domain) ──────────
    Route::post('users/{user}/domains/{domain}',    [AccessAdminController::class, 'grant']);
    Route::delete('users/{user}/domains/{domain}',  [AccessAdminController::class, 'revoke']);
});

// Internal sync endpoint & token refresh
Route::post('/internal-sync-user', [InternalSyncController::class, 'store']);
Route::post('/token/refresh', [RefreshTokenController::class, 'refresh']);
