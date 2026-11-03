<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * kitchen.order_assembled (P6-33, module `kds_expo`): the expo (assembly) station bumped an order assembled — or a line
 * going back to the kitchen undid it (`assembled: false`). Goes to the order's staff channels (store, branch,
 * pos-branch): the station screens drop or show it on the expo, the till refreshes. The number itself is announced by
 * the pickup queue (`queue.ready`). Like every OrderBroadcast: queued, after commit, tried once, rescued; ids only.
 */
class KitchenOrderAssembledBroadcast extends OrderBroadcast
{
    public ?string $assembledAt;

    public ?int $queueNumber;

    public function __construct(Order $order, public bool $assembled)
    {
        parent::__construct($order);

        $at = $order->getAttribute('assembled_at');
        $this->assembledAt = $at !== null ? \Illuminate\Support\Carbon::parse($at, 'UTC')->toJSON() : null;
        $this->queueNumber = $order->queue_number !== null ? (int) $order->queue_number : null;
    }

    public function broadcastAs(): string
    {
        return 'kitchen.order_assembled';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->staffChannels();
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, assembled: bool, assembled_at: string|null, queue_number: int|null}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'assembled' => $this->assembled,
            'assembled_at' => $this->assembledAt,
            'queue_number' => $this->queueNumber,
        ];
    }
}
