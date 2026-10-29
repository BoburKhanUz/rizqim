<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Shifts\OrderStamp;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Events\Broadcast\BillChangedBroadcast;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Making, splitting and printing an order's bills (POS_SPEC 2.9, 2.10, R17, L2):
 *
 *  - bills are made from what is left to bill (BillableOrder): bills already being paid stay as they are, the other
 *    bills (open or printed, no payment yet) are voided and made again, so adding a round after a bill was printed
 *    just means billing again;
 *  - one bill needs orders.manage or pos.take_payment; splitting needs pos.take_payment;
 *  - the first print hands the bill to the guest and locks the order (`bill_locked_at`: voids and discounts need a
 *    manager from then on, P3-12); printing again is a copy ("NUSXA") and needs pos.reprint_bill or a manager's
 *    approval;
 *  - every change moves the order's `version` (409 for a terminal that holds an older one) and is audited;
 *  - a waiter bills and prints only their own orders (WaiterAccess, POS_SPEC 11; another waiter's needs a manager's
 *    approval token for orders.manage).
 */
final class BillService
{
    public function __construct(
        private readonly BillSplitter $splitter,
        private readonly PermissionService $permissions,
        private readonly ManagerApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  'single'|'equal'|'items'|'by_guest'  $mode
     * @param  list<array{items: list<array{order_item_id: int, quantity: string}>, guests?: int|null}>  $billsSpec
     * @return list<OrderBill>
     *
     * @throws PosActionException
     */
    public function create(User $user, Order $order, string $mode, int $parts, array $billsSpec, int $version, ?Device $device, ?Closure $applied = null, ?string $approvalToken = null): array
    {
        $splits = ($mode === 'equal' && $parts > 1) || ($mode === 'items' && count($billsSpec) > 1) || $mode === 'by_guest';
        if ($splits && !$this->permissions->allows($user, Permission::POS_TAKE_PAYMENT, $order)) {
            throw PosActionException::forbidden('permission_denied', "Hisobni bo'lish uchun to'lov qabul qilish huquqi kerak.", ['permission' => Permission::POS_TAKE_PAYMENT->value]);
        }

        [$order, $bills, $voided] = DB::transaction(function () use ($user, $order, $mode, $parts, $billsSpec, $version, $device, $applied, $approvalToken) {
            $order = self::lockOpenOrder($order, $version);
            app(\App\Domain\Pos\Orders\WaiterAccess::class)->guard($order, $user, $approvalToken);
            self::assertWeighed($order);

            $voided = [];
            $payable = OrderBill::where('order_id', $order->id)
                ->whereIn('status', [BillStatus::OPEN->value, BillStatus::PRINTED->value])
                ->whereNotExists(fn ($payments) => $payments->selectRaw('1')->from('payments')->whereColumn('payments.bill_id', 'order_bills.id'))
                ->lockForUpdate()
                ->get();
            foreach ($payable as $old) {
                $old->forceFill(['status' => BillStatus::VOID->value, 'voided_at' => now()])->save();
                $voided[] = ['uuid' => $old->uuid, 'printed_count' => $old->printed_count, 'total' => $old->total];
            }

            $billable = BillableOrder::of($order);
            $plans = $this->splitter->plan($billable, $mode, $parts, $billsSpec);
            $bills = $this->splitter->save($order, $mode, $plans, $billable->basis, $user->id);

            OrderCloser::bumpVersion($order);
            $order->save();

            $this->audit->record('pos.bills_created', $order, [], [], array_filter([
                'mode' => $mode,
                'bills' => array_map(fn (OrderBill $bill) => ['uuid' => $bill->uuid, 'total' => $bill->total], $bills),
                'voided' => $voided ?: null,
            ]), ['actor' => $user, 'device' => $device]);

            if ($applied) {
                $applied();
            }

            return [$order, $bills, $voided];
        });

        event(new BillChangedBroadcast($order, null, $voided === [] ? 'created' : 'rebilled'));

        return $bills;
    }

    /**
     * Prints the bill: the first time locks the order, later times are copies ("NUSXA", pos.reprint_bill or a
     * manager's approval).
     *
     * @return array{bill: OrderBill, copy: bool, approved_by: int|null}
     *
     * @throws PosActionException
     */
    public function print(User $user, OrderBill $bill, ?string $approvalToken, ?Device $device, ?int $version, ?Closure $applied = null): array
    {
        $result = DB::transaction(function () use ($user, $bill, $approvalToken, $device, $version, $applied) {
            $order = Order::whereKey($bill->order_id)->lockForUpdate()->firstOrFail();
            $bill = OrderBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            self::checkVersion($order, $version);

            if ($bill->status === BillStatus::VOID) {
                throw PosActionException::conflict('bill_void', "Bu schyot bekor qilingan: buyurtmaning joriy schyotini chiqaring.");
            }
            if ($bill->status !== BillStatus::PAID) {
                self::assertWeighed($order);
            }

            $copy = $bill->printed_count > 0;
            $approvedBy = null;
            if ($copy) {
                $approvedBy = $this->approvals->authorize($user, Permission::POS_REPRINT_BILL, $order, $approvalToken, $device);
            }
            // P3-37: a copy too (cashiers print copies alone now): a bill the order outgrew is billed again, not reprinted
            if ($bill->status !== BillStatus::PAID && !$bill->payments()->exists() && $bill->basis !== BillableOrder::fingerprint($order)) {
                throw self::orderChanged();
            }
            // A manager who approved the copy also covers another waiter's order
            $approvedBy = app(\App\Domain\Pos\Orders\WaiterAccess::class)->guard($order, $user, $approvedBy === null ? $approvalToken : null, $approvedBy) ?? $approvedBy;

            $bill->forceFill([
                'printed_count' => $bill->printed_count + 1,
                'printed_at' => $bill->printed_at ?? now(),
                'status' => $bill->status === BillStatus::OPEN ? BillStatus::PRINTED->value : $bill->status->value,
            ])->save();

            if ($order->bill_locked_at === null) {
                $order->forceFill(['bill_locked_at' => now()]);
                OrderCloser::bumpVersion($order);
                $order->save();
            }

            $this->audit->record($copy ? 'pos.bill_reprinted' : 'pos.bill_printed', $order, [], [], array_filter([
                'bill' => $bill->uuid,
                'number' => $bill->number,
                'total' => $bill->total,
                'printed_count' => $bill->printed_count,
                'approved_by' => $approvedBy,
            ], fn ($value) => $value !== null), ['actor' => $user, 'device' => $device]);

            if ($applied) {
                $applied();
            }

            return ['bill' => $bill, 'copy' => $copy, 'approved_by' => $approvedBy, 'order' => $order];
        });

        event(new BillChangedBroadcast($result['order'], $result['bill'], 'printed'));

        return ['bill' => $result['bill'], 'copy' => $result['copy'], 'approved_by' => $result['approved_by']];
    }

    /**
     * The order, locked, open for billing (a POS order not closed or cancelled), at the version the terminal holds.
     *
     * @throws PosActionException
     */
    public static function lockOpenOrder(Order $order, ?int $version): Order
    {
        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

        if (!in_array($order->channel, OrderStamp::CHANNELS, true)) {
            throw PosActionException::conflict('not_pos_order', "Bu buyurtma kassada hisob-kitob qilinmaydi (POS buyurtmasi emas).");
        }
        if ($order->closed_at !== null || !in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true)) {
            throw PosActionException::conflict('order_closed', 'Buyurtma yopilgan yoki bekor qilingan.');
        }
        // Money a payment provider already receipted online (P3-14, subject `order`) never gets a second sale receipt
        if (\App\Models\FiscalReceipt::forSubject('order', (int) $order->id)->where('type', 'sale')->exists()) {
            throw PosActionException::conflict('order_receipted_online', "Bu buyurtma uchun onlayn to'lovda fiskal chek chiqqan: kassada qayta hisoblanmaydi.");
        }
        self::checkVersion($order, $version);

        return $order;
    }

