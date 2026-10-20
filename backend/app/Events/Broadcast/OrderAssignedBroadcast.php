<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.assigned: the order was handed to a courier (or reassigned to another one) by OrderStatusService.
 */
class OrderAssignedBroadcast extends OrderBroadcast
{
    public function broadcastAs(): string
    {
        return 'order.assigned';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = $this->courierId !== null ? [$this->courierChannel($this->courierId)] : [];

        return array_merge($channels, $this->staffChannels());
    }
}
