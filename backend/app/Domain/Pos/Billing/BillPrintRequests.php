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
 *    /bills/{uuid}/print on a copy), audited as `pos.bill_reprinted` with the requester.
 *
 * Which till prints: the printer is `bill_printer` (an active receipt printer of the branch), else the receipt printer
 * of the branch's printing till; the till is the device the printer belongs to, else the printing till (`target_device`
 * null when neither is set: every till of the branch prints).
 */
class BillPrintRequests
{
    public function __construct(
        private readonly BillService $bills,
        private readonly ManagerApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    public function afterBillRequested(Order $order, User $actor, ?Device $device): void
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

            $this->announce($order, $bill, $branch, false, $actor);
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
     * @return array{request_id: string, printer_id: int|null, target_device: string|null}
     */
    private function announce(Order $order, OrderBill $bill, Branch $branch, bool $reprint, User $actor): array
    {
        [$printerId, $targetDevice] = $this->route($branch);
        $requestId = (string) Str::uuid();

        event(new BillPrintRequestedBroadcast($order, (string) $bill->uuid, $requestId, $reprint, $printerId, $targetDevice, (int) $actor->id));

        return ['request_id' => $requestId, 'printer_id' => $printerId, 'target_device' => $targetDevice];
    }

    /**
     * @return array{0: int|null, 1: string|null} the printer id and the till's device uuid
     */
    private function route(Branch $branch): array
    {
        $settings = PosSettings::resolve($branch->pos_settings);
        $printingDevice = $settings['printing_device_id'];

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

        return [$printer?->id !== null ? (int) $printer->id : null, is_string($target) && $target !== '' ? $target : null];
    }
}
