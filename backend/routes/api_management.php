<?php

/*
| P2-12: management endpoints for the partner web panel (docs/API.md, "Boshqaruv API xaritasi"): delivery zones,
| branches and working hours, staff, sales reports with CSV export, and the store's money history. Required from
| routes/api.php inside its /v1 prefix group. Store and branch scoping live in the controllers (ManagementController).
*/

use App\Http\Controllers\Api\V1\Management\BranchController;
use App\Http\Controllers\Api\V1\Management\DeliveryZoneManageController;
use App\Http\Controllers\Api\V1\Management\FinanceHistoryController;
use App\Http\Controllers\Api\V1\Management\ReportController;
use App\Http\Controllers\Api\V1\Management\StaffController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    // Sales reports (the same figures as /owner/analytics); a CSV download is a heavier read, so it has its own limit
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/owner/reports/sales', [ReportController::class, 'sales']);
        Route::get('/owner/reports/top-products', [ReportController::class, 'topProducts']);
        Route::get('/owner/reports/export', [ReportController::class, 'export'])->middleware('throttle:report-csv');
    });

    // Branch list for anyone who works with orders, the menu, staff or reports; a branch-bound caller sees their own
    Route::get('/owner/branches', [BranchController::class, 'index'])
        ->middleware('permission:branches.manage,settings.manage,orders.view,menu.view,staff.view,reports.view');
    Route::get('/owner/branches/{id}', [BranchController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)
        ->middleware('permission:branches.manage,settings.manage,orders.view,menu.view,staff.view,reports.view');

    // Settings, staff and money: never from a staff PIN session on a terminal (docs/AUTH.md)
    Route::middleware('not-staff-session')->group(function () {
        Route::post('/owner/branches', [BranchController::class, 'store'])->middleware('permission:settings.manage');
        Route::patch('/owner/branches/{id}', [BranchController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:branches.manage');
        Route::delete('/owner/branches/{id}', [BranchController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:settings.manage');

        Route::middleware('permission:zones.manage')->group(function () {
            Route::get('/owner/delivery-zones', [DeliveryZoneManageController::class, 'index']);
            Route::post('/owner/delivery-zones', [DeliveryZoneManageController::class, 'store']);
            Route::get('/owner/delivery-zones/{id}', [DeliveryZoneManageController::class, 'show'])->where('id', RowId::ROUTE_PATTERN);
            Route::patch('/owner/delivery-zones/{id}', [DeliveryZoneManageController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
            Route::delete('/owner/delivery-zones/{id}', [DeliveryZoneManageController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);
        });

        Route::middleware('permission:staff.view')->group(function () {
            Route::get('/owner/staff', [StaffController::class, 'index']);
            // Who may add or edit whom: StaffAccountService (the panel's employee screen rules)
            Route::post('/owner/staff', [StaffController::class, 'store'])->middleware('throttle:staff-admin');
            Route::patch('/owner/staff/{id}', [StaffController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware('throttle:staff-admin');
        });

        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/owner/finance/ledger', [FinanceHistoryController::class, 'ledger']);
            Route::get('/owner/finance/wallet', [FinanceHistoryController::class, 'wallet']);
            Route::get('/owner/payouts', [FinanceHistoryController::class, 'payouts']);
            Route::get('/owner/payments', [FinanceHistoryController::class, 'payments']);
        });
    });
});
