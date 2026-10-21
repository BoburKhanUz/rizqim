<?php

namespace App\Events\Broadcast;

use App\Models\Device;
use App\Models\PosShift;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * shift.changed (P3-18): a till's shift was opened, had cash put in or taken out, or was closed (Z). Goes to the
 * branch's floor channel (pos-branch.{id}) and its managers' (branch.{id}); like every POS hint it is queued on
 * `broadcasts`, after commit, tried once and rescued, and carries ids and status only: terminals refetch the shift.
 */
class ShiftChangedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 1;

    public string $uuid;

    public string $status;

    public int $storeId;

    public int $branchId;

    public ?string $deviceUuid;

    public string $businessDate;

    public ?int $zNumber;

    public string $changedAt;

    /**
     * @param  string  $change  opened | cash_movement | closed
     */
    public function __construct(PosShift $shift, public string $change)
    {
        $this->uuid = (string) $shift->uuid;
        $this->status = $shift->status->value;
        $this->storeId = (int) $shift->store_id;
        $this->branchId = (int) $shift->branch_id;
        $this->deviceUuid = $shift->device_id !== null ? Device::whereKey($shift->device_id)->value('uuid') : null;
        $this->businessDate = $shift->business_date->toDateString();
        $this->zNumber = $shift->z_number !== null ? (int) $shift->z_number : null;
        $this->changedAt = now()->toJSON();
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'shift.changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->branchId}"),
            new PrivateChannel("pos-branch.{$this->branchId}"),
        ];
    }

    /**
     * @return array{shift: array{uuid: string, status: string, store_id: int, branch_id: int, device_uuid: string|null, business_date: string, z_number: int|null}, change: string, changed_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'shift' => [
                'uuid' => $this->uuid,
                'status' => $this->status,
                'store_id' => $this->storeId,
                'branch_id' => $this->branchId,
                'device_uuid' => $this->deviceUuid,
                'business_date' => $this->businessDate,
                'z_number' => $this->zNumber,
            ],
            'change' => $this->change,
            'changed_at' => $this->changedAt,
        ];
    }
}
