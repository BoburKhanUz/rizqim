<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderNotificationToTelegram
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected \App\Services\Notifications\TelegramNotificationService $telegramService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        $this->telegramService->sendOrderCreated($event->order);
    }
}
