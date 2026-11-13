<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Orders\PosOrderPresenter;
use App\Domain\Pos\Orders\PosOrderService;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\PosRefund;
use Illuminate\Support\Facades\DB;

/**
 * Review P6 hardware/LAN (C2): the branch's outstanding "print this bill as a pre-check" requests
 * (BillPrintRequests announces them as `bill.print_requested`). The broadcast is fire-and-forget: a till that was
 * offline for a moment, restarted or sat at its PIN screen without a staff session would never print the waiter's
 * bill. So every announced request is kept here (`pos_bill_print_requests`) and the till, with its own device token,
 * reads the ones meant for it (GET /pos/bill-print-requests: on reconnect, on unlock, on start and whenever a
 * broadcast arrives while nobody is signed in) and claims each before printing (POST
 * /pos/bill-print-requests/{request_id}/printed): one paper per request even with two tills or a broadcast and a
 * catch-up together.
 *
 * Only open POS orders, bills that are neither void nor paid, and requests of the last PENDING_MINUTES are outstanding:
 * an older one is the cashier's to print by hand (the waiter can ask for a copy again).
 */
class BillPrintInbox
{
    /** A request older than this is no longer printed by itself. */
    public const PENDING_MINUTES = 60;

    /** Rows are pruned after this many days. */
    public const KEEP_DAYS = 2;

    /** The most requests one read returns. */
    public const LIMIT = 20;

    /** One outstanding request: the event's fields plus the bill and the order as the till reads them with a staff session. */
    public const ENTRY = 'array{request_id: string, reprint: bool, printer_id: int|null, target_device: string|null, requested_by: int|null, requested_at: string|null, bill: array{uuid: string}, order: ' . \App\Http\Controllers\Api\V1\PosHall\PosOrderController::ORDER . ', billing: array{order: ' . BillPresenter::ORDER . ', bills: list<' . BillPresenter::BILL . '>, refunds: list<' . BillPresenter::REFUND . '>}}';

    public function record(Order $order, string $billUuid, string $requestId, bool $reprint, ?int $printerId, ?string $targetDevice, ?int $requestedBy): void
    {
        if ($order->branch_id === null) {
            return;
        }

        DB::table('pos_bill_print_requests')->insertOrIgnore([
            'request_id' => strtolower($requestId),
            'store_id' => $order->store_id,
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'bill_uuid' => $billUuid,
            'reprint' => $reprint,
            'printer_id' => $printerId,
            'target_device' => $targetDevice,
            'requested_by' => $requestedBy,
            'created_at' => now(),
        ]);

        DB::table('pos_bill_print_requests')
            ->where('branch_id', $order->branch_id)
            ->where('created_at', '<', now()->subDays(self::KEEP_DAYS))
            ->delete();
    }

    /**
     * The outstanding requests this till prints: its branch's, named to it or to no till, not printed yet.
     *
     * @return list<array<string, mixed>>
     */
    public function pending(Device $device): array
    {
        if ($device->branch_id === null) {
            return [];
        }

        $rows = DB::table('pos_bill_print_requests')
            ->where('store_id', $device->store_id)
            ->where('branch_id', $device->branch_id)
            ->whereNull('printed_at')
            ->where('created_at', '>=', now()->subMinutes(self::PENDING_MINUTES))
            ->where(fn ($query) => $query->whereNull('target_device')->orWhere('target_device', (string) $device->uuid))
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $orders = app(PosOrderPresenter::class);
        $bills = app(BillPresenter::class);
        $out = [];
        foreach ($rows as $row) {
            $order = Order::query()->with(PosOrderPresenter::RELATIONS)->whereKey($row->order_id)->first();
            if ($order === null || !in_array($order->status, PosOrderService::OPEN_STATUSES, true)) {
                continue;
            }
            $bill = OrderBill::where('order_id', $order->id)->where('uuid', $row->bill_uuid)->first();
            if ($bill === null || $bill->status === BillStatus::VOID || $bill->status === BillStatus::PAID) {
                continue;
            }

            $out[] = [
                'request_id' => (string) $row->request_id,
                'reprint' => (bool) $row->reprint,
                'printer_id' => $row->printer_id !== null ? (int) $row->printer_id : null,
                'target_device' => $row->target_device !== null ? (string) $row->target_device : null,
                'requested_by' => $row->requested_by !== null ? (int) $row->requested_by : null,
                'requested_at' => $row->created_at !== null ? \Illuminate\Support\Carbon::parse($row->created_at)->toJSON() : null,
                'bill' => ['uuid' => (string) $bill->uuid],
                'order' => $orders->order($order),
                'billing' => [
                    'order' => $bills->order($order),
                    'bills' => OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->orderBy('number')->get()
                        ->map(fn (OrderBill $b) => $bills->bill($b, $order))->values()->all(),
                    'refunds' => PosRefund::where('order_id', $order->id)->orderBy('id')->get()
                        ->map(fn (PosRefund $refund) => $bills->refund($refund))->values()->all(),
                ],
            ];
        }

        return $out;
    }

    /**
     * The till takes the request before printing it: `true` when it is this till's to print (first to claim it, or
     * claimed by this till already), `false` when another till took it, `null` when the branch has no such request.
     */
    public function claim(Device $device, string $requestId): ?bool
    {
        $id = strtolower($requestId);
        $query = fn () => DB::table('pos_bill_print_requests')
            ->where('request_id', $id)
            ->where('store_id', $device->store_id)
            ->where('branch_id', $device->branch_id);

        $taken = $query()->whereNull('printed_at')->update(['printed_at' => now(), 'printed_device_id' => $device->id]);
        if ($taken > 0) {
            return true;
        }

        $row = $query()->first(['printed_device_id']);
        if ($row === null) {
            return null;
        }

        return (int) $row->printed_device_id === (int) $device->id;
    }
}
