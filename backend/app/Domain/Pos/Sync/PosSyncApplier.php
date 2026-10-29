<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Billing\BillFiscalizer;
use App\Domain\Pos\Billing\BillService;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\OrderCloser;
use App\Domain\Pos\Billing\PaymentService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Orders\PosLineBuilder;
use App\Domain\Pos\Orders\PosOrderException;
use App\Domain\Pos\Orders\PosOrderService;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Shifts\OrderStamp;
use App\Domain\Pos\Shifts\QueueNumbers;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\FireMode;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\PosSyncOperation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applies one operation from a till's offline queue (docs/POS_SPEC.md §5) through the POS domain services, so every
 * business rule stays where it is (PosOrderService, PosLineBuilder, BillService, PaymentService, OrderCloser,
 * BillFiscalizer), and records it in pos_sync_operations in the same transaction.
 *
 * The conflict rules (POS_SPEC 5.3) — the server is the source of truth, but a sale that happened and money taken are
 * never rejected:
 *  - the same operation twice (op_id) or the same order twice (external_id): applied once;
 *  - an offline price is kept and the order flagged `offline_price`; a menu snapshot older than 24 h makes it a
 *    conflict for the manager (`stale_menu`);
 *  - a kazan or stock that ran short: the sale is kept, the quantity never goes below zero, the shortfall is recorded
 *    (menu_batches.oversold_quantity) and shown (`oversold`, conflict);
 *  - an order that changed on the server so the operation no longer fits (closed or cancelled, a line sent or voided
 *    meanwhile, a room taken): rejected into "Sinxronlash xatolari" (a room taken: the order is opened without it and
 *    flagged instead, the guests were served);
 *  - money: recorded whatever happened; on a cancelled order the order is flagged `refund_needed`, on a bill it can
 *    no longer go to `payment_unallocated`; a bill paid less or more than the server's total is a conflict
 *    (`underpaid`, `overpaid`).
 */
final class PosSyncApplier
{
    /** A menu snapshot older than this makes an offline-priced order a conflict (POS_SPEC 5.3, 5.4). */
    public const STALE_MENU_HOURS = 24;

    /** How long before the sync a staff member's PIN session on the till may have started. */
    public const SESSION_WINDOW_HOURS = 36;

    /** Review P3-35: a device time older than this (the offline receipt's fiscal limit, POS_SPEC 4.6) is clamped. */
    public const DEVICE_TIME_MAX_AGE_HOURS = 48;

    /** Review P3-35: the most a payment column holds (decimal 12,2). */
    public const MAX_AMOUNT = '9999999999.99';

    public function __construct(
        private readonly PosOrderService $orders,
        private readonly PosLineBuilder $lines,
        private readonly BillService $bills,
        private readonly PaymentService $payments,
        private readonly BillFiscalizer $fiscalizer,
        private readonly OrderCloser $closer,
        private readonly ShiftService $shifts,
        private readonly PermissionService $permissions,
        private readonly AuditLogger $audit,
        private readonly PosOwnerAlerts $alerts,
    ) {}

    /**
     * @param  array{op_id: string, seq: int, type: string, occurred_at?: ?string, staff_id?: ?int, payload?: array<string, mixed>}  $op
     */
    public function apply(Device $device, array $op, ?int $clockSkew): PosSyncOperation
    {
        $received = now();
        $type = PosSyncOperationType::tryFrom((string) $op['type']);
        $occurredAt = self::time($op['occurred_at'] ?? null);
        // Review P3-35: what the device's clock said, corrected by the batch's skew and kept within reason, is when
        // it happened for the server (the order's offline_created_at, the payment's offline_paid_at → sold_at)
        [$happenedAt, $clamped] = $this->deviceTime($occurredAt, $clockSkew);
        $payload = (array) ($op['payload'] ?? []);
        $base = [
            'store_id' => $device->store_id,
            'branch_id' => $device->branch_id,
            'device_id' => $device->id,
            'op_id' => strtolower((string) $op['op_id']),
            'seq' => (int) $op['seq'],
            'type' => (string) $op['type'],
            'staff_id' => isset($op['staff_id']) ? (int) $op['staff_id'] : null,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
            'clock_skew_seconds' => $clockSkew,
            'received_at' => $received,
            'client_ref' => $this->clientRef($type, $payload),
        ];

        try {
            if ($type === null) {
                throw new PosSyncException('type_unknown', "Amal turi noma'lum: ilovani yangilang.");
            }
            $staff = $this->staff($device, $base['staff_id'], $type);
        } catch (PosSyncException $e) {
            // Nobody to attribute it to (or an unknown type): never applied, money included
            return $this->rejected($base, $e->reason, $e->getMessage());
        }

        $bills = [];
        try {
            return DB::transaction(function () use ($device, $type, $staff, $payload, $happenedAt, $clamped, $base, &$bills) {
                $outcome = $this->run($device, $type, $staff, $payload, $happenedAt);
                $bills = $outcome['bills'] ?? [];
                if ($clamped !== null) {
                    $outcome['flags'] = [...($outcome['flags'] ?? []), $clamped];
                    $outcome['result']['flags'] = [...($outcome['result']['flags'] ?? []), $clamped];
                }

                return $this->record($base, $outcome);
            });
        } catch (PosSyncException|PosOrderException|PosActionException|ValidationException|FiscalException|\App\Domain\Pos\Booking\BookingException|\App\Domain\Pos\Accounts\CustomerAccountException $e) {
            [$reason, $message] = $this->describe($e);

            // Money taken is never rejected: it is recorded on the order as it is now
            return $type->isMoney()
                ? $this->unallocatedOrRejected($device, $staff, $payload, $happenedAt, $base, $reason, $message)
                : $this->rejected($base, $reason, $message);
        } catch (Throwable $e) {
            // Review P3-35: the database failing (a deadlock, a lost connection) stops the batch, the operation comes
            // again; anything else is this operation's own content (a value no column holds...): it would fail the
            // same way every time and block the till's queue for good, so it is answered instead
            if (TransientDatabaseError::is($e)) {
                throw $e;
            }
            report($e);
            $message = "Amal ma'lumotlari serverda qo'llanmadi (noto'g'ri yoki juda katta qiymat).";

            return $type->isMoney()
                ? $this->unallocatedOrRejected($device, $staff, $payload, $happenedAt, $base, 'invalid_payload', $message)
                : $this->rejected($base, 'invalid_payload', $message);
        } finally {
            // After the commit: the sale receipts of bills this operation covered (the sandbox or a provider answers
            // at once; an unreachable one stays "fiskal kutmoqda" and is retried)
            foreach ($bills as $bill) {
                $this->fiscalizer->issue($bill);
            }
        }
    }

