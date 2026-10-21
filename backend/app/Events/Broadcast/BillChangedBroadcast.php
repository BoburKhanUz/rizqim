<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use App\Models\OrderBill;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * order.bill_changed (P3-18): an order's bills were made or split, printed, paid (part or whole), got their receipt
 * or were voided. Goes to the order's staff channels (store, branch, pos-branch); ids and status only, like every
 * OrderBroadcast: terminals refetch GET /pos/orders/{uuid}/bills.
 */
class BillChangedBroadcast extends OrderBroadcast
{
    public ?string $billUuid;

    public ?string $billStatus;

    public ?string $fiscalStatus;

    /**
     * @param  string  $change  created | printed | payment | fiscal | voided
     */
    public function __construct(Order $order, ?OrderBill $bill, public string $change)
    {
        parent::__construct($order);

        $this->billUuid = $bill?->uuid;
        $this->billStatus = $bill?->status->value;
        $this->fiscalStatus = $bill?->getAttribute('fiscal_status');
    }

    public function broadcastAs(): string
    {
        return 'order.bill_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->staffChannels();
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, bill: array{uuid: string, status: string|null, fiscal_status: string|null}|null, change: string}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'bill' => $this->billUuid === null ? null : [
                'uuid' => $this->billUuid,
                'status' => $this->billStatus,
                'fiscal_status' => $this->fiscalStatus,
            ],
            'change' => $this->change,
        ];
    }
}
