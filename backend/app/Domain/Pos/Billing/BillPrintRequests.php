<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Setup\PosSettings;
use App\Enums\Permission;
use App\Enums\PrinterPurpose;
use App\Events\Broadcast\BillPrintRequestedBroadcast;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Printer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asking a till to print a bill as a pre-check (P6 stage A, owner decision 03.11.2026): never fiscal, never the
 * hand-over of the bill (no copy count, no order lock; POST /pos/bills/{uuid}/print stays the till's own print).
 *
 *  - `afterBillRequested()`: the waiter asked for the bill and the branch's `auto_print_bill` is on: the bill is made if
 *    it is not (BillService::ensureForPrint) and `bill.print_requested` goes to the branch's terminals. It never
 *    fails the waiter's request: whatever goes wrong here (nothing to bill, a weight pending, a lost broadcast) is
 *    dropped, the till still sees "Schyot so'raldi" and prints by hand.
 *  - `reprint()`: a copy again from the waiter app: pos.reprint_bill or a manager's approval token (like POST
 *    /bills/{uuid}/print on a copy), audited as `pos.bill_reprinted` with the requester. Review P6 C4: a bill the order
 *    outgrew (another round since, nothing paid on it) is never printed as it was: the order is billed again
 *    (BillService::ensureForPrint) and the current bill is printed as a pre-check (not a copy); when the till split
 *    the order, 409 `bill_stale` (the cashier makes the bills again).
 *  - `outgrown()`: whether the order changed after its bill was made (PosOrderService::requestBill: asking for the bill
 *    again then prints again).
 *
 * Which till prints: the printer is `bill_printer` (an active receipt printer of the branch), else the receipt printer
 * of the branch's printing till; the till is the device the printer belongs to, else the active printing till, else
 * (review P6 C3) the branch's LAN hub (BranchLan::hubOf: the owner's choice, the till holding the hub's secret, the first
 * cashier till), so two tills never both print one pre-check; `target_device` is null only when the branch has no such
 * till (every till prints).
 */
