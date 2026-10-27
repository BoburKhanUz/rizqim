<?php

namespace App\Providers;

use App\Events\Broadcast\OpsOrderCreated;
use App\Events\Broadcast\OpsOrderUpdated;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Jobs\WatchOrderSlaJob;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\Attention\LateOrdersSource;
use App\Services\Admin\Orders\Attention\NoCourierOrdersSource;
use App\Services\Admin\Orders\Attention\UnacceptedOrdersSource;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * P4-14: Boshqaruv markazi → Buyurtmalar. Its "Diqqat talab qiladi" sources (unaccepted beyond the SLA, late in
 * preparation or on the way, ready delivery orders with no courier) and the platform.ops events of marketplace
 * orders: ops.order.created for a new order, ops.order.updated for every status change (the SLA watcher sends the SLA
 * crossings, every minute). A failure here never fails the order change that caused it.
 */
class AdminOrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            UnacceptedOrdersSource::class,
            LateOrdersSource::class,
            NoCourierOrdersSource::class,
        ], AttentionSource::TAG);
    }

    public function boot(): void
    {
        // SLA crossings (ok → warning → late) recorded and broadcast once per order and stage
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new WatchOrderSlaJob())
                ->name('admin-order-sla-watch')
                ->everyMinute()
                ->withoutOverlapping(5);
        });

        // OrderCreated is dispatched after the checkout commits; the other two dispatch after commit themselves
        Event::listen(OrderCreated::class, function (OrderCreated $event) {
            $this->announce(fn () => AdminOrderQuery::isMarketplace($event->order) ? event(new OpsOrderCreated($event->order)) : null, $event->order->id);
        });

        $updated = function (OrderStatusUpdated|OrderCancelled $event) {
            $this->announce(fn () => AdminOrderQuery::isMarketplace($event->order) ? event(new OpsOrderUpdated($event->order)) : null, $event->order->id);
        };
        Event::listen(OrderStatusUpdated::class, $updated);
        Event::listen(OrderCancelled::class, $updated);
    }

    private function announce(callable $send, int $orderId): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning("Failed to announce order {$orderId} on platform.ops: {$e->getMessage()}");
        }
    }
}
