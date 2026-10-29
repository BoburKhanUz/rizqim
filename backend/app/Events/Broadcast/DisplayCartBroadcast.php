<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;

/**
 * display.cart (P6-14): a till changed the cart it shows on its customer display. A hint on `pos-branch.{id}` like every
 * POS event: the till's uuid, a sequence and the screen state (idle, cart, payment, paid): no names or prices. The
 * display refetches GET /pos/display/state. Sent at once (not queued): the customer watches the screen while the cashier
 * taps, and a missed hint costs nothing (the display polls anyway); a Reverb outage never fails the till's request.
 */
class DisplayCartBroadcast implements ShouldBroadcastNow, ShouldRescue
{
    public function __construct(
        public int $branchId,
        public int $storeId,
        public string $terminal,
        public int $seq,
        public string $state,
    ) {}

    public function broadcastAs(): string
    {
        return 'display.cart';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pos-branch.{$this->branchId}")];
    }

    /**
     * @return array{terminal: string, seq: int, state: string, branch_id: int, store_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'terminal' => $this->terminal,
            'seq' => $this->seq,
            'state' => $this->state,
            'branch_id' => $this->branchId,
            'store_id' => $this->storeId,
        ];
    }
}
