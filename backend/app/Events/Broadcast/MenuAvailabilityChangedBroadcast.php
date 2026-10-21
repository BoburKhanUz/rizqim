<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * menu.availability_changed: something on a branch's menu became unavailable or available again (stop-list entry
 * added, removed or expired; a batch opened, sold out, refilled or closed; a branch override switched). P1-21.
 *
 * Sent to the staff channels (store.{id}, branch.{id}, pos-branch.{id}, like the order events) and to the public `menu.{storeId}`
 * channel the customer apps listen on: availability is public anyway (it is in GET /catalog). The payload says what
 * changed; clients refetch GET /catalog/availability for the full picture. Queued on `broadcasts`, after commit,
 * tried once and rescued, exactly like OrderBroadcast: a hint must never fail the request that caused it.
 */
class MenuAvailabilityChangedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 1;

    public string $changedAt;

    /**
     * @param  list<array{type: string, id: int, available: bool}>  $items  what changed (product, variant, modifier)
     * @param  string  $reason  stop_list | batch | branch_override
     */
    public function __construct(
        public int $storeId,
        public ?int $branchId,
        public array $items,
        public string $reason,
    ) {
        $this->changedAt = now()->toJSON();
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'menu.availability_changed';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("store.{$this->storeId}")];

        if ($this->branchId !== null) {
            $channels[] = new PrivateChannel("branch.{$this->branchId}");
            // P2-11: the branch's floor staff and paired terminals (stop-list on the till and the kitchen display)
            $channels[] = new PrivateChannel("pos-branch.{$this->branchId}");
        }

        $channels[] = new Channel("menu.{$this->storeId}");

        return $channels;
    }

    /**
     * @return array{store_id: int, branch_id: int|null, reason: string, items: list<array{type: string, id: int, available: bool}>, changed_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'store_id' => $this->storeId,
            'branch_id' => $this->branchId,
            'reason' => $this->reason,
            'items' => $this->items,
            'changed_at' => $this->changedAt,
        ];
    }
}
