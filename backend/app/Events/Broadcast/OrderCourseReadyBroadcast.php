<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.course_ready (P6-21): every line of one course of a POS hall order that went to the kitchen is ready. Goes to
 * the order's staff channels (store, branch, pos-branch); the waiter phone shows "2-kurs tayyor: 7-stol" when
 * `waiter_id` is its own staff member (a phone of an order without a waiter: every waiter phone of the branch), the till
 * on its floor map. Like every OrderBroadcast: queued, after commit, tried once, rescued; ids, the course and the
 * table only.
 */
class OrderCourseReadyBroadcast extends OrderBroadcast
{
    public int $version;

    public ?int $tableId;

    public ?int $waiterId;

    /**
     * @param  list<int>  $itemIds
     */
    public function __construct(Order $order, public int $courseNo, public array $itemIds)
    {
        parent::__construct($order);

        $this->version = (int) $order->version;
        $this->tableId = $order->table_id !== null ? (int) $order->table_id : null;
        $this->waiterId = $order->waiter_id !== null ? (int) $order->waiter_id : null;
    }

    public function broadcastAs(): string
    {
        return 'order.course_ready';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->staffChannels();
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, version: int, course_no: int, item_ids: list<int>, table_id: int|null, waiter_id: int|null}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'version' => $this->version,
            'course_no' => $this->courseNo,
            'item_ids' => $this->itemIds,
            'table_id' => $this->tableId,
            'waiter_id' => $this->waiterId,
        ];
    }
}
