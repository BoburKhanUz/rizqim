<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.status_changed: every change OrderStatusService makes (a new status, a cancellation, a refund, a waiting order
 * handed to another courier). Goes to the store's staff, the customer and the order's courier; when the courier
 * changed, the previous one hears it too (their copy disappears on refetch).
 */
class OrderStatusChangedBroadcast extends OrderBroadcast
{
    public ?int $previousCourierId;

    public function __construct(Order $order, ?int $previousCourierId = null)
    {
        parent::__construct($order);

        $this->previousCourierId = $previousCourierId;
    }

    public function broadcastAs(): string
    {
        return 'order.status_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = $this->staffChannels();
        $channels[] = new PrivateChannel("user.{$this->customerId}");

        foreach (array_unique(array_filter([$this->courierId, $this->previousCourierId])) as $courierId) {
            $channels[] = $this->courierChannel($courierId);
        }

        return $channels;
    }
}
