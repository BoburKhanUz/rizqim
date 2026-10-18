<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Notifications\TelegramNotificationService;

class SendOrderCancelledAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected TelegramNotificationService $telegramService
    ) {}

    public function handle(OrderCancelled $event): void
    {
        // We can reuse the status changed method since it handles canceled status too.
        $this->telegramService->sendOrderStatusChanged($event->order);
    }
}
