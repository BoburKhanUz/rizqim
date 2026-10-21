<?php

namespace App\Providers;

use App\Domain\Pos\Accounts\CustomerAccountService;
use App\Domain\Pos\Booking\BookingAdvanceLedger;
use App\Domain\Pos\Booking\BookingService;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Events\OrderStatusUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * P3-17: bookings, deposits and customer accounts (nasiya). Binds the two ledgers billing (P3-18) uses and completes a
 * booking when its order ends.
 */
class PosBookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdvanceLedger::class, BookingAdvanceLedger::class);
        $this->app->bind(CustomerAccountLedger::class, CustomerAccountService::class);
    }

    public function boot(): void
    {
        // The booking's order was closed or canceled: the booking is completed (after commit, like the event)
        Event::listen(OrderStatusUpdated::class, function (OrderStatusUpdated $event) {
            try {
                app(BookingService::class)->completeForOrder($event->order);
            } catch (Throwable $e) {
                Log::error("Failed to complete the booking of order {$event->order->id}: {$e->getMessage()}");
            }
        });
    }
}
