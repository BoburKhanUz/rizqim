<?php

use App\Http\Controllers\Api\V1\Admin\AdminTodayController;
use Illuminate\Support\Facades\Route;

// P4-13: Bugun. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*).
// Read-only: today's numbers (aggregate tables) and "Diqqat talab qiladi" (every section's attention sources).
Route::prefix('today')->name('today.')->middleware('admin.can:today.view')->group(function () {
    Route::get('/', [AdminTodayController::class, 'index'])->name('summary');
    Route::get('/attention', [AdminTodayController::class, 'attention'])->name('attention');
});
