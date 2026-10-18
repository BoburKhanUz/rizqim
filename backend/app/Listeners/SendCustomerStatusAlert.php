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
        $this->telegramService->sendOrderStatusChanged($event->order);
    }
}
