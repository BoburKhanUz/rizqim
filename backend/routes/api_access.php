<?php

/*
| P1-12: refresh tokens, devices, terminal pairing, staff PIN sessions, permissions and the audit log (docs/AUTH.md).
| Required from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Auth\PermissionController;
use App\Http\Controllers\Api\V1\Auth\PhoneLoginController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Device\DeviceController;
use App\Http\Controllers\Api\V1\Device\StoreDeviceController;
use App\Http\Controllers\Api\V1\Device\TerminalController;
use App\Http\Controllers\Api\V1\User\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Store\AuditLogController;
use App\Http\Controllers\Api\V1\Store\StaffAccessController;
use Illuminate\Support\Facades\Route;

// No token yet: refresh, phone sign-in, terminal pairing
Route::post('/auth/refresh', [TokenController::class, 'refresh'])->middleware('throttle:auth-refresh');
Route::post('/auth/phone/send-code', [PhoneLoginController::class, 'sendCode'])->middleware('throttle:otp-send');
Route::post('/auth/phone/verify', [PhoneLoginController::class, 'verify'])->middleware('throttle:otp-verify');
Route::post('/devices/pair', [TerminalController::class, 'pair'])->middleware('throttle:device-pair');

// A signed-in user (Mini App, apps, staff PIN session)
Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::post('/auth/logout', [TokenController::class, 'logout'])->middleware('throttle:auth-logout');
    Route::get('/me/permissions', [PermissionController::class, 'show']);

    // Account-wide and administrative actions refuse a staff PIN session token: a terminal is for order work only
    Route::middleware('not-staff-session')->group(function () {
        Route::post('/auth/logout-all', [TokenController::class, 'logoutAll'])->middleware('throttle:auth-logout');

        // P1-22: which messages (order updates, promotions, new-order alerts…) come over Telegram and push
        Route::get('/me/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/me/notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::middleware('throttle:devices')->group(function () {
            Route::get('/devices', [DeviceController::class, 'index']);
            Route::post('/devices', [DeviceController::class, 'store']);
            Route::delete('/devices/{uuid}', [DeviceController::class, 'destroy'])->whereUuid('uuid');
            // P1-22: the FCM token of this install (docs/PUSH.md)
            Route::put('/devices/{uuid}/push-token', [DeviceController::class, 'updatePushToken'])->whereUuid('uuid');
            Route::delete('/devices/{uuid}/push-token', [DeviceController::class, 'clearPushToken'])->whereUuid('uuid');

            Route::get('/owner/devices', [StoreDeviceController::class, 'index'])->middleware('permission:devices.view');
            Route::patch('/owner/devices/{uuid}', [StoreDeviceController::class, 'update'])->whereUuid('uuid')->middleware('permission:devices.manage');
            Route::post('/owner/devices/{uuid}/revoke', [StoreDeviceController::class, 'revoke'])->whereUuid('uuid')->middleware('permission:devices.manage');
        });
        Route::post('/owner/devices/pairing-codes', [StoreDeviceController::class, 'createPairingCode'])
            ->middleware(['permission:devices.manage', 'throttle:pairing-code']);

        Route::middleware('throttle:staff-admin')->group(function () {
            Route::put('/owner/staff/{id}/pin', [StaffAccessController::class, 'setPin'])->whereNumber('id')->middleware('permission:staff.pin');
            Route::delete('/owner/staff/{id}/pin', [StaffAccessController::class, 'resetPin'])->whereNumber('id')->middleware('permission:staff.pin');
            Route::put('/owner/staff/{id}/role', [StaffAccessController::class, 'updateRole'])->whereNumber('id')->middleware('permission:staff.manage');
            Route::get('/owner/permissions', [StaffAccessController::class, 'permissions'])->middleware('permission:permissions.manage');
            Route::put('/owner/permissions', [StaffAccessController::class, 'updatePermission'])->middleware('permission:permissions.manage');
        });

        Route::get('/owner/audit-logs', [AuditLogController::class, 'index'])->middleware(['permission:audit.view', 'throttle:audit-logs']);
    });
});

// A paired terminal's own token
Route::middleware(['auth:device', 'throttle:terminal'])->group(function () {
    Route::get('/terminal', [TerminalController::class, 'show']);
    Route::get('/terminal/staff', [TerminalController::class, 'staff']);
    Route::post('/devices/{uuid}/staff-session', [TerminalController::class, 'startStaffSession'])->whereUuid('uuid')->middleware('throttle:staff-session');
    Route::delete('/devices/{uuid}/staff-session', [TerminalController::class, 'endStaffSession'])->whereUuid('uuid');
});