    /**
     * Money that cannot go through its bill: recorded unallocated (unallocated()); when even that fails on the
     * operation's own content, rejected for the manager with the tenders as sent.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $base
     */
    private function unallocatedOrRejected(Device $device, User $staff, array $payload, ?Carbon $happenedAt, array $base, string $reason, string $message): PosSyncOperation
    {
        try {
            return $this->unallocated($device, $staff, $payload, $happenedAt, $base, $reason, $message);
        } catch (Throwable $e) {
            if (TransientDatabaseError::is($e)) {
                throw $e;
            }
            report($e);

            return PosSyncOperation::create($base + [
                'status' => PosSyncStatus::REJECTED->value,
                'reason' => 'invalid_payload',
                'error' => "To'lov serverga yozilmadi (ma'lumotlari noto'g'ri). Pulni menejer qo'lda tekshirsin.",
                'result' => ['tenders' => $payload['tenders'] ?? []],
            ]);
        }
    }

    /**
     * The device's time of an operation for the server: corrected by the batch's clock skew when that is more than the
     * warning threshold, and kept within [now − 48 h, now] (the fiscal limit for an offline receipt, POS_SPEC 4.6).
     * Returns the time and, when it was moved by more than the threshold, a `device_time_clamped` flag (informational).
     *
     * @return array{0: Carbon|null, 1: array<string, mixed>|null}
     */
    private function deviceTime(?Carbon $occurredAt, ?int $skew): array
    {
        if ($occurredAt === null) {
            return [null, null];
        }

        $time = $occurredAt->copy();
        if ($skew !== null && abs($skew) > PosSyncService::CLOCK_SKEW_WARN_SECONDS) {
            $time = $time->addSeconds($skew);
        }
        $floor = now()->subHours(self::DEVICE_TIME_MAX_AGE_HOURS);
        $ceiling = now();
        $clamped = $time->lt($floor) ? $floor : ($time->gt($ceiling) ? $ceiling : $time);

        return [$clamped, abs($clamped->diffInSeconds($time)) > PosSyncService::CLOCK_SKEW_WARN_SECONDS ? self::clampFlag($occurredAt, $clamped) : null];
    }

    /**
     * @return array<string, mixed>
     */
    private static function clampFlag(Carbon $deviceTime, Carbon $usedTime): array
    {
        return ['flag' => 'device_time_clamped', 'device_time' => $deviceTime->toJSON(), 'used_time' => $usedTime->toJSON()];
    }

