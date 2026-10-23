<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.bill_requested (P3-26): a waiter asked the till for the bill of a POS hall order (POST
 * /pos/orders/{uuid}/request-bill). Goes to the order's staff channels (store, branch, pos-branch): the till shows
 * "Schyot so'raldi" on the floor map and in its queue. Like every OrderBroadcast: queued, after commit, tried once,
 * rescued; ids, the order's version, the table and who asked only.
 */
class OrderBillRequestedBroadcast extends OrderBroadcast
{
    public int $version;

    public ?int $tableId;

    public ?string $requestedAt;

    public ?int $requestedBy;

    public function __construct(Order $order)
    {
        parent::__construct($order);

        $this->version = (int) $order->version;
        $this->tableId = $order->table_id !== null ? (int) $order->table_id : null;
        $this->requestedAt = $order->bill_requested_at?->toJSON();
        $this->requestedBy = $order->bill_requested_by !== null ? (int) $order->bill_requested_by : null;
    }

    public function broadcastAs(): string
    {
        return 'order.bill_requested';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->staffChannels();
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, version: int, table_id: int|null, bill_requested_at: string|null, requested_by: int|null}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'version' => $this->version,
            'table_id' => $this->tableId,
            'bill_requested_at' => $this->requestedAt,
            'requested_by' => $this->requestedBy,
        ];
    }
}