    /**
     * P6-25: no bill is made or printed while a "weight later" line waits for its real weight (PosWeights): 409
     * `weight_pending` with the lines (`items`: id and name). Weighing or voiding the line frees the bill.
     *
     * @throws PosActionException
     */
    public static function assertWeighed(Order $order): void
    {
        $pending = \App\Models\OrderItem::query()
            ->where('order_id', $order->id)
            ->where('weight_status', \App\Domain\Pos\Orders\WeightStatus::AWAITING->value)
            ->where('kitchen_status', '!=', 'void')
            ->whereNull('voided_at')
            ->orderBy('id')
            ->get(['id', 'product_name', 'variant_name']);
        if ($pending->isEmpty()) {
            return;
        }

        throw PosActionException::conflict('weight_pending', "Og'irligi kiritilmagan taom bor: avval uni torting va og'irligini kiriting.", [
            'items' => $pending->map(fn (\App\Models\OrderItem $item) => [
                'id' => (int) $item->id,
                'name' => $item->product_name . ($item->variant_name ? " ({$item->variant_name})" : ''),
            ])->all(),
        ]);
    }

    /**
     * @throws PosActionException 409 with the current version when the terminal's is older
     */
    public static function checkVersion(Order $order, ?int $version): void
    {
        if ($version !== null && $version !== (int) $order->getAttribute('version')) {
            throw PosActionException::conflict('version_conflict', "Buyurtma boshqa qurilmada o'zgartirildi. Yangilab, qayta urinib ko'ring.", [
                'version' => (int) $order->getAttribute('version'),
            ]);
        }
    }

    public static function orderChanged(): PosActionException
    {
        return PosActionException::conflict('order_changed', "Schyot chiqqandan keyin buyurtma o'zgardi. Schyotni qayta tuzing.");
    }
}