class BillPrintRequests
{
    public function __construct(
        private readonly BillService $bills,
        private readonly ManagerApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  string|null  $requestId  the request's id (review P6 hardware/LAN C6: a relayed LAN request's op id, so the
     *                                  hub that printed it already at the start of an outage knows it); null: a new one
     */
    public function afterBillRequested(Order $order, User $actor, ?Device $device, ?string $requestId = null): void
    {
        try {
            $branch = $order->branch_id !== null ? Branch::find($order->branch_id) : null;
            if ($branch === null || !PosSettings::resolve($branch->pos_settings)['auto_print_bill']) {
                return;
            }

            $bill = $this->bills->ensureForPrint($actor, $order, $device);
            if ($bill === null) {
                return;
            }

            $this->announce($order, $bill, $branch, false, $actor, $requestId);
        } catch (PosActionException) {
            // Nothing to bill yet, a weight is pending, the order is closed: the till prints by hand
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{request_id: string, printer_id: int|null, target_device: string|null}
     *
     * @throws PosActionException
     */
    public function reprint(User $actor, OrderBill $bill, ?string $approvalToken, ?Device $device): array
    {
        $announced = DB::transaction(function () use ($actor, $bill, $approvalToken, $device) {
            $order = Order::whereKey($bill->order_id)->lockForUpdate()->firstOrFail();
            $bill = OrderBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (!in_array($order->channel, \App\Domain\Pos\Shifts\OrderStamp::CHANNELS, true)) {
                throw PosActionException::conflict('not_pos_order', 'Bu buyurtma kassada hisob-kitob qilinmaydi (POS buyurtmasi emas).');
            }
            if ($bill->status === BillStatus::VOID) {
                throw PosActionException::conflict('bill_void', 'Bu schyot bekor qilingan: buyurtmaning joriy schyotini chiqaring.');
            }

            $approvedBy = $this->approvals->authorize($actor, Permission::POS_REPRINT_BILL, $order, $approvalToken, $device);
            // A manager who approved the copy also covers another waiter's order
            $approvedBy = app(\App\Domain\Pos\Orders\WaiterAccess::class)->guard($order, $actor, $approvedBy === null ? $approvalToken : null, $approvedBy) ?? $approvedBy;

            // Review P6 C4: a bill the order outgrew is not printed with its old total: the current one is
            if ($bill->status !== BillStatus::PAID && !$bill->payments()->exists() && $bill->basis !== BillableOrder::fingerprint($order)) {
                $split = OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->count() > 1;
                $current = $split ? null : $this->bills->ensureForPrint($actor, $order, $device);
                if ($current === null) {
                    throw PosActionException::conflict('bill_stale', "Schyot chiqqandan keyin buyurtma o'zgardi: yangi schyotni kassir tuzadi.");
                }

                return $this->announce($order, $current, Branch::findOrFail($order->branch_id), false, $actor);
            }

            $this->audit->record('pos.bill_reprinted', $order, [], [], array_filter([
                'bill' => $bill->uuid,
                'number' => $bill->number,
                'total' => $bill->total,
                'printed_count' => $bill->printed_count,
                'approved_by' => $approvedBy,
                'requested_from' => 'print_request',
            ], fn ($value) => $value !== null), ['actor' => $actor, 'device' => $device]);

            $branch = Branch::findOrFail($order->branch_id);

            return $this->announce($order, $bill, $branch, true, $actor);
        });

        return $announced;
    }

    /**
     * Whether the order changed after its bill was made: an open bill, nothing paid on it, made from another basis.
     */
    public static function outgrown(Order $order): bool
    {
        $bills = OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->get();
        if ($bills->isEmpty() || $bills->contains(fn (OrderBill $bill) => $bill->status === BillStatus::PAID || $bill->payments()->exists())) {
            return false;
        }
        $basis = BillableOrder::fingerprint($order);

        return $bills->contains(fn (OrderBill $bill) => $bill->basis !== $basis);
    }

    /**
     * @return array{request_id: string, printer_id: int|null, target_device: string|null}
     */
    private function announce(Order $order, OrderBill $bill, Branch $branch, bool $reprint, User $actor, ?string $requestId = null): array
    {
        [$printerId, $targetDevice] = $this->route($branch);
        $requestId = $requestId !== null && Str::isUuid($requestId) ? strtolower($requestId) : (string) Str::uuid();

        // Review P6 hardware/LAN (C2): kept for the till that misses the broadcast (offline, restarted, locked)
        app(BillPrintInbox::class)->record($order, (string) $bill->uuid, $requestId, $reprint, $printerId, $targetDevice, (int) $actor->id);
        event(new BillPrintRequestedBroadcast($order, (string) $bill->uuid, $requestId, $reprint, $printerId, $targetDevice, (int) $actor->id));

        return ['request_id' => $requestId, 'printer_id' => $printerId, 'target_device' => $targetDevice];
    }

    /**
     * @return array{0: int|null, 1: string|null} the printer id and the till's device uuid
     */
    private function route(Branch $branch): array
    {
        $settings = PosSettings::resolve($branch->pos_settings);
        $printingDevice = \App\Domain\Pos\Setup\PosBootstrap::activePrintingDevice($branch, $settings['printing_device_id']);

        $printer = $settings['bill_printer'] !== null
            ? Printer::query()->whereKey($settings['bill_printer'])->where('branch_id', $branch->id)->where('purpose', PrinterPurpose::RECEIPT->value)->where('is_active', true)->first()
            : null;

        if ($printer === null) {
            // The default: the printing till's own receipt printer, if it has one
            $printerDevice = $printingDevice !== null ? Device::where('uuid', $printingDevice)->value('id') : null;
            $printer = $printerDevice !== null
                ? Printer::query()->where('branch_id', $branch->id)->where('purpose', PrinterPurpose::RECEIPT->value)->where('is_active', true)->where('device_id', $printerDevice)->orderBy('id')->first()
                : null;
        }

        $target = $printer?->device_id !== null ? Device::where('id', $printer->device_id)->value('uuid') : $printingDevice;
        // Review P6 C3: no till named: the LAN hub prints (never every till of the branch)
        $target ??= app(\App\Domain\Pos\Lan\BranchLan::class)->hubOf($branch)?->uuid;

        return [$printer?->id !== null ? (int) $printer->id : null, is_string($target) && $target !== '' ? $target : null];
    }
}
