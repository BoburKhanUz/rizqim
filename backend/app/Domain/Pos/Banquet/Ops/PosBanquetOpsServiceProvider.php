<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Booking\BookingAdvanceLedger;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Events\OrderStatusUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * P6-54: billing's advance ledger also serves banquet event orders (CompositeAdvanceLedger: the booking deposits
 * ledger extended), and an event's order closing (its final bill paid and receipted) settles the event.
 */
class PosBanquetOpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The booking deposits ledger (PosBookingServiceProvider) becomes the one that also serves event orders; another
        // ledger bound in its place (a test's fake) is left as it is
        $this->app->extend(AdvanceLedger::class, fn (AdvanceLedger $ledger, $app) => $ledger::class === BookingAdvanceLedger::class
            ? new CompositeAdvanceLedger($app->make(BanquetPrepaymentLedger::class))
            : $ledger);
    }

    public function boot(): void
    {
        // After the commit, like the event: an order that closed and belongs to an in-progress event settles it
        Event::listen(OrderStatusUpdated::class, function (OrderStatusUpdated $event) {
            try {
                app(BanquetSettlementService::class)->markSettled($event->order->refresh());
            } catch (Throwable $e) {
                Log::error("Failed to settle the banquet event of order {$event->order->id}: {$e->getMessage()}");
            }
        });
    }
}
