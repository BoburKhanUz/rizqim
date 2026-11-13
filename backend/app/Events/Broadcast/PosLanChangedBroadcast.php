<?php

namespace App\Events\Broadcast;

use App\Domain\Pos\Lan\LanChange;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * pos.lan_changed (P6 review S5, docs/POS_SPEC.md §5.5): the branch's local bridge changed in a way the hub must hear
 * now — a terminal was revoked, the key rotated, the hub moved or its port changed (`reason`, LanChange). Goes to
 * `pos-branch.{id}`, the paired terminals of the branch; they refetch GET /pos/lan (the hub: its device list and
 * keys). Ids only, never a key. Like every POS hint: queued on `broadcasts`, after commit, tried once and rescued.
 */
class PosLanChangedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 1;

    public string $changedAt;

    /**
     * @param  string|null  $device  the revoked terminal's uuid (device_revoked) or the new hub's (hub_changed)
     */
    public function __construct(
        public int $branchId,
        public LanChange $reason,
        public ?string $device = null,
    ) {
        $this->changedAt = now()->toJSON();
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'pos.lan_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pos-branch.{$this->branchId}")];
    }

    /**
     * @return array{branch_id: int, reason: value-of<LanChange>, device: string|null, changed_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'branch_id' => $this->branchId,
            'reason' => $this->reason->value,
            'device' => $this->device,
            'changed_at' => $this->changedAt,
        ];
    }
}
