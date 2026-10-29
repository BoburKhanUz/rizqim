<?php

namespace App\Domain\Pos\Hall;

use App\Events\Broadcast\OrderBroadcast;
use App\Models\DiningTable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * table.state_changed (P6-26): the hostess marked a place `cleaning` or cleared it. To the branch's floor channel
 * (pos-branch.{id}); the screens refetch the floor. Queued on `broadcasts`, after commit, tried once and rescued.
 */
class TableStateChangedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 1;

    public int $branchId;

    public int $tableId;

    public ?string $serviceState;

    public function __construct(DiningTable $table)
    {
        $this->branchId = (int) $table->branch_id;
        $this->tableId = (int) $table->id;
        $this->serviceState = $table->service_state;
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'table.state_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pos-branch.{$this->branchId}")];
    }

    /**
     * @return array{table: array{id: int, branch_id: int, service_state: string|null}}
     */
    public function broadcastWith(): array
    {
        return ['table' => ['id' => $this->tableId, 'branch_id' => $this->branchId, 'service_state' => $this->serviceState]];
    }
}
