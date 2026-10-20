<?php

use App\Jobs\CancelUnpaidOnlineOrdersJob;
use App\Jobs\RetryFailedSettlementsJob;
use App\Models\Order;
use App\Services\Finance\SettlementRecoveryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Unpaid online orders give back stock and promo use after config('orders.unpaid_online_ttl_minutes')
Schedule::job(new CancelUnpaidOnlineOrdersJob)->everyMinute()->withoutOverlapping(10);

// Delivered orders whose ledger booking failed are booked again until config('orders.settlement_retry_max_attempts')
Schedule::job(new RetryFailedSettlementsJob)->everyTenMinutes()->withoutOverlapping(30);

// After the automatic retries gave up and the cause is fixed: books the given orders (ids or uuids) once more
Artisan::command('orders:retry-settlements {orders* : ids or uuids of delivered orders whose booking failed}', function (SettlementRecoveryService $settlements) {
    foreach ($this->argument('orders') as $key) {
        $order = Order::where(ctype_digit((string) $key) ? 'id' : 'uuid', $key)->first();

        if (!$order || $order->settlement_failed_at === null) {
            $this->warn("{$key}: no failed ledger booking");
        } elseif ($settlements->retry($order->id)) {
            $this->info("{$key}: booked");
        } else {
            $this->error("{$key}: " . ($order->fresh()->settlement_error ?? 'not booked (no longer delivered)'));
        }
    }
})->purpose('Book again delivered orders whose ledger booking failed');
