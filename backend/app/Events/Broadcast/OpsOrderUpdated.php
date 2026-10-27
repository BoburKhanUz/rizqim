<?php

namespace App\Events\Broadcast;

use App\Models\Order;

/**
 * ops.order.updated (P4-14): a marketplace order changed. `change`: `status` (any status change or courier assignment)
 * or `sla` (its running stage crossed into warning or late, once per order and stage: OrderSlaWatcher), with `sla`
 * {stage, state, late_by_minutes} for the latter.
 */
class OpsOrderUpdated extends OpsOrderBroadcast
{
    public const STATUS = 'status';

    public const SLA = 'sla';

    /**
     * @param  array{stage: string, state: string, late_by_minutes: int}|null  $sla
     */
    public function __construct(Order $order, public string $change = self::STATUS, public ?array $sla = null)
    {
        parent::__construct($order);
    }

    public function broadcastAs(): string
    {
        return 'ops.order.updated';
    }

    /**
     * @return array{order: array{uuid: string, short_code: string|null, status: string, channel: string, type: string, store_id: int, branch_id: int|null, updated_at: string|null}, change: string, sla: array{stage: string, state: string, late_by_minutes: int}|null}
     */
    public function broadcastWith(): array
    {
        return ['order' => $this->orderPayload(), 'change' => $this->change, 'sla' => $this->sla];
    }
}
