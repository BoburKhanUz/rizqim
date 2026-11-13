<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * bill.print_requested (P6 stage A): the bill of a POS hall order is to be printed as a pre-check ("Bu fiskal chek
 * emas"): the waiter asked for it (`reprint` false: POST /pos/orders/{uuid}/request-bill with the branch's
 * `auto_print_bill` on) or asked for a copy again (`reprint` true: POST /pos/bills/{uuid}/print-request). Goes to
 * `pos-branch.{id}` only, the paired terminals of the branch; the till named in `target_device` (the device that owns
 * the printer, or the branch's printing till; null: none is named, every till prints) prints it on `printer_id` (null:
 * its own receipt printer), once per `request_id`. The till reads the bill itself (GET /pos/orders/{uuid}/bills).
 * Like every OrderBroadcast: queued, after commit, tried once, rescued; ids only.
 */
class BillPrintRequestedBroadcast extends OrderBroadcast
{
    public function __construct(
        Order $order,
        public string $billUuid,
        public string $requestId,
        public bool $reprint,
        public ?int $printerId,
        public ?string $targetDevice,
        public ?int $requestedBy,
    ) {
        parent::__construct($order);
    }

    public function broadcastAs(): string
    {
        return 'bill.print_requested';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->branchId === null ? [] : [new PrivateChannel("pos-branch.{$this->branchId}")];
    }

    /**
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, bill: array{uuid: string}, request_id: string, reprint: bool, printer_id: int|null, target_device: string|null, requested_by: int|null}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'bill' => ['uuid' => $this->billUuid],
            'request_id' => $this->requestId,
            'reprint' => $this->reprint,
            'printer_id' => $this->printerId,
            'target_device' => $this->targetDevice,
            'requested_by' => $this->requestedBy,
        ];
    }
}
