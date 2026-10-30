<?php

/*
| P6-32 (wave "Fast food va qahvaxona"): the owner's sticker and label settings of a branch (the `labels` module). The
| till reads the same settings from GET /pos/bootstrap `labels`. Required from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Management\BranchLabelSettingsController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api', 'not-staff-session'])->group(function () {
    Route::get('/owner/branches/{id}/label-settings', [BranchLabelSettingsController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)
        ->middleware(['permission:settings.manage', 'pos.module:labels']);
    Route::put('/owner/branches/{id}/label-settings', [BranchLabelSettingsController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)
        ->middleware(['permission:settings.manage', 'pos.module:labels', 'billing.unlocked']);
});