    /**
     * A date the device sent, null when it is none (never a 500 for the whole batch).
     */
    private static function time(mixed $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function rejected(array $base, string $reason, string $message): PosSyncOperation
    {
        return PosSyncOperation::create($base + [
            'status' => PosSyncStatus::REJECTED->value,
            'reason' => $reason,
            'error' => $message,
            'result' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status?: PosSyncStatus, reason?: ?string, error?: ?string, result: array<string, mixed>, order?: ?Order, bills?: list<OrderBill>, flags?: list<array<string, mixed>>}
     */
    private function run(Device $device, PosSyncOperationType $type, User $staff, array $payload, ?Carbon $occurredAt): array
    {
        $replayed = $this->replayed($type, $staff, $payload);
        if ($replayed !== null && $type !== PosSyncOperationType::ORDER_OPEN) {
            // The till sent this very action online, the server did it, the answer was lost in the outage
            $order = $this->posOrder($device, $payload);

            return $this->finish($order, [], $payload, ['replayed' => true] + $replayed);
        }

        return match ($type) {
            PosSyncOperationType::ORDER_OPEN => $this->open($device, $staff, $payload, $occurredAt),
            PosSyncOperationType::ORDER_ROUND => $this->round($device, $staff, $payload),
            PosSyncOperationType::ORDER_SEND => $this->send($device, $staff, $payload),
            PosSyncOperationType::ORDER_VOID => $this->void($device, $staff, $payload),
            PosSyncOperationType::ORDER_SERVED => $this->served($device, $staff, $payload),
            PosSyncOperationType::BILL_CREATE => $this->bill($device, $staff, $payload),
            PosSyncOperationType::BILL_PAY => $this->pay($device, $staff, $payload, $occurredAt),
            PosSyncOperationType::ORDER_CLOSE => $this->close($device, $staff, $payload),
        };
    }

    // --- operations -------------------------------------------------------------------------------------------------

    /**
     * order.open: {order (the device's uuid, stored as external_id), local_number, queue_number? (P6-13), channel, table_id?, guests_count?,
     * waiter_id?, note?, items?, send?, menu_taken_at?}
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function open(Device $device, User $staff, array $payload, ?Carbon $occurredAt): array
    {
        $clientId = $this->uuidOf($payload['order'] ?? null, 'order');
        $channel = OrderChannel::tryFrom((string) ($payload['channel'] ?? ''));
        if (!in_array($channel, OrderStamp::CHANNELS, true)) {
            throw new PosSyncException('channel_invalid', "Buyurtma kanali noto'g'ri (zal yoki olib ketish).");
        }
        $branch = $this->branch($device);

        $existing = Order::where('store_id', $device->store_id)->where('external_id', $clientId)->first();
        if ($existing !== null) {
            // The same order from an operation that was lost on the way back: one order
            return ['result' => $this->refs($existing), 'order' => $existing];
        }

        $flags = [];
        $table = null;
        $tableId = isset($payload['table_id']) ? (int) $payload['table_id'] : null;
        if ($tableId !== null && $channel === OrderChannel::POS_HALL) {
            $table = DiningTable::where('branch_id', $branch->id)->find($tableId);
            if ($table === null) {
                $flags[] = ['flag' => 'table_missing', 'table_id' => $tableId];
            }
        }

        $guests = max($channel === OrderChannel::POS_HALL ? 1 : 0, (int) ($payload['guests_count'] ?? 0));
        $waiterId = isset($payload['waiter_id']) ? (int) $payload['waiter_id'] : null;
        $options = ['external_id' => $clientId, 'note' => $this->text($payload['note'] ?? null, 500)];
        // P6-13: the queue number the till printed from its block (QueueNumbers keeps it when it is allowed); without
        // the queue_numbers module there are no blocks and no talon: a proposed number is ignored (review P6 C#L4)
        $queueNumber = isset($payload['queue_number']) && is_int($payload['queue_number']) && $payload['queue_number'] > 0 && $payload['queue_number'] <= 999999
            && QueueNumbers::numbersOn($branch) ? $payload['queue_number'] : null;
        $options['queue_number'] = $queueNumber;
        // Review P6 C#M1: the business day the order was taken on (an order taken before the day's start and synced
        // after it stays in that day, numbered from that day's blocks), not the day it reached the server
        if ($occurredAt !== null) {
            $options['business_date'] = BusinessDay::of($branch, $occurredAt);
        }

        try {
            $order = $this->orders->openOrder($branch, $channel, $table, $guests, $waiterId, (int) $device->id, $staff, $options);
        } catch (PosOrderException $e) {
            if (!in_array($e->reason, ['table_occupied', 'table_inactive'], true) || $table === null) {
                throw $e;
            }
            // The guests sat there and were served: the order opens without the room, the manager seats it
            $order = $this->orders->openOrder($branch, $channel, null, $guests, $waiterId, (int) $device->id, $staff, $options);
            $order->forceFill(['hall_ref' => $table->hall?->name, 'table_ref' => $table->name])->save();
            $flags[] = ['flag' => $e->reason, 'table' => $table->name, 'message' => $e->getMessage()];
        } catch (ValidationException) {
            // An unknown waiter: the order is the staff member's
            $order = $this->orders->openOrder($branch, $channel, $table, $guests, null, (int) $device->id, $staff, $options);
        }

        if ($queueNumber !== null && (int) $order->queue_number !== $queueNumber) {
            // The talon in the customer's hand shows another number (another order took it, or it was not this
            // till's block for the day): the order keeps the server's number, the manager sees both
            $flags[] = ['flag' => 'queue_number_taken', 'queue_number' => $queueNumber, 'assigned' => $order->queue_number];
        }

        $localNumber = $this->text($payload['local_number'] ?? null, 32);
        if ($localNumber !== null && Order::where('store_id', $device->store_id)->where('local_number', $localNumber)->exists()) {
            $flags[] = ['flag' => 'local_number_taken', 'local_number' => $localNumber];
            $localNumber = null;
        }
        $order->forceFill([
            'local_number' => $localNumber,
            'offline_created_at' => $occurredAt,
            'synced_at' => now(),
        ])->save();

        $items = $this->lineInputs($payload['items'] ?? []);
        $roundFlags = [];
        $sent = [];
        if ($items !== []) {
            $round = $this->orders->offlineRound($order, $staff, $items, (bool) ($payload['send'] ?? false), 1, self::time($payload['menu_taken_at'] ?? null));
            $this->priceAlert($order, $round['flags']);
            $order = $round['order'];
            $roundFlags = $round['flags'];
            $sent = $round['sent'];
        }

        return $this->finish($order, [...$flags, ...$roundFlags], $payload, ['sent' => $sent]);
    }

    /**
     * order.round: {order, items, send?}
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function round(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);
        $items = $this->lineInputs($payload['items'] ?? []);
        if ($items === []) {
            throw new PosSyncException('items_required', "Bosqichda taom yo'q.");
        }

        $round = $this->orders->offlineRound($order, $staff, $items, (bool) ($payload['send'] ?? false), null, self::time($payload['menu_taken_at'] ?? null));
        $this->priceAlert($round['order'], $round['flags']);

        return $this->finish($round['order'], $round['flags'], $payload, ['sent' => $round['sent']]);
    }

    /**
     * order.send: {order, lines?} — the lines still waiting (the device's line ids or the server's item ids); without
     * lines every unsent `now` line. Lines the kitchen already has are skipped.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);
        $ids = null;
        if (isset($payload['lines']) && is_array($payload['lines'])) {
            $ids = [];
            foreach ($payload['lines'] as $ref) {
                $item = $this->item($order, $ref, false);
                if ($item !== null && $item->kitchen_status === KitchenItemStatus::PENDING && $item->sent_at === null) {
                    $ids[] = (int) $item->id;
                }
            }
            if ($ids === []) {
                // Sent from another device meanwhile, or voided: nothing left to do
                return $this->finish($order, [], $payload, ['sent' => []]);
            }
        } elseif (!OrderItem::where('order_id', $order->id)->where('kitchen_status', KitchenItemStatus::PENDING->value)->whereNull('sent_at')->where('fire_mode', FireMode::NOW->value)->exists()) {
            return $this->finish($order, [], $payload, ['sent' => []]);
        }

        $order = $this->orders->send($order, (int) $order->version, $staff, $ids);

        return $this->finish($order, [], $payload);
    }

    /**
     * order.void: {order, line, reason?} — a line not sent yet (offline nobody can approve a void after sending).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function void(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);
        $item = $this->item($order, $payload['line'] ?? null);
        if ($item->isVoid()) {
            return $this->finish($order, [], $payload);
        }
        if ($item->sent_at !== null) {
            throw new PosSyncException('line_sent', "Taom shu orada oshxonaga yuborilgan: uni o'chirishni menejer onlayn qilsin.", ['item_id' => (int) $item->id]);
        }

        $order = $this->orders->void($order, (int) $order->version, $staff, (int) $item->id, $this->text($payload['reason'] ?? null, 255), null);

        return $this->finish($order, [], $payload);
    }

    /**
     * order.served: {order, line}
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function served(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);
        $item = $this->item($order, $payload['line'] ?? null);
        if ($item->kitchen_status === KitchenItemStatus::SERVED) {
            return $this->finish($order, [], $payload);
        }

        $order = $this->orders->serve($order, (int) $order->version, $staff, (int) $item->id);

        return $this->finish($order, [], $payload);
    }

    /**
     * bill.create: {order, bill (the device's id), expected_total?} — one bill of what is left to bill.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function bill(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);
        $this->uuidOf($payload['bill'] ?? null, 'bill');

        $bills = $this->bills->create($staff, $order, 'single', 1, [], (int) $order->version, $device);
        $bill = $bills[0];
        $flags = [];
        $expected = isset($payload['expected_total']) ? Money::of((string) $payload['expected_total']) : null;
        if ($expected !== null && !$expected->equals($bill->total)) {
            $flags[] = ['flag' => 'amount_mismatch', 'device_total' => $expected->toString(), 'server_total' => Money::of($bill->total)->toString()];
        }

        return $this->finish($order->refresh(), $flags, $payload, ['bill_uuid' => $bill->uuid, 'bill_total' => Money::of($bill->total)->toString()], informational: true);
    }

    /**
     * bill.pay: {order, bill (the device's id or a server bill uuid), shift_uuid?, tenders: [{tender: cash|card, amount,
     * tendered_amount?, card_type?, transaction_id?, card_last4?}]} — the money as the till took it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pay(Device $device, User $staff, array $payload, ?Carbon $occurredAt): array
    {
        $order = $this->posOrder($device, $payload);
        if (!in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true) || $order->closed_at !== null) {
            throw new PosSyncException('order_closed', $order->status === OrderStatus::CANCELED
                ? "Buyurtma serverda bekor qilingan."
                : 'Buyurtma serverda yopilgan.');
        }
        $bill = $this->billOf($device, $order, $payload['bill'] ?? null);
        $tenders = $this->tenders($payload['tenders'] ?? []);

        $paid = Money::sum(...Payment::where('bill_id', $bill->id)->where('status', 'paid')->pluck('amount')->all());
        $due = Money::of($bill->total)->minus($paid);
        $taken = Money::sum(...array_map(fn (array $tender) => Money::of($tender['amount']), $tenders));
        if (!$due->isPositive()) {
            throw new PosSyncException('bill_paid', "Schyot shu orada to'langan.");
        }

        $flags = [];
        $applied = $tenders;
        if ($taken->isGreaterThan($due)) {
            $applied = $this->fit($tenders, $due);
            $flags[] = ['flag' => 'overpaid', 'amount' => $taken->minus($due)->toString(), 'server_total' => Money::of($bill->total)->toString()];
        } elseif ($taken->isLessThan($due)) {
            $flags[] = ['flag' => 'underpaid', 'amount' => $due->minus($taken)->toString(), 'server_total' => Money::of($bill->total)->toString()];
        }

        $shift = $this->openShiftOf($device, $payload['shift_uuid'] ?? null);
        $paidAt = $occurredAt ?? now();
        if ($shift !== null && $paidAt->lt($shift->opened_at)) {
            // Review P3-35: never before the shift the money lands in was opened
            $flags[] = self::clampFlag($paidAt, $shift->opened_at->copy());
            $paidAt = $shift->opened_at->copy();
        }
        $bill = $this->payments->pay($staff, $bill, $applied, null, $shift?->uuid, null, $device, null, $paidAt);
        $paymentUuids = Payment::where('bill_id', $bill->id)->where('offline_paid_at', '!=', null)->orderBy('id')->pluck('uuid')->all();

        return $this->finish(Order::findOrFail($order->id), $flags, $payload, [
            'bill_uuid' => $bill->uuid,
            'payments' => $paymentUuids,
            'fiscal_status' => $bill->getAttribute('fiscal_status'),
        ]) + ['bills' => [$bill]];
    }

    /**
     * order.close: {order} — closes it once every bill is paid and receipted (a pending receipt closes it later).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function close(Device $device, User $staff, array $payload): array
    {
        $order = $this->posOrder($device, $payload);
        $closed = $order->closed_at !== null;
        if (!$closed && $order->status !== OrderStatus::CANCELED) {
            $closed = DB::transaction(function () use ($order, $staff) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                return $this->closer->closeIfSettled($locked, $staff) || $locked->closed_at !== null;
            });
        }

        return $this->finish(Order::findOrFail($order->id), [], $payload, ['closed' => $closed]);
    }

    /**
     * Money that cannot go through its bill (the order was cancelled or closed, the bill is gone or was paid, the
     * payment refused): recorded on the order as it was taken, in the till's shift (cash in its drawer), the order
     * flagged for the manager (`refund_needed` on a cancelled order, else `payment_unallocated`). Rejected only when
     * there is no order to put it on.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $base
     */
    private function unallocated(Device $device, User $staff, array $payload, ?Carbon $occurredAt, array $base, string $reason, string $message): PosSyncOperation
    {
        try {
            $order = $this->posOrder($device, $payload);
            $tenders = $this->tenders($payload['tenders'] ?? []);
        } catch (PosSyncException $e) {
            return PosSyncOperation::create($base + [
                'status' => PosSyncStatus::REJECTED->value,
                'reason' => $e->reason,
                'error' => "To'lov serverga yozilmadi: {$e->getMessage()} Pulni menejer qo'lda tekshirsin.",
                'result' => ['tenders' => $payload['tenders'] ?? []],
            ]);
        }

        return DB::transaction(function () use ($device, $staff, $order, $tenders, $occurredAt, $base, $reason, $message) {
            // The shift's row first, as payments and the Z take it (review P3-35): a shift closed meanwhile takes none
            $shift = $this->openShiftOf($device, $base['payload']['shift_uuid'] ?? null);
            $shift = $shift !== null ? PosShift::lockForMoney((int) $shift->id) : null;
            if ($shift !== null && !$shift->isOpen()) {
                $shift = null;
            }
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($shift !== null && $occurredAt !== null && $occurredAt->lt($shift->opened_at)) {
                $occurredAt = $shift->opened_at->copy();
            }

            $created = [];
            foreach ($tenders as $tender) {
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'bill_id' => null,
                    'shift_id' => $shift?->id,
                    'device_id' => $device->id,
                    'received_by' => $staff->id,
                    'provider' => $tender['tender'],
                    'amount' => Money::of($tender['amount'])->toString(),
                    'status' => 'paid',
                    'paid_at' => now(),
                    'tendered_amount' => isset($tender['tendered_amount']) ? Money::of($tender['tendered_amount'])->toString() : null,
                    'change_amount' => isset($tender['tendered_amount']) ? Money::of($tender['tendered_amount'])->minus($tender['amount'])->toString() : null,
                    'card_type' => $tender['card_type'] ?? null,
                    'transaction_id' => $tender['transaction_id'] ?? null,
                    'card_last4' => $tender['card_last4'] ?? null,
                    'offline_paid_at' => $occurredAt ?? now(),
                    'synced_at' => now(),
                ]);
                if ($shift !== null && $tender['tender'] === PosTender::CASH->value) {
                    $this->shifts->recordSale($shift, $payment, $staff, $device);
                }
                $created[] = $payment;
            }

            $flag = $order->status === OrderStatus::CANCELED ? 'refund_needed' : 'payment_unallocated';
            $amount = Money::sum(...array_map(fn (Payment $payment) => Money::of($payment->amount), $created));
            $flags = [['flag' => $flag, 'amount' => $amount->toString(), 'reason' => $reason, 'message' => $message]];
            $this->flag($order, $flags, (string) $base['op_id']);

            $this->audit->record('pos.offline_payment_unallocated', $order, [], [], [
                'amount' => $amount->toString(),
                'reason' => $reason,
                'payments' => array_map(fn (Payment $payment) => $payment->uuid, $created),
            ], ['actor' => $staff, 'device' => $device]);

            return $this->record($base, [
                'status' => PosSyncStatus::CONFLICT,
                'reason' => $flag,
                'error' => $flag === 'refund_needed'
                    ? "Buyurtma bekor qilingan, lekin offlayn {$amount->toString()} so'm olingan: pul yozildi, mijozga qaytarish kerak."
                    : "To'lov ({$amount->toString()} so'm) schyotga tushmadi: {$message} Pul yozildi, menejer tekshirsin.",
                'result' => $this->refs($order) + ['payments' => array_map(fn (Payment $payment) => $payment->uuid, $created), 'flags' => $flags],
                'order' => $order,
            ]);
        });
    }

    // --- helpers ----------------------------------------------------------------------------------------------------

    /** The Idempotency-Key scope of the online endpoint each operation stands for. */
    private const SCOPES = [
        'order.open' => 'pos.orders.open',
        'order.round' => 'pos.orders.rounds',
        'order.send' => 'pos.orders.send',
        'order.void' => 'pos.orders.void',
        'order.served' => 'pos.orders.served',
        'bill.create' => 'pos.bills.create',
        'bill.pay' => 'pos.bills.pay',
        'order.close' => 'pos.orders.close',
    ];

    /**
     * When the operation carries the Idempotency-Key of an online request the staff member made just before the
     * outage (`idempotency_key`) and the server did that request: what it answered (a bill's uuid), else null.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function replayed(PosSyncOperationType $type, User $staff, array $payload): ?array
    {
        $key = $payload['idempotency_key'] ?? null;
        if (!is_string($key) || $key === '') {
            return null;
        }

        $row = \App\Models\IdempotencyKey::query()
            ->where('scope', self::SCOPES[$type->value])
            ->where('actor_type', 'user')
            ->where('actor_id', $staff->id)
            ->where('key', $key)
            ->where(fn ($query) => $query->whereNull('locked_until')->orWhereBetween('response_status', [200, 299]))
            ->first();
        if ($row === null) {
            return null;
        }

        $body = json_decode((string) $row->getAttribute('response_body'), true);
        $bill = is_array($body) ? ($body['data']['bills'][0]['uuid'] ?? $body['data']['bill']['uuid'] ?? null) : null;

        return is_string($bill) ? ['bill_uuid' => $bill] : [];
    }

    /**
     * The operation's result: the order's refs, the flags (stored on the order too), the status — conflict when a
     * flag needs a manager. Flags that only inform (an offline price on a fresh menu, a bill total that differs, to be
     * settled by the payment) keep it applied.
     *
     * @param  list<array<string, mixed>>  $flags
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function finish(Order $order, array $flags, array $payload, array $extra = [], bool $informational = false): array
    {
        $menuTakenAt = self::time($payload['menu_taken_at'] ?? null);
        $offlinePrice = array_filter($flags, fn (array $flag) => $flag['flag'] === 'offline_price') !== [];
        if ($offlinePrice && $menuTakenAt !== null && $menuTakenAt->lt(now()->subHours(self::STALE_MENU_HOURS))) {
            $flags[] = ['flag' => 'stale_menu', 'menu_taken_at' => $menuTakenAt->toJSON()];
        }

        $attention = array_values(array_filter($flags, fn (array $flag) => !in_array($flag['flag'], ['offline_price', 'amount_mismatch', 'local_number_taken', 'device_time_clamped'], true)));
        $status = $attention !== [] && !$informational ? PosSyncStatus::CONFLICT : PosSyncStatus::APPLIED;

        return [
            'status' => $status,
            'reason' => $status === PosSyncStatus::CONFLICT ? (string) $attention[0]['flag'] : null,
            'error' => $status === PosSyncStatus::CONFLICT ? $this->flagMessage($attention[0]) : null,
            'result' => $this->refs($order) + $extra + ($flags !== [] ? ['flags' => $flags] : []),
            'order' => $order,
            'flags' => $flags,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $outcome
     */
    private function record(array $base, array $outcome): PosSyncOperation
    {
        /** @var Order|null $order */
        $order = $outcome['order'] ?? null;
        if ($order !== null && !empty($outcome['flags'])) {
            $this->flag($order, $outcome['flags'], (string) $base['op_id']);
        }
        $status = $outcome['status'] ?? PosSyncStatus::APPLIED;

        return PosSyncOperation::create($base + [
            'status' => $status->value,
            'reason' => $outcome['reason'] ?? null,
            'error' => $outcome['error'] ?? null,
            'result' => $outcome['result'] ?? null,
            'order_id' => $order?->id,
            'applied_at' => now(),
        ]);
    }

    /**
     * Adds flags to the order's sync_flags (each with the operation that raised it).
     *
     * @param  list<array<string, mixed>>  $flags
     */
    private function flag(Order $order, array $flags, string $opId): void
    {
        $existing = (array) ($order->sync_flags ?? []);
        foreach ($flags as $flag) {
            $existing[] = $flag + ['op_id' => $opId, 'at' => now()->toJSON()];
        }
        $order->forceFill(['sync_flags' => $existing])->save();
    }

    /**
     * @param  array<string, mixed>  $flag
     */
    private function flagMessage(array $flag): string
    {
        return match ($flag['flag']) {
            'oversold' => "Ortiqcha sotilgan: '{$flag['name']}' — {$flag['quantity']} (qozonda yetmagan). Qoldiq 0 da qoldi.",
            'stock_short' => "Qoldiqdan ko'p sotilgan: '{$flag['name']}' — {$flag['quantity']}.",
            'sold_while_unavailable' => "'{$flag['name']}' offlayn sotilgan, lekin serverda u hozir sotilmaydi (stop-list yoki yashirilgan).",
            'stale_menu' => "Narxlar 24 soatdan eski menyu nusxasidan olingan: menejer ko'rib chiqsin.",
            'table_occupied', 'table_inactive' => "Joy ({$flag['table']}) serverda band yoki yopilgan edi: buyurtma joysiz ochildi, menejer joyini tanlasin.",
            'table_missing' => 'Stol yoki xona serverda topilmadi: buyurtma joysiz ochildi.',
            // P6-13
            'queue_number_taken' => "Talondagi raqam {$flag['queue_number']} band yoki bu kassaning bugungi oralig'ida emas: buyurtmaga {$flag['assigned']} raqami berildi. Mijozga yangi raqamni ayting.",
            'underpaid' => "Schyot serverda {$flag['server_total']} so'm: {$flag['amount']} so'm kam to'langan.",
            'overpaid' => "Schyot serverda {$flag['server_total']} so'm: {$flag['amount']} so'm ortiqcha olingan.",
            'offline_price_mismatch' => "'{$flag['name']}' kassada {$flag['device_price']} so'mdan sotilgan, lekin menyu nusxasi vaqtida bunday narx bo'lmagan: server narxi ({$flag['server_price']} so'm) qo'yildi. Menejer tekshirsin.",
            default => 'Offlayn amal serverda farq bilan qo\'llandi: menejer tekshirsin.',
        };
    }

    /**
     * Review P3-35: a till that sold below a price the dish ever had while its menu was taken tells the owner (the
     * server's price was applied; the manager sees the conflict in "Sinxronlash xatolari").
     *
     * @param  list<array<string, mixed>>  $flags
     */
    private function priceAlert(Order $order, array $flags): void
    {
        $lower = array_values(array_filter($flags, fn (array $flag) => $flag['flag'] === 'offline_price_mismatch'
            && Money::of((string) $flag['device_price'])->isLessThan((string) $flag['server_price'])));
        if ($lower === []) {
            return;
        }

        $lines = array_map(fn (array $flag) => "• {$flag['name']}: kassada " . PosOwnerAlerts::sum((string) $flag['device_price'])
            . ', menyuda ' . PosOwnerAlerts::sum((string) $flag['server_price']), $lower);
        $this->alerts->send((int) $order->store_id, "⚠️ Offlayn sotuvda narx menyudagidan past: buyurtma {$order->short_code}"
            . ($order->local_number ? " ({$order->local_number})" : '') . "\n" . implode("\n", $lines)
            . "\nServer narxi qo'yildi, menejer tekshirsin.");
    }

    /**
     * @return array<string, mixed>
     */
    private function refs(Order $order): array
    {
        return [
            'order_uuid' => (string) $order->uuid,
            'order_version' => (int) $order->version,
            'order_status' => $order->status->value,
            'local_number' => $order->local_number,
            // P6-13: the order's queue number on the server (the talon's, unless flagged queue_number_taken)
            'queue_number' => $order->queue_number !== null ? (int) $order->queue_number : null,
            'items' => OrderItem::where('order_id', $order->id)->whereNotNull('client_line_id')->orderBy('id')
                ->get(['id', 'client_line_id'])
                ->mapWithKeys(fn (OrderItem $item) => [(string) $item->getAttribute('client_line_id') => (int) $item->id])
                ->all(),
        ];
    }

    /**
     * The staff member the operation is attributed to: this store's, working on the till's branch, in a terminal role,
     * signed in on this till with their PIN before the outage (a session started there in the last 36 hours), holding
     * the operation's permission.
     */
    private function staff(Device $device, ?int $staffId, PosSyncOperationType $type): User
    {
        $staff = $staffId !== null ? User::where('store_id', $device->store_id)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::terminalRoles()))
            ->when($device->branch_id !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $device->branch_id)))
            ->find($staffId) : null;
        if ($staff === null) {
            throw new PosSyncException('staff_invalid', "Xodim topilmadi yoki bu kassada ishlamaydi.");
        }

        $signedIn = AuditLog::query()
            ->where('action', 'staff.session_started')
            ->where('user_id', $staff->id)
            ->where('device_id', $device->id)
            ->where('created_at', '>=', now()->subHours(self::SESSION_WINDOW_HOURS))
            ->exists();
        if (!$signedIn) {
            throw new PosSyncException('staff_not_signed_in', "{$staff->name} bu kassada uzilishdan oldin PIN bilan kirmagan.");
        }

        foreach ($type->permissions() as $permission) {
            if ($this->permissions->allows($staff, $permission, $device)) {
                return $staff;
            }
        }

        throw new PosSyncException('permission_denied', "{$staff->name} uchun bu amalga ruxsat yo'q.", ['permission' => $type->permissions()[0]->value]);
    }

    private function branch(Device $device): Branch
    {
        if ($device->branch_id === null) {
            throw new PosSyncException('terminal_without_branch', 'Kassa filialga ulanmagan.');
        }

        return Branch::findOrFail($device->branch_id);
    }

    /**
     * The POS order an operation names (`order`: the device's id of an order opened offline, or the server uuid of one
     * it had before the outage), in the till's branch.
     *
     * @param  array<string, mixed>  $payload
     */
    private function posOrder(Device $device, array $payload): Order
    {
        $ref = $this->uuidOf($payload['order'] ?? null, 'order');
        $order = Order::query()
            ->where('store_id', $device->store_id)
            ->when($device->branch_id !== null, fn ($query) => $query->where('branch_id', $device->branch_id))
            ->whereIn('channel', array_map(fn (OrderChannel $channel) => $channel->value, OrderStamp::CHANNELS))
            ->where(fn ($query) => $query->where('external_id', $ref)->orWhere('uuid', $ref))
            ->first();
        if ($order === null) {
            throw new PosSyncException('order_not_found', "Buyurtma serverda topilmadi (ochilishi rad etilgan bo'lishi mumkin).");
        }

        return $order;
    }

    /**
     * The order, still open on the server (POS_SPEC 5.3: a closed or cancelled order rejects the operation).
     *
     * @param  array<string, mixed>  $payload
     */
    private function openOrder(Device $device, array $payload): Order
    {
        $order = $this->posOrder($device, $payload);
        if (!in_array($order->status, PosOrderService::OPEN_STATUSES, true)) {
            throw new PosSyncException('order_closed', $order->status === OrderStatus::CANCELED
                ? 'Buyurtma shu orada serverda bekor qilingan.'
                : 'Buyurtma shu orada serverda yopilgan.', ['order_status' => $order->status->value]);
        }

        return $order;
    }

    /**
     * A line by the device's id (uuid) or the server's item id.
     */
    private function item(Order $order, mixed $ref, bool $required = true): ?OrderItem
    {
        $query = OrderItem::where('order_id', $order->id);
        $item = is_int($ref) || (is_string($ref) && ctype_digit($ref))
            ? $query->whereKey((int) $ref)->first()
            : (is_string($ref) && Str::isUuid($ref) ? $query->where('client_line_id', strtolower($ref))->first() : null);
        if ($item === null && $required) {
            throw new PosSyncException('line_not_found', "Taom qatori serverda topilmadi.");
        }

        return $item;
    }

    /**
     * The bill a payment names: the one the device made offline (its bill.create operation) or a server bill.
     */
    private function billOf(Device $device, Order $order, mixed $ref): OrderBill
    {
        $ref = $this->uuidOf($ref, 'bill');
        $made = PosSyncOperation::where('device_id', $device->id)
            ->where('type', PosSyncOperationType::BILL_CREATE->value)
            ->where('client_ref', $ref)
            ->where('status', '!=', PosSyncStatus::REJECTED->value)
            ->first();
        $uuid = $made !== null ? (string) ($made->result['bill_uuid'] ?? '') : $ref;

        $bill = OrderBill::where('order_id', $order->id)->where('uuid', $uuid)->first();
        if ($bill === null) {
            throw new PosSyncException('bill_not_found', 'Schyot serverda topilmadi.');
        }
        if ($bill->status === BillStatus::VOID) {
            throw new PosSyncException('bill_void', "Schyot shu orada bekor qilingan (buyurtma o'zgargan).");
        }
        if ($bill->status === BillStatus::PAID) {
            throw new PosSyncException('bill_paid', "Schyot shu orada to'langan.");
        }

        return $bill;
    }

    private function openShiftOf(Device $device, mixed $uuid): ?PosShift
    {
        if (is_string($uuid) && Str::isUuid($uuid)) {
            $named = PosShift::where('uuid', $uuid)->where('device_id', $device->id)->first();
            if ($named !== null && $named->isOpen()) {
                return $named;
            }
        }

        return $this->shifts->current($device);
    }

    /**
     * The tenders a till takes offline: cash (with what the guest handed over) and card (a separate bank terminal).
     *
     * @return list<array{tender: string, amount: string, tendered_amount?: string, card_type?: string, transaction_id?: string, card_last4?: string}>
     */
    private function tenders(mixed $input): array
    {
        $tenders = [];
        foreach (is_array($input) ? $input : [] as $tender) {
            if (!is_array($tender)) {
                continue;
            }
            $kind = (string) ($tender['tender'] ?? '');
            if (!in_array($kind, [PosTender::CASH->value, PosTender::CARD->value], true)) {
                throw new PosSyncException('tender_not_offline', "Offlayn faqat naqd va karta (alohida terminal) qabul qilinadi.");
            }
            $amount = self::money((string) ($tender['amount'] ?? ''));
            if ($amount === null || !$amount->isPositive()) {
                throw new PosSyncException('amount_invalid', "To'lov summasi noto'g'ri.");
            }
            $row = ['tender' => $kind, 'amount' => $amount->toString()];
            if ($kind === PosTender::CASH->value && isset($tender['tendered_amount']) && self::money((string) $tender['tendered_amount']) !== null) {
                $row['tendered_amount'] = Money::of((string) $tender['tendered_amount'])->toString();
            }
            if ($kind === PosTender::CARD->value) {
                // Review P3-35: what the columns hold (a card type the till does not know is `other`)
                $cardType = strtolower(trim(is_scalar($tender['card_type'] ?? null) ? (string) $tender['card_type'] : ''));
                $row['card_type'] = in_array($cardType, PaymentService::CARD_TYPES, true) ? $cardType : 'other';
                $row['transaction_id'] = mb_substr(trim(is_scalar($tender['transaction_id'] ?? null) ? (string) $tender['transaction_id'] : ''), 0, 64) ?: 'offline';
                // P6-16: the card's last four digits when the till sent four digits; anything else is dropped (money
                // taken offline is never refused for it)
                $last4 = trim(is_scalar($tender['card_last4'] ?? null) ? (string) $tender['card_last4'] : '');
                if (preg_match('/^\d{4}$/', $last4)) {
                    $row['card_last4'] = $last4;
                }
            }
            $tenders[] = $row;
        }
        if ($tenders === []) {
            throw new PosSyncException('tenders_required', "To'lov turi yo'q.");
        }

        return $tenders;
    }

    /**
     * Tenders cut down to what the bill owes: cash first (the guest's change covers it), then card.
     *
     * @param  list<array<string, mixed>>  $tenders
     * @return list<array<string, mixed>>
     */
    private function fit(array $tenders, Money $due): array
    {
        $excess = Money::sum(...array_map(fn (array $tender) => Money::of($tender['amount']), $tenders))->minus($due);
        $order = array_keys($tenders);
        usort($order, fn (int $a, int $b) => ($tenders[$a]['tender'] === PosTender::CASH->value ? 0 : 1) <=> ($tenders[$b]['tender'] === PosTender::CASH->value ? 0 : 1));
        foreach ($order as $k) {
            if (!$excess->isPositive()) {
                break;
            }
            $amount = Money::of($tenders[$k]['amount']);
            $cut = $amount->isGreaterThan($excess) ? $excess : $amount;
            $tenders[$k]['amount'] = $amount->minus($cut)->toString();
            $excess = $excess->minus($cut);
        }

        return array_values(array_filter($tenders, fn (array $tender) => Money::of($tender['amount'])->isPositive()));
    }

    /**
     * The offline lines of a round: PosLineBuilder::parse() plus the device's line id and prices.
     *
     * @return list<array<string, mixed>>
     */
    private function lineInputs(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            return [];
        }
        $items = array_values($items);
        $parsed = $this->lines->parse($items, FireMode::NOW);

        $lines = [];
        foreach ($parsed as $line) {
            $item = is_array($items[$line['index']] ?? null) ? $items[$line['index']] : [];
            $prices = [];
            foreach ((array) ($item['modifiers'] ?? []) as $modifier) {
                if (is_array($modifier) && isset($modifier['id'], $modifier['price_delta']) && self::money((string) $modifier['price_delta']) !== null) {
                    $prices[strtolower((string) $modifier['id'])] = Money::of((string) $modifier['price_delta']);
                }
            }
            $lineId = isset($item['line_id']) && is_string($item['line_id']) && Str::isUuid($item['line_id']) ? strtolower($item['line_id']) : null;
            $lines[] = $line + [
                'client_line_id' => $lineId,
                'unit_price' => isset($item['unit_price']) ? self::money((string) $item['unit_price']) : null,
                'modifier_prices' => $prices,
            ];
        }

        return $lines;
    }

    /**
     * A decimal amount the device sent ("15000", "15000.50"), null when it is not one.
     */
    private static function money(mixed $value): ?Money
    {
        // Review P3-35: at most what a money column holds (decimal 12,2: ten digits before the point)
        return is_scalar($value) && preg_match('/^\d{1,10}(\.\d{1,2})?$/', trim((string) $value)) ? Money::of(trim((string) $value)) : null;
    }

    private function uuidOf(mixed $value, string $what): string
    {
        if (!is_string($value) || !Str::isUuid($value)) {
            throw new PosSyncException("{$what}_ref_invalid", $what === 'bill' ? "Schyot raqami noto'g'ri." : "Buyurtma raqami noto'g'ri.");
        }

        return strtolower($value);
    }

    private function text(mixed $value, int $max): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
    }

    /**
     * The device's id of what an operation creates (order.open: the order, bill.create: the bill).
     *
     * @param  array<string, mixed>  $payload
     */
    private function clientRef(?PosSyncOperationType $type, array $payload): ?string
    {
        $value = match ($type) {
            PosSyncOperationType::ORDER_OPEN => $payload['order'] ?? null,
            PosSyncOperationType::BILL_CREATE => $payload['bill'] ?? null,
            default => null,
        };

        return is_string($value) && Str::isUuid($value) ? strtolower($value) : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function describe(\Throwable $e): array
    {
        return match (true) {
            $e instanceof PosSyncException => [$e->reason, $e->getMessage()],
            $e instanceof PosOrderException => [$e->reason, $e->getMessage()],
            $e instanceof PosActionException => [$e->reason, $e->getMessage()],
            $e instanceof FiscalException => [$e->reason, $e->getMessage()],
            $e instanceof ValidationException => ['invalid', (string) collect($e->errors())->flatten()->first()],
            default => ['invalid', $e->getMessage()],
        };
    }
}
