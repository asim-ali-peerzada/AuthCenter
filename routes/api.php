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
use App\Http\Controllers\Admin\UserApprovalController;
use App\Http\Controllers\External\ExternalDataController;
use App\Http\Controllers\Auth\TwoFactorAuthController;
use App\Http\Controllers\Auth\OAuth2Controller;
use App\Http\Controllers\upload\SiteUploadController;
use App\Http\Controllers\AccessRequest\AccessRequestController;
use App\Http\Controllers\DomainStatus\DomainStatusController;

Route::prefix('auth')->group(function () {

    /* ── public endpoints ── */
    Route::post('signup/initiate-2fa', [SignupController::class, 'initiate2FA'])->name('auth.signup.initiate-2fa');
    Route::post('signup/complete-2fa', [SignupController::class, 'completeSignup'])->name('auth.signup.complete-2fa');

    Route::post('signup', [SignupController::class, 'store'])->name('auth.signup');
    Route::post('login',  [LoginController::class,  'login']);
    Route::post('/login/verify-2fa', [LoginController::class, 'verify2FA']);
    Route::post('/generate-2fa-secret', [LoginController::class, 'generate2FASecret']);


    /* JWT redemption (rate‑limited) */
    Route::post('token/exchange', [TokenExchangeController::class, 'tokenExchange'])
        ->middleware('throttle:10,1');

    // sites/hubs data
    Route::get('sites', [SiteUploadController::class, 'index']);
    // specific site details
    Route::post('site-details', [SiteUploadController::class, 'getSiteDetails']);

    // Update hub access details
    Route::put('sites/{id}/access-details', [SiteUploadController::class, 'updateAccessDetails']);

    /* ── protected by jwt guard + blacklist check ── */
    Route::middleware(['auth:jwt', 'jwt.blacklist'])->group(function () {

        // Domain list for “choose‑domain” UI
        Route::get('domains', [DomainController::class, 'index']);

        // Logout: revoke JWT immediately (blacklist JTI)
        Route::post('logout', [LogoutController::class, 'logout']);

        Route::get('validate', [LoginController::class, 'validate']);

        // Access Request for user
        Route::post('/access-requests', [AccessRequestController::class, 'store'])
            ->middleware('access.request:create')
            ->name('access-requests.store');
        Route::get('/access-requests', [AccessRequestController::class, 'index'])
            ->middleware('access.request:view')
            ->name('access-requests.index');
        Route::delete('/access-requests/{accessRequest}', [AccessRequestController::class, 'destroy'])
            ->name('access-requests.destroy');
        Route::patch('/access-requests/{accessRequestId}/resubmit', [AccessRequestController::class, 'resubmit'])
            ->name('access-requests.resubmit');

        // Activation Request for user
        Route::post('/activation-requests', [AccessRequestController::class, 'storeActivation'])
            ->middleware('access.request:create')
            ->name('activation-requests.store');

        // Domain status
        Route::post('status/domains', [DomainStatusController::class, 'jobFinderStatus']);
    });
});

Route::prefix('admin')
    ->middleware(['auth:jwt', 'jwt.blacklist', 'debug.auth', 'is.admin'])
    ->group(function () {

        // ── Users ───────────────────────────
        Route::get('users',              [UserAdminController::class, 'index']);
        Route::get('users/{uuid}',              [UserAdminController::class, 'show']);
        Route::post('users',             [UserAdminController::class, 'store']);
        Route::put('users/{user}',       [UserAdminController::class, 'update']);
        Route::post('users/{user}/image', [UserAdminController::class, 'updateImage']);
        Route::patch('users/status', [UserAdminController::class, 'toggleStatus']);
        Route::delete('users/{user}', [UserAdminController::class, 'destroy']);
        Route::post('users/{user}/reset-password', [UserAdminController::class, 'resetPassword']);
        Route::post('users/{user}/change-password', [UserAdminController::class, 'changePassword']);
        Route::get('search/users', [UserAdminController::class, 'search']);
        Route::get('filtered/users/', [UserAdminController::class, 'filtered']);
        Route::post('unlock/users/', [UserAdminController::class, 'unlockUser']);
        Route::get('dashboard-summary', [DashboardController::class, 'summary']);
        Route::get('dashboard', [DashboardController::class, 'site_access_dashboard']);
        Route::get('/user-activities', [UserActivityController::class, 'index']);
        Route::get('/export-summary-activities', [ExportController::class, 'exportSummaryWithActivities']);

        // ── Domains ─────────────────────────
        Route::get('domains',            [DomainAdminController::class, 'index']);
        Route::post('domains',           [DomainAdminController::class, 'store']);
        Route::put('domains/{domain}',   [DomainAdminController::class, 'update']);
        Route::get('detail/{domain}', [DomainAdminController::class, 'show']);
        Route::get('users/{uuid}/domains', [DomainAdminController::class, 'user_domains']);
        Route::delete('domains/{domain}', [DomainAdminController::class, 'destroy']);

        // ── Access (user ↔ domain) ──────────
        Route::post('users/{user}/domains/{domain}',    [AccessAdminController::class, 'grant']);
        Route::delete('users/{user}/domains/{domain}',  [AccessAdminController::class, 'revoke']);

        // Pending access requests routes
        Route::get('pending-users', [UserApprovalController::class, 'index'])->name('admin.pending-users');
        Route::post('approve-users', [UserApprovalController::class, 'updateApprovalStatus'])->name('admin.approve-users');

        // Enforce 2FA login setting
        Route::post('enforce-login', [TwoFactorAuthController::class, 'updateEnforce2FALogin']);
        Route::get('enforce-login', [TwoFactorAuthController::class, 'getEnforce2FALogin']);

        // Upload Excel
        Route::post('sites/upload', [SiteUploadController::class, 'uploadExcel']);

        // File processing status polling endpoint
        Route::get('sites/files/{fileId}/status', [SiteUploadController::class, 'getFileStatus']);

        // Get all uploaded files
        Route::get('sites/files', [SiteUploadController::class, 'getUploadedFiles']);

        // Delete uploaded file
        Route::delete('sites/files/{fileId}', [SiteUploadController::class, 'deleteFile']);

        // Access Request for Admin
        Route::get('/access-requests/search', [AccessRequestController::class, 'search'])
            ->name('admin.access-requests.search');
        Route::post('/access-requests/{accessRequestId}/approve', [AccessRequestController::class, 'approve'])
            ->middleware('access.request:manage')
            ->name('access-requests.approve');
        Route::post('/access-requests/{accessRequestId}/reject', [AccessRequestController::class, 'reject'])
            ->middleware('access.request:manage')
            ->name('access-requests.reject');
        Route::patch('/access-requests/{accessRequestId}/external-status', [AccessRequestController::class, 'updateExternalStatus'])
            ->middleware('access.request:manage')
            ->name('access-requests.update-external-status');
    });

Route::post('/oauth/token', [OAuth2Controller::class, 'token'])->name('oauth.token');

// OAuth2 token validation for external projects
Route::get('/oauth/validate', [OAuth2Controller::class, 'validate'])->name('oauth.validate');

// Internal sync endpoint & token refresh
Route::post('/internal-sync-user', [InternalSyncController::class, 'store']);
// Token refresh
Route::post('/token/refresh', [RefreshTokenController::class, 'refresh']);

// Routes for external services to fetch data
Route::middleware('auth.sync.secret')->group(function () {
    Route::get('/external_small_cell', [ExternalDataController::class, 'getSiteData'])->name('external.site-names');
    Route::post('/user-activation-status', [AccessRequestController::class, 'updateUserActivationStatus'])->name('webhook.user-activation-status');
});
