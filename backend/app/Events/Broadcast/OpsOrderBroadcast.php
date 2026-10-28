<?php

namespace App\Events\Broadcast;

use App\Models\Order;

/**
 * P4-14: a marketplace order on the team's live channel `platform.ops`: ids, status and the SLA, nothing personal (the
 * admin app refetches GET /admin/orders or the card).
 */
abstract class OpsOrderBroadcast extends PlatformOpsBroadcast
{
    public string $uuid;

    public ?string $shortCode;

    public string $status;

    public string $channel;

    public string $type;

    public int $storeId;

    public ?int $branchId;

    public ?string $updatedAt;

    public function __construct(Order $order)
    {
        $this->uuid = (string) $order->uuid;
        $this->shortCode = $order->short_code;
        $this->status = $order->status->value;
        $this->channel = $order->channel->value;
        $this->type = $order->order_type->value;
        $this->storeId = (int) $order->store_id;
        $this->branchId = $order->branch_id !== null ? (int) $order->branch_id : null;
        $this->updatedAt = $order->updated_at?->toIso8601String();
    }

    /**
     * @return array{uuid: string, short_code: string|null, status: string, channel: string, type: string, store_id: int, branch_id: int|null, updated_at: string|null}
     */
    protected function orderPayload(): array
    {
        return [
            'uuid' => $this->uuid,
            'short_code' => $this->shortCode,
            'status' => $this->status,
            'channel' => $this->channel,
            'type' => $this->type,
            'store_id' => $this->storeId,
            'branch_id' => $this->branchId,
            'updated_at' => $this->updatedAt,
        ];
    }
}
