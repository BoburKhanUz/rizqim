<?php

namespace App\Domain\Pos\Orders;

use App\Events\Broadcast\OrderBroadcast;
use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.transferred (P6-24): an open table went to another waiter. Besides the branch's floor channel (everyone
 * refetches the floor) it goes to the old waiter's and the new waiter's own channels (`user.{id}`), so the old one's
 * phone says "stol 5 boshqa ofitsiantga o'tkazildi" and the new one's "stol 5 sizga berildi". Ids and the table's
 * name only; a client refetches the order. Queued after commit, tried once and rescued (OrderBroadcast).
 */
class OrderTransferredBroadcast extends OrderBroadcast
{
    public ?string $tableRef;

    public int $version;

    public function __construct(Order $order, public ?int $fromWaiterId, public int $toWaiterId)
    {
        parent::__construct($order);

        $this->tableRef = $order->table_ref;
        $this->version = (int) $order->version;
    }

    public function broadcastAs(): string
    {
        return 'order.transferred';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = $this->branchId !== null ? [new PrivateChannel("pos-branch.{$this->branchId}")] : [new PrivateChannel("store.{$this->storeId}")];
        foreach (array_unique(array_filter([$this->fromWaiterId, $this->toWaiterId])) as $userId) {
            $channels[] = new PrivateChannel("user.{$userId}");
        }

        return $channels;
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, version: int, table_ref: string|null, from_waiter_id: int|null, to_waiter_id: int}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'version' => $this->version,
            'table_ref' => $this->tableRef,
            'from_waiter_id' => $this->fromWaiterId,
            'to_waiter_id' => $this->toWaiterId,
        ];
    }
}
