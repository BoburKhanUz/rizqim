<?php

/*
| P4-11: the Boshqaruv markazi's admin API, /api/v1/admin (docs/API.md "Admin API"). Required from routes/api.php inside
| its /v1 prefix group.
|
| - auth:admin: an admin session token only (App\Auth\AdminTokenGuard); store, customer, staff and terminal tokens are
|   refused here, and admin tokens are refused everywhere else.
| - admin.can:<permission>: App\Enums\AdminPermission (with or without the `admin.` prefix), the member's role
|   defaults plus the superadmin's overrides.
| - admin.recent: a step-up confirmation (POST /admin/auth/confirm) in the last 10 minutes, else 403 reauth_required.
| - admin.audit: every write is recorded in the audit log (App\Http\Middleware\RecordAdminAudit). A route's name is
|   its audit action, so every admin route is named admin.<section>.<what happened>.
|
| Stage B sections plug in at the bottom, each in its own file, inside the signed-in group.
*/

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminSearchController;
use App\Http\Controllers\Api\V1\Admin\AdminSessionController;
use App\Http\Controllers\Api\V1\Admin\AdminTeamController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('admin.audit')->group(function () {
    // No session yet: sign-in steps and accepting an invite (throttled per address; the per-account lockout is
    // AdminLoginLockout's)
    Route::prefix('auth')->name('auth.')->middleware('throttle:admin-auth')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login');
        Route::post('/2fa/setup', [AdminAuthController::class, 'setup'])->name('2fa_setup');
        Route::post('/2fa/confirm', [AdminAuthController::class, 'confirmSetup'])->name('2fa_confirm');
        Route::post('/2fa/verify', [AdminAuthController::class, 'verify'])->name('2fa_verify');
        Route::post('/invite/accept', [AdminAuthController::class, 'acceptInvite'])->name('invite_accept');
    });

    Route::middleware(['auth:admin', 'throttle:admin-api'])->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('/me', [AdminAuthController::class, 'me'])->name('me');
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logged_out');
            Route::post('/confirm', [AdminAuthController::class, 'confirm'])->name('reauthenticated')->middleware('throttle:admin-auth');
            Route::post('/2fa/recovery-codes', [AdminAuthController::class, 'regenerateRecoveryCodes'])->name('recovery_codes_regenerated')->middleware('admin.recent');
            Route::get('/sessions', [AdminSessionController::class, 'index'])->name('sessions');
            // Another session of mine needs a step-up (checked in the controller: ending this one is a log-out)
            Route::delete('/sessions/{id}', [AdminSessionController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN)->name('session_revoked');
        });

        Route::get('/me', [AdminAuthController::class, 'currentMember'])->name('me');

        Route::get('/search', [AdminSearchController::class, 'index'])->name('search')->middleware('throttle:admin-search');

        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit_logs')->middleware('admin.can:audit.view');

        // Tizim → Jamoa, Rollar
        Route::middleware('admin.can:team.manage')->group(function () {
            Route::get('/team', [AdminTeamController::class, 'index'])->name('team');
            Route::get('/team/{id}', [AdminTeamController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)->name('team_member');
            Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles');

            Route::middleware('admin.recent')->group(function () {
                Route::post('/team', [AdminTeamController::class, 'store'])->name('team.invited');
                Route::patch('/team/{id}', [AdminTeamController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->name('team.updated');
                Route::post('/team/{id}/invite', [AdminTeamController::class, 'reissueInvite'])->where('id', RowId::ROUTE_PATTERN)->name('team.invite_reissued');
                Route::post('/team/{id}/reset-2fa', [AdminTeamController::class, 'resetTwoFactor'])->where('id', RowId::ROUTE_PATTERN)->name('team.2fa_reset');
                Route::put('/roles', [AdminRoleController::class, 'update'])->name('roles.updated');
            });
        });

        // Stage B (P4-13..P4-15): one file per section, inside the auth:admin group above
        require __DIR__ . '/api_admin_today.php';       // Bugun (admin.can:today.view)
        require __DIR__ . '/api_admin_orders.php';      // Buyurtmalar (orders.*; interventions: admin.recent + reason)
        require __DIR__ . '/api_admin_monitoring.php';  // Monitoring (monitoring.*)
        require __DIR__ . '/api_admin_billing.php';     // P4-21 tariffs and store subscriptions
        require __DIR__ . '/api_admin_stores.php';      // P4-22 Ovqatxonalar (stores.*)
        require __DIR__ . '/api_admin_finance.php';     // P4-23 Moliya (finance.*, payouts.approve)
        require __DIR__ . '/api_admin_pos.php';         // P4-24 POS va obunalar (invoices, devices, shifts, fiscal, sync errors)
        require __DIR__ . '/api_admin_customers.php';   // P4-31 Mijozlar va yordam (users.view, support.*)
        require __DIR__ . '/api_admin_metrics.php';     // P4-31 Ko'rsatkichlar (MRR, churn, funnel) and Excel exports
        require __DIR__ . '/api_admin_marketing.php';   // P4-31 Marketing (marketing.*: banners, promo codes, categories)
        require __DIR__ . '/api_admin_system.php';      // P4-31 Tizim (system.manage: settings, payment providers, regions, feature flags)
        require __DIR__ . '/api_admin_integrations.php'; // P4-31 Integratsiyalar (integrations.*: connections, journal, stuck orders)
    });
});
