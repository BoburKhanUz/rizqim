<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * P6-13 (A2): a queue number moved on the pickup board, on the branch's floor channel `pos-branch.{id}` (tills, the
 * kitchen display, the customer display): `queue.preparing` (the number entered "Tayyorlanmoqda" or a recalled line
 * took it back there), `queue.ready` (ready; again with `called: true` when the till re-announces it, POST
 * /pos/orders/{uuid}/call), `queue.handed` (the customer took it), `queue.canceled` (the order was cancelled: the
 * number leaves the board). Like every OrderBroadcast: queued on `broadcasts`, after commit, tried once, rescued; the
 * order's ids and status plus the number and its state only (no names, phones or prices).
 */
class QueueNumberBroadcast extends OrderBroadcast
{
    public const EVENTS = ['queue.preparing', 'queue.ready', 'queue.handed', 'queue.canceled'];

    public ?int $number;

    public ?string $businessDate;

    public ?string $pickupStatus;

    public string $channel;

    public ?string $readyAt;

    public ?string $calledAt;

    public int $callCount;

    public ?string $handedAt;

    public function __construct(Order $order, public string $event, public bool $called = false)
    {
        parent::__construct($order);

        $this->number = $order->queue_number !== null ? (int) $order->queue_number : null;
        $this->businessDate = $order->business_date?->toDateString();
        $this->pickupStatus = $order->getAttribute('pickup_status');
        $this->channel = $order->channel->value;
        $this->readyAt = $order->pickup_ready_at?->toJSON();
        $this->calledAt = $order->pickup_called_at?->toJSON();
        $this->callCount = (int) $order->getAttribute('pickup_call_count');
        $this->handedAt = $order->pickup_handed_at?->toJSON();
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->branchId !== null ? [new PrivateChannel("pos-branch.{$this->branchId}")] : [];
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, queue: array{number: int|null, business_date: string|null, status: string|null, channel: string, ready_at: string|null, called_at: string|null, call_count: int, handed_at: string|null, called: bool}}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'queue' => [
                'number' => $this->number,
                'business_date' => $this->businessDate,
                'status' => $this->pickupStatus,
                'channel' => $this->channel,
                'ready_at' => $this->readyAt,
                'called_at' => $this->calledAt,
                'call_count' => $this->callCount,
                'handed_at' => $this->handedAt,
                'called' => $this->called,
            ],
        ];
    }
}
