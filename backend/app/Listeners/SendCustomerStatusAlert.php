<?php

namespace App\Listeners;

use App\Events\OrderStatusUpdated;
use App\Models\OrderStatusHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Notifications\TelegramNotificationService;

class SendCustomerStatusAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected TelegramNotificationService $telegramService
    ) {}

    /**
     * Decided when the event is dispatched, right after its transaction commits (OrderStatusUpdated dispatches after
     * commit), before the alert is queued.
     */
    public function shouldQueue(OrderStatusUpdated $event): bool
    {
        // P2-33: a kitchen correction (recall from ready, ready again) is not news to the customer
        if ($event->quiet) {
            return false;
        }

        // P3-12: a POS order has no customer to tell (its user is the staff member who opened it)
        if ($event->order->channel->isPos()) {
            return false;
        }

        // P6-13, review P6 C#L5: two moves in one transaction (accepted -> preparing -> ready at the till): the customer
        // hears the last one once. At this moment only that transaction's rows are newer than the one this event
        // announced, so a later move by the worker's time (the queue running late) never drops the alert
        return $event->historyId === null || !OrderStatusHistory::where('order_id', $event->order->id)
            ->where('id', '>', $event->historyId)
            ->exists();
    }

    public function handle(OrderStatusUpdated $event): void
    {
        $order = $event->order;
        if ($event->status !== null && $order->status->value !== $event->status) {
            // The queue ran late and the order has moved on since: the customer still hears the step this event
            // announced (the next one has its own alert); a copy, never saved
            $order = clone $order;
            $order->setAttribute('status', $event->status);
        }

        $this->telegramService->sendOrderStatusChanged($order);
    }
}
