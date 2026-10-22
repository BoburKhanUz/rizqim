<?php

namespace App\Events\Broadcast;

use App\Models\FiscalReceipt;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * fiscal.receipt_updated (P3-11): a receipt was issued or failed (after a retry, the sweeper or a manual retry), so
 * the till can drop it from "fiskal kutmoqda" or reprint with the QR. Sent to `pos-branch.{branchId}` (when the
 * receipt has a branch) and `store.{storeId}`. Ids and status only: clients refetch the receipt through the API.
 * Queued on `broadcasts`, after commit, tried once and rescued, like OrderBroadcast.
 */
class FiscalReceiptUpdatedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public int $tries = 1;

    public int $storeId;

    public ?int $branchId;

    public string $uuid;

    public string $type;

    public string $status;

    public ?string $subjectType;

    public ?int $subjectId;

    public function __construct(FiscalReceipt $receipt)
    {
        $this->storeId = (int) $receipt->store_id;
        $this->branchId = $receipt->branch_id !== null ? (int) $receipt->branch_id : null;
        $this->uuid = $receipt->uuid;
        $this->type = $receipt->type->value;
        $this->status = $receipt->status->value;
        $this->subjectType = $receipt->subject_type;
        $this->subjectId = $receipt->subject_id;
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'fiscal.receipt_updated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("store.{$this->storeId}")];
        if ($this->branchId !== null) {
            $channels[] = new PrivateChannel("pos-branch.{$this->branchId}");
        }

        return $channels;
    }

    /**
     * @return array{receipt: array{uuid: string, type: string, status: string, store_id: int, branch_id: int|null, subject_type: string|null, subject_id: int|null}}
     */
    public function broadcastWith(): array
    {
        return [
            'receipt' => [
                'uuid' => $this->uuid,
                'type' => $this->type,
                'status' => $this->status,
                'store_id' => $this->storeId,
                'branch_id' => $this->branchId,
                'subject_type' => $this->subjectType,
                'subject_id' => $this->subjectId,
            ],
        ];
    }
}
