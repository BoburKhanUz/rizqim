<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A real-time hint to the Mini App that an order changed (Laravel Reverb, channels in routes/channels.php).
 *
 * Queued (ShouldBroadcast) and dispatched only after the surrounding transaction commits, so a rolled-back change is
 * never announced. The payload is the order as it was at that moment and nothing personal: clients refetch the order
 * through the API, which applies OrderPolicy. With BROADCAST_CONNECTION=null or log nothing reaches a client and
 * nothing else changes.
 *
 * Like Telegram, a Reverb outage never fails the request (the order is already committed, a 4xx/5xx would make the
 * customer retry into a duplicate): ShouldRescue reports a failed push (or, on a sync queue, a failed send) and moves
 * on. Queued on their own `broadcasts` queue and tried once: a hint that missed its moment is stale, the Mini App
 * refetches anyway, and an outage must not pile retries in front of the Telegram jobs on `default`.
 */
abstract class OrderBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public const QUEUE = 'broadcasts';

    public int $tries = 1;

    public string $uuid;

    public string $status;

    public int $storeId;

    public ?int $branchId;

    public ?int $courierId;

    public int $customerId;

    public ?string $updatedAt;

    public function __construct(Order $order)
    {
        $this->uuid = (string) $order->uuid;
        $this->status = $order->status->value;
        $this->storeId = (int) $order->store_id;
        $this->branchId = $order->branch_id !== null ? (int) $order->branch_id : null;
        $this->courierId = $order->courier_id !== null ? (int) $order->courier_id : null;
        $this->customerId = (int) $order->user_id;
        $this->updatedAt = $order->updated_at?->toJSON();
    }

    public function broadcastQueue(): string
    {
        return self::QUEUE;
    }

    abstract public function broadcastAs(): string;

    /**
     * @return array<int, PrivateChannel>
     */
    abstract public function broadcastOn(): array;

    /**
     * The whole payload (the contract with the Mini App): no names, phones, addresses or prices.
     *
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}}
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'uuid' => $this->uuid,
                'status' => $this->status,
                'store_id' => $this->storeId,
                'branch_id' => $this->branchId,
                'courier_id' => $this->courierId,
                'updated_at' => $this->updatedAt,
            ],
        ];
    }

    /**
     * store.{id} and, for an order of a branch, branch.{id} and pos-branch.{id} (P2-11: everyone working in the
     * branch and its paired terminals)
     *
     * @return array<int, PrivateChannel>
     */
    protected function staffChannels(): array
    {
        $channels = [new PrivateChannel("store.{$this->storeId}")];

        if ($this->branchId !== null) {
            $channels[] = new PrivateChannel("branch.{$this->branchId}");
            $channels[] = new PrivateChannel("pos-branch.{$this->branchId}");
        }

        return $channels;
    }

    protected function courierChannel(int $courierId): PrivateChannel
    {
        return new PrivateChannel("courier.{$courierId}");
    }
}
