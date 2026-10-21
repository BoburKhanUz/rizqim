<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.items_changed (P3-12, docs/POS_SPEC.md §12.2): a POS order's lines, guests, table or charges changed (a round
 * added, lines sent to the kitchen, a line voided or served, guests, a move or merge, a charge waived or restored, the
 * whole order cancelled — P3 integration fixes). Goes to the
 * order's staff channels (store, branch, pos-branch). Like every OrderBroadcast: queued, after commit, tried once,
 * rescued; ids, the order's version and what happened only: the till, waiter phones and KDS refetch the order.
 */
class OrderItemsChangedBroadcast extends OrderBroadcast
{
    public int $version;

    public ?int $tableId;

    /**
     * @param  'round_added'|'sent'|'voided'|'guests_changed'|'moved'|'merged'|'charges_changed'|'served'|'cancelled'  $change
     * @param  list<int>  $itemIds  the lines concerned (none for guests, a move or a charge)
     */
    public function __construct(Order $order, public string $change, public array $itemIds = [])
    {
        parent::__construct($order);

        $this->version = (int) $order->version;
        $this->tableId = $order->table_id !== null ? (int) $order->table_id : null;
    }

    public function broadcastAs(): string
    {
        return 'order.items_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->staffChannels();
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, version: int, table_id: int|null, change: string, item_ids: list<int>}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'version' => $this->version,
            'table_id' => $this->tableId,
            'change' => $this->change,
            'item_ids' => $this->itemIds,
        ];
    }
}
