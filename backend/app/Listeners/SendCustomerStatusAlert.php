<?php

namespace App\Listeners;

use App\Events\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Notifications\TelegramNotificationService;

class SendCustomerStatusAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected TelegramNotificationService $telegramService
    ) {}

    public function handle(OrderStatusUpdated $event): void
    {
        // P2-33: a kitchen correction (recall from ready, ready again) is not news to the customer
        if ($event->quiet) {
            return;
        }

        // P6-13: two moves in one transaction (accepted -> preparing -> ready at the till) reach the listener after
        // the commit, when the order already shows the last one: the customer hears that one once, not twice
        if ($event->status !== null && $event->order->status->value !== $event->status) {
            return;
        }

        // P3-12: a POS order has no customer to tell (its user is the staff member who opened it)
        if ($event->order->channel->isPos()) {
            return;
        }

        $this->telegramService->sendOrderStatusChanged($event->order);
    }
}
