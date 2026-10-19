<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.created: a new order for the store's staff (CreateOrderAction, after commit).
 */
class OrderCreatedBroadcast extends OrderBroadcast
{
    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->staffChannels();
    }
}
