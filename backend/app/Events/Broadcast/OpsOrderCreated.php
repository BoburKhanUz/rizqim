<?php

namespace App\Events\Broadcast;

/**
 * ops.order.created (P4-14): a new marketplace order, for the Buyurtmalar live list.
 */
class OpsOrderCreated extends OpsOrderBroadcast
{
    public function broadcastAs(): string
    {
        return 'ops.order.created';
    }

    /**
     * @return array{order: array{uuid: string, short_code: string|null, status: string, channel: string, type: string, store_id: int, branch_id: int|null, updated_at: string|null}}
     */
    public function broadcastWith(): array
    {
        return ['order' => $this->orderPayload()];
    }
}
