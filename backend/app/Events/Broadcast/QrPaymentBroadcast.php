<?php

namespace App\Events\Broadcast;

use App\Models\PosQrPayment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * qr.paid | qr.cancelled | qr.expired (P6-41): a QR shown at the till was paid (the provider confirmed it and the
 * bill took the payment), cancelled (the cashier, a newer QR, a bill change, a refused confirmation) or ran out of
 * time. Goes to the branch's floor channel (pos-branch.{id}); like every POS hint it is queued on `broadcasts`, after
 * commit, tried once and rescued, and carries ids and status only: the till refetches GET /pos/qr/{uuid} and the bill.
 */
class QrPaymentBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 1;

    public string $uuid;

    public string $billUuid;

    public string $orderUuid;

    public string $provider;

    public string $status;

    public int $branchId;

    public string $changedAt;

    public function __construct(PosQrPayment $qr, string $orderUuid)
    {
        $this->uuid = (string) $qr->uuid;
        $this->billUuid = (string) $qr->bill_uuid;
        $this->orderUuid = $orderUuid;
        $this->provider = $qr->provider->value;
        $this->status = $qr->status->value;
        $this->branchId = (int) $qr->branch_id;
        $this->changedAt = now()->toJSON();
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'qr.' . $this->status;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pos-branch.{$this->branchId}")];
    }

    /**
     * @return array{qr: array{uuid: string, bill_uuid: string, order_uuid: string, provider: string, status: string}, changed_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'qr' => [
                'uuid' => $this->uuid,
                'bill_uuid' => $this->billUuid,
                'order_uuid' => $this->orderUuid,
                'provider' => $this->provider,
                'status' => $this->status,
            ],
            'changed_at' => $this->changedAt,
        ];
    }
}
