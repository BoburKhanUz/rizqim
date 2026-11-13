<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\Setup\PosBootstrap;
use App\Events\Broadcast\BillChangedBroadcast;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Models\PosQrPayment;
use App\Models\Order;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * P6-41/P6-42 "QR to'lov kassada": the bootstrap's `qr_payments` section, the cancel of a QR whose bill changed
 * (order.bill_changed, order.items_changed; after commit) and the minute job that expires QRs (pos:expire-qr-payments).
 */
class PosQrPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([QrPaymentsBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        // A bill made again, split, voided or paid in another way, or an order changed under its bill (a discount,
        // a line): its pending QR no longer fits
        Event::listen([BillChangedBroadcast::class, OrderItemsChangedBroadcast::class], function (BillChangedBroadcast|OrderItemsChangedBroadcast $event) {
            if (!PosQrPayment::where('status', QrPaymentStatus::PENDING->value)->whereHas('order', fn ($query) => $query->where('uuid', $event->uuid))->exists()) {
                return;
            }
            $order = Order::where('uuid', $event->uuid)->first();
            if ($order !== null) {
                app(QrPaymentService::class)->cancelStale($order);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireQrPaymentsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pos:expire-qr-payments')->everyMinute()->withoutOverlapping(5)->name('pos-expire-qr-payments');
        });
    }
}
