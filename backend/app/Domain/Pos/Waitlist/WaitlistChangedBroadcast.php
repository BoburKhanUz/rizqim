<?php

namespace App\Domain\Pos\Waitlist;

use App\Events\Broadcast\OrderBroadcast;
use App\Models\PosWaitlistEntry;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * waitlist.changed (P6-24): a waitlist entry was added, changed, called, seated or closed. To the branch's floor
 * channel (pos-branch.{id}); the uuid, status and version only: the hostess' screens refetch GET /pos/waitlist. Queued
 * on `broadcasts`, after commit, tried once and rescued, like OrderBroadcast.
 */
class WaitlistChangedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 1;

    public string $uuid;

    public string $status;

    public int $branchId;

    public int $version;

    public function __construct(PosWaitlistEntry $entry)
    {
        $this->uuid = (string) $entry->uuid;
        $this->status = $entry->status->value;
        $this->branchId = (int) $entry->branch_id;
        $this->version = (int) $entry->version;
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'waitlist.changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pos-branch.{$this->branchId}")];
    }

    /**
     * @return array{entry: array{uuid: string, status: string, branch_id: int, version: int}}
     */
    public function broadcastWith(): array
    {
        return ['entry' => ['uuid' => $this->uuid, 'status' => $this->status, 'branch_id' => $this->branchId, 'version' => $this->version]];
    }
}
