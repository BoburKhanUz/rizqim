<?php

use App\Http\Controllers\Api\V1\Admin\AdminComplaintController;
use App\Http\Controllers\Api\V1\Admin\AdminCustomerActionController;
use App\Http\Controllers\Api\V1\Admin\AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\AdminSupportCannedReplyController;
use App\Http\Controllers\Api\V1\Admin\AdminSupportSuspiciousController;
use Illuminate\Support\Facades\Route;

// P4-31: Mijozlar va yordam. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names
// admin.*). Users: admin.users.view reads; block, unblock and closing sessions need admin.support.manage, a step-up
// (admin.recent) and a `reason`. Complaints, tayyor javoblar and Shubhali holatlar: admin.support.view reads,
// admin.support.manage changes (a reply to the customer: at most 20 a minute per member, admin-complaint-reply). RIZQIM
// team accounts are never listed or acted on here. The route name is the audit action.

$id = '[1-9][0-9]{0,17}';

Route::middleware('admin.can:users.view')->group(function () use ($id) {
    Route::get('/users', [AdminCustomerController::class, 'index'])->name('users');
    Route::get('/users/{id}', [AdminCustomerController::class, 'show'])->where('id', $id)->name('users.user');
});

Route::middleware(['admin.can:support.manage', 'admin.recent'])->group(function () use ($id) {
    Route::post('/users/{id}/block', [AdminCustomerActionController::class, 'block'])->where('id', $id)->name('users.blocked');
    Route::post('/users/{id}/unblock', [AdminCustomerActionController::class, 'unblock'])->where('id', $id)->name('users.unblocked');
    Route::post('/users/{id}/close-sessions', [AdminCustomerActionController::class, 'closeSessions'])->where('id', $id)->name('users.sessions_closed');
});

Route::middleware('admin.can:support.view')->group(function () {
    Route::get('/complaints', [AdminComplaintController::class, 'index'])->name('support.complaints');
    Route::get('/complaints/{uuid}', [AdminComplaintController::class, 'show'])->whereUuid('uuid')->name('support.complaint');
    Route::get('/support/canned-replies', [AdminSupportCannedReplyController::class, 'index'])->name('support.canned_replies');
    Route::get('/support/suspicious', [AdminSupportSuspiciousController::class, 'index'])->name('support.suspicious');
});

Route::middleware('admin.can:support.manage')->group(function () use ($id) {
    Route::patch('/complaints/{uuid}', [AdminComplaintController::class, 'update'])->whereUuid('uuid')->name('support.complaint_updated');
    Route::post('/complaints/{uuid}/reply', [AdminComplaintController::class, 'reply'])->whereUuid('uuid')->middleware('throttle:admin-complaint-reply')->name('support.complaint_replied');
    Route::post('/complaints/{uuid}/resolve', [AdminComplaintController::class, 'resolve'])->whereUuid('uuid')->name('support.complaint_resolved');

    Route::post('/support/canned-replies', [AdminSupportCannedReplyController::class, 'store'])->name('support.canned_reply_created');
    Route::patch('/support/canned-replies/{id}', [AdminSupportCannedReplyController::class, 'update'])->where('id', $id)->name('support.canned_reply_updated');
    Route::delete('/support/canned-replies/{id}', [AdminSupportCannedReplyController::class, 'destroy'])->where('id', $id)->name('support.canned_reply_deleted');
});
