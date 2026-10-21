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

// --- P1-23: monthly subscription invoices (docs/BILLING.md); daily so a missed 1st is caught up, idempotent ---
Schedule::job(new \App\Jobs\BillSubscriptionsJob)->dailyAt('00:15')->timezone(config('billing.timezone', 'Asia/Tashkent'))->withoutOverlapping(60);

Artisan::command('billing:run', function (\App\Services\Finance\SubscriptionService $subscriptions) {
    $this->info($subscriptions->run() . ' invoice(s) issued');
})->purpose('Bill ended subscription periods and update invoice / subscription statuses (safe to run again)');
// --- end P1-23 ---

// P1-21: stop-list entries whose `until` passed are removed (and the menus told) every minute; Idempotency-Key
// responses are kept for 24 hours, then pruned
Artisan::command('menu:expire-stop-list', function (\App\Services\Catalog\StopListService $stopList) {
    $this->info($stopList->expire() . ' expired stop-list entries removed');
})->purpose('Remove stop-list entries whose until time passed and broadcast the change');
Schedule::command('menu:expire-stop-list')->everyMinute()->withoutOverlapping(5);
// P3 integration fix: every prunable model of app/Models (MassPrunable / Prunable: Idempotency-Key responses, expired
// manager approvals of the POS, pos_approvals) is pruned daily; a new prunable model is picked up without a change here
Schedule::command('model:prune')->daily()->withoutOverlapping(60);

// P3 integration fix (R20): the owner hears once about each POS hall order open longer than its branch's
// pos_settings.alerts.open_room_hours (App\Domain\Pos\Orders\PosOrderAlerts)
Artisan::command('pos:alert-long-open', function (\App\Domain\Pos\Orders\PosOrderAlerts $alerts) {
    $this->info($alerts->checkLongOpen() . ' long-open order alert(s) sent');
})->purpose('Alert the owner about POS hall orders open longer than the branch threshold (once per order)');
Schedule::command('pos:alert-long-open')->everyTenMinutes()->withoutOverlapping(10);
