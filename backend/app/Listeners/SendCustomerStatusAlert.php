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

        $this->telegramService->sendOrderStatusChanged($event->order);
    }
}
