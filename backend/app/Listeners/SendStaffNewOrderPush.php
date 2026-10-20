<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Models\User;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\NotificationRouter;
use App\Services\Push\OrderPushMessages;
use App\Services\Push\PushSender;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The app push for a new order, to the staff who may act on it: the managers of its branch and the store-wide managers,
 * the owners when there is no manager (the same people the Telegram order card goes to). The Telegram messages stay
 * where they are; this only adds the push, and only for staff who keep it on for new orders. Not queued: it just
 * queues one SendPushNotification per device, and nothing it does can fail the order that was created.
 */
class SendStaffNewOrderPush
{
    public function __construct(
        protected NotificationRouter $router,
        protected PushSender $push,
        protected OrderPushMessages $messages,
    ) {}

    public function handle(OrderCreated $event): void
    {
        try {
            if (!$this->push->isConfigured()) {
                return;
            }

            $order = $event->order;
            $staff = User::where('store_id', $order->store_id)
                ->where('role', UserRole::MANAGER->value)
                ->where(fn ($query) => $query->whereNull('branch_id')
                    ->when($order->branch_id, fn ($query) => $query->orWhere('branch_id', $order->branch_id)))
                ->get();

            if ($staff->isEmpty()) {
                $staff = User::where('store_id', $order->store_id)->where('role', UserRole::OWNER->value)->get();
            }

            $content = $this->messages->forNewOrder($order);

            foreach ($staff as $person) {
                $this->router->send($person, new NotificationMessage(NotificationType::STAFF_NEW_ORDER, push: $content));
            }
        } catch (Throwable $e) {
            Log::error("Failed to queue the new order push for order {$event->order->id}: {$e->getMessage()}");
        }
    }
}
