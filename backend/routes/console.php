<?php

use App\Jobs\CancelUnpaidOnlineOrdersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Unpaid online orders give back stock and promo use after config('orders.unpaid_online_ttl_minutes')
Schedule::job(new CancelUnpaidOnlineOrdersJob)->everyMinute()->withoutOverlapping(10);
