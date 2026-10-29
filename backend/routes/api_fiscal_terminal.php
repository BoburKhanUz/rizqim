<?php

/*
| P6-16 (D5): the card flow of each branch for the partner web's fiscal page — how it takes cards, the installation
| checklist, smart kassa tills — and the bank terminal day reports compared with the Z (docs/API.md "Fiskal terminal
| oqimlari"). Required from routes/api.php inside its /v1 prefix group. Never from a staff PIN session; behind the
| `terminal_card_flow` module (the controller checks a record's own branch). The till's side is in api_pos_billing.php.
*/

use App\Http\Controllers\Api\V1\Fiscal\TerminalFlowController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api', 'not-staff-session', 'pos.module:terminal_card_flow'])->group(function () {
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/owner/fiscal/terminal-flow', [TerminalFlowController::class, 'index']);
        Route::put('/owner/branches/{branch}/terminal-flow', [TerminalFlowController::class, 'update'])->where('branch', RowId::ROUTE_PATTERN);
        Route::put('/owner/fiscal/devices/{uuid}', [TerminalFlowController::class, 'updateDevice'])->whereUuid('uuid');
    });

    Route::get('/owner/fiscal/bank-reports', [TerminalFlowController::class, 'reports'])->middleware('permission:finance.view,reports.view');
    Route::put('/owner/fiscal/bank-reports/{uuid}', [TerminalFlowController::class, 'enter'])->whereUuid('uuid')->middleware('permission:finance.manage,pos.close_shift');
});
