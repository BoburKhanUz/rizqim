<?php

use Illuminate\Support\Facades\Route;

/*
 * P4-33: the backend serves no web panel any more (the Filament panels /admin and /superadmin are gone): the RIZQIM team
 * works in the control center (platform/admin), store owners and staff in the partner web panel (partner/web). Whoever
 * opens the backend's own address lands on the partner panel (services.partner_web.url, PARTNER_WEB_URL); without it,
 * a plain 404. Health checks: /up and /health.
 */
Route::get('/', function () {
    $partnerWeb = trim((string) config('services.partner_web.url'));
    abort_if($partnerWeb === '', 404);

    return redirect()->away($partnerWeb);
});
