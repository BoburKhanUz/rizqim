<?php

namespace App\Providers;

use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Domain\Pos\Hall\BookingTableReservations;
use App\Domain\Pos\Hall\TableReservations;
use App\Domain\Pos\Orders\PosOrderService;
use Illuminate\Support\ServiceProvider;

/**
 * P3-12: POS hall orders. PosOrderOpener (used by the POS endpoints and by a booking's "Keldi", P3-17) is
 * PosOrderService; the floor map reads upcoming bookings through TableReservations (bindIf: P3-17 may bind its own).
 */
class PosOrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PosOrderOpener::class, PosOrderService::class);
        $this->app->bindIf(TableReservations::class, BookingTableReservations::class);
    }
}
