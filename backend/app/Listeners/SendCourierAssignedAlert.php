<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Services\Notifications\OrderAlertService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one place a courier (or the staff member standing in) hears that an order was handed to them: every transition
 * into courier_assigned and every reassignment of a waiting order to another courier, whichever OrderStatusService
 * caller made it (assign-courier, a status update with or without a chosen courier, the Telegram "deliver" button).
 * Repeating the same courier is a no-op without an event, so each event here is a new assignment. An order sent
 * straight on its way (e.g. the admin panel's "Send to Delivery") gets the delivery card from
 * TelegramNotificationService instead.
 *
 * Not queued: it runs once the transition has committed and reads the order as that transition left it, so a quick
 * second reassignment cannot redirect this message; the message itself is a queued SendTelegramRequest.
 */
class SendCourierAssignedAlert implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected OrderAlertService $alerts
    ) {}

    public function handle(OrderStatusUpdated $event): void
    {
        $order = $event->order;

        if ($order->status !== OrderStatus::COURIER_ASSIGNED) {
            return;
        }

        try {
            // Queried rather than a relation that may have been loaded before a reassignment
            $this->alerts->courierAssigned($order, $order->courier()->first());
        } catch (Throwable $e) {
            // The transition is already committed: a failed notification must not turn it into an error response
            Log::error("Failed to queue the courier assignment alert for order {$order->id}: {$e->getMessage()}");
        }
    }
}
