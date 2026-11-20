<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Billing\BillableOrder;
use App\Domain\Pos\Billing\BillFiscalizer;
use App\Domain\Pos\Billing\BillService;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\OrderCloser;
use App\Domain\Pos\Billing\PaymentService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Kitchen\KitchenAssembly;
use App\Domain\Pos\Lan\BranchLan;
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
use App\Services\Notifications\OrderAlertService;
use App\Services\Order\KitchenService;
use App\Services\Order\OrderTransitionException;
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
 *    (`underpaid`, `overpaid`). Wave M5 S1: a payment whose staff check fails (PIN session older than 36 h, the
 *    permission gone, an unknown staff id) settles its bill all the same when the bill is open (else it is recorded
 *    unallocated) and is flagged `staff_unverified`; the money is that person's only when they work on this till and
 *    signed in on it at least once (attributable()), the audit's actor is the device. The `order.close` of an order
 *    such a payment paid in full is applied too; any other operation failing that check is rejected.
 *
 * P6 local bridge (docs/POS_SPEC.md §5.5): an operation the branch's hub relayed for another terminal of the branch
 * (`origin_device`: a waiter's phone, a KDS) is applied as that terminal's (the staff member signed in there, the
 * order's device), only when the sender is the branch's hub (BranchLan::acceptsRelayFrom), the origin is an active
 * terminal of the same branch and the type is relayable (never money, a bill or closing); otherwise it is rejected
 * (`relay_not_hub`, `origin_unknown`, `origin_wrong_branch`, `origin_revoked`, `relay_not_allowed`).
 */
final class PosSyncApplier
{
    /** A menu snapshot older than this makes an offline-priced order a conflict (POS_SPEC 5.3, 5.4). */
    public const STALE_MENU_HOURS = 24;

    /** How long before the operation (review P6 C8: not the sync) a staff member's PIN session on the till may have started. */
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
     * @param  array{op_id: string, seq: int, type: string, occurred_at?: ?string, staff_id?: ?int, payload?: array<string, mixed>, origin_device?: ?string}  $op
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
            'origin_device_id' => null,
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

        $origin = isset($op['origin_device']) ? strtolower($op['origin_device']) : null;
        try {
            if ($type === null) {
                throw new PosSyncException('type_unknown', "Amal turi noma'lum: ilovani yangilang.");
            }
            // P6: a relayed operation is the origin terminal's (who signed in there, the order's device)
            $acting = $this->origin($device, $origin, $type, $happenedAt);
            $base['origin_device_id'] = (int) $acting->id !== (int) $device->id ? (int) $acting->id : null;
            try {
                $staff = $this->staff($acting, $base['staff_id'], $type, $happenedAt);
            } catch (PosSyncException $e) {
                if ($type->isMoney()) {
                    // Wave M5 S1: money taken is never rejected, whoever took it: recorded, flagged `staff_unverified`
                    return $this->staffUnverified($acting, $type, $payload, $happenedAt, $clamped, $base, $e);
                }
                // ...and the close of an order such a payment paid in full follows it (anything else: rejected)
                $closed = $type === PosSyncOperationType::ORDER_CLOSE ? $this->closeUnverified($acting, $payload, $base, $e) : null;
                if ($closed === null) {
                    throw $e;
                }

                return $closed;
            }
        } catch (PosSyncException $e) {
            // Wave M4 T-1: the row links only a user of this store (the error list shows the linked user's name); an id
            // of anybody else stays only in what the till sent
            if ($base['staff_id'] !== null && !User::where('store_id', $device->store_id)->whereKey($base['staff_id'])->exists()) {
                $base['staff_id'] = null;
            }

            // Nobody to attribute it to (or an unknown type, or a relay the server does not take): never applied
            // (a payment whose staff check failed was recorded above: staffUnverified())
            return $this->rejected($base, $e->reason, $e->getMessage(), $origin !== null ? ['origin_device' => $origin] : null);
        }

        // Review P6 hardware/LAN (C1): a relayed operation's op_id is the origin device's own request id — the waiter app
        // retries an online request whose answer was lost through the hub with the same Idempotency-Key — so it stands
        // for the key when the hub did not send one (the stored payload stays as sent)
        $keyed = $base['origin_device_id'] !== null && !is_string($payload['idempotency_key'] ?? null)
            ? ['idempotency_key' => $base['op_id']] + $payload
            : $payload;

        $bills = [];
        try {
            return DB::transaction(function () use ($acting, $type, $staff, $keyed, $happenedAt, $clamped, $base, &$bills) {
                $outcome = $this->run($acting, $type, $staff, $keyed, $happenedAt);
                $bills = $outcome['bills'] ?? [];
                if ($clamped !== null) {
                    $outcome['flags'] = [...($outcome['flags'] ?? []), $clamped];
                    $outcome['result']['flags'] = [...($outcome['result']['flags'] ?? []), $clamped];
                }

                return $this->record($base, $outcome);
            });
        } catch (PosSyncException|PosOrderException|PosActionException|ValidationException|FiscalException|\App\Domain\Pos\Booking\BookingException|\App\Domain\Pos\Accounts\CustomerAccountException|OrderTransitionException $e) {
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
     * @param  array{claimed_staff_id: int|null}|null  $unverified  the staff check failed (staffUnverified())
     */
    private function unallocatedOrRejected(Device $device, ?User $staff, array $payload, ?Carbon $happenedAt, array $base, string $reason, string $message, ?array $unverified = null): PosSyncOperation
    {
        try {
            return $this->unallocated($device, $staff, $payload, $happenedAt, $base, $reason, $message, $unverified);
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
     * Wave M5 S1 (docs/scale/M5_DESIGN.md §2): a payment whose staff check failed (the PIN session started more than
     * 36 h before it, the permission was taken away meanwhile, the staff id is nobody of this till) is money in the
     * drawer all the same:
     *  - the server took this very request online already (its Idempotency-Key): a replay, nothing new is recorded;
     *  - the bill is open: it is settled through the normal payment path (PaymentService), so the bill is paid, the
     *    order closes, the shift closes over it and nobody pays it a second time;
     *  - there is no open bill to put it on (the order cancelled or closed, the bill paid, void or unknown, the shift
     *    closed, the payment refused): recorded unallocated (unallocated()).
     * Flagged `staff_unverified` for the manager and audited either way. The staff id is only a claim: the money is
     * that person's only when they are attributable(); the audit's actor is the device, the claim is in its payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $clamped
     * @param  array<string, mixed>  $base
     */
    private function staffUnverified(Device $device, PosSyncOperationType $type, array $payload, ?Carbon $happenedAt, ?array $clamped, array $base, PosSyncException $e): PosSyncOperation
    {
        $claimed = $base['staff_id'];
        $attributed = $this->attributable($device, $claimed);
        // The row links (and "Sinxronlash xatolari" names) only a person who passes that check; so does the message
        $base['staff_id'] = $attributed?->id;
        $message = $attributed !== null || $e->reason === 'staff_invalid' ? $e->getMessage() : "Amalda ko'rsatilgan xodim bu kassada PIN bilan kirmagan.";
        $unverified = ['claimed_staff_id' => $claimed];

        $replayed = $claimed !== null && User::where('store_id', $device->store_id)->whereKey($claimed)->exists()
            ? $this->replayed($type, $claimed, $payload)
            : null;
        if ($replayed !== null) {
            try {
                $order = $this->posOrder($device, $payload);

                // (`staff_unverified`: why the check failed, so the close that follows is taken too — closeUnverified())
                return $this->record($base, $this->finish($order, [], $payload, ['replayed' => true, 'staff_unverified' => $e->reason] + $replayed));
            } catch (PosSyncException) {
                // No such order here: recorded below as any other payment
            }
        }

        $bills = [];
        try {
            return DB::transaction(function () use ($device, $attributed, $payload, $happenedAt, $clamped, $base, $e, $message, $unverified, &$bills) {
                // PaymentService needs a person for the shift and the drawer's count: the shift's opener stands in
                $shift = $this->openShiftOf($device, $payload['shift_uuid'] ?? null);
                $standIn = $attributed ?? ($shift !== null ? User::find($shift->opened_by) : null);
                if ($standIn === null) {
                    throw new PosSyncException('shift_not_open', "Ochiq smena yo'q.");
                }

                $before = Payment::where('order_id', $this->posOrder($device, $payload)->id)->pluck('id')->all();
                $outcome = $this->pay($device, $standIn, $payload, $happenedAt, true, $attributed);
                $bills = $outcome['bills'] ?? [];
                /** @var Order $order */
                $order = $outcome['order'];
                $created = Payment::where('order_id', $order->id)->whereNotIn('id', $before)->orderBy('id')->get();
                $amount = Money::sum(...$created->pluck('amount')->all())->toString();

                $flags = [
                    ['flag' => 'staff_unverified', 'amount' => $amount, 'reason' => $e->reason, 'message' => $message],
                    ...($outcome['flags'] ?? []),
                    ...($clamped !== null ? [$clamped] : []),
                ];
                $this->audit->record('pos.offline_payment_staff_unverified', $order, [], [], $unverified + [
                    'amount' => $amount,
                    'reason' => $e->reason,
                    'payments' => $created->pluck('uuid')->all(),
                    'bill' => $outcome['result']['bill_uuid'] ?? null,
                ], ['actor' => null, 'device' => $device]);

                return $this->record($base, [
                    'status' => PosSyncStatus::CONFLICT,
                    'reason' => 'staff_unverified',
                    'error' => "To'lov ({$amount} so'm) xodim tekshiruvidan o'tmadi: {$message} Pul schyotga yozildi: menejer tekshirsin.",
                    'result' => ['flags' => $flags] + $outcome['result'],
                    'order' => $order,
                    'flags' => $flags,
                ]);
            });
        } catch (PosSyncException|PosOrderException|PosActionException|ValidationException|FiscalException|OrderTransitionException) {
            // No open bill to put it on: the money is recorded on the order as it is now
            $bills = [];

            return $this->unallocatedOrRejected($device, $attributed, $payload, $happenedAt, $base, $e->reason, $message, $unverified);
        } catch (Throwable $failure) {
            if (TransientDatabaseError::is($failure)) {
                throw $failure;
            }
            report($failure);
            $bills = [];

            return $this->unallocatedOrRejected($device, $attributed, $payload, $happenedAt, $base, $e->reason, $message, $unverified);
        } finally {
            foreach ($bills as $bill) {
                $this->fiscalizer->issue($bill);
            }
        }
    }

    /**
     * Wave M5 S1: `order.close` whose staff check failed. The till queues it right after the payment; when this till's
     * `staff_unverified` payment (or its replay) left the order paid in full, the close is applied (flagged and audited, the device as the
     * actor) instead of being rejected for the same check and left blocking the shift. Null: not that case (rejected
     * as any other operation).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $base
     */
    private function closeUnverified(Device $device, array $payload, array $base, PosSyncException $e): ?PosSyncOperation
    {
        try {
            $order = $this->posOrder($device, $payload);
        } catch (PosSyncException) {
            return null;
        }
        $paidUnverified = PosSyncOperation::where('device_id', $device->id)
            ->where('order_id', $order->id)
            ->where('type', PosSyncOperationType::BILL_PAY->value)
            ->where('status', '!=', PosSyncStatus::REJECTED->value)
            // ...recorded now, or the replay of one the server took online
            ->where(fn ($query) => $query->where('reason', 'staff_unverified')->orWhereNotNull('result->staff_unverified'))
            ->exists();
        if (!$paidUnverified || !$this->fullyPaid($order)) {
            return null;
        }

        $claimed = $base['staff_id'];
        $base['staff_id'] = $this->attributable($device, $claimed)?->id;

        return DB::transaction(function () use ($device, $order, $payload, $base, $claimed, $e) {
            $outcome = $this->close($device, null, $payload);
            // Informational: the payment's conflict already asks for the manager
            $flags = [['flag' => 'staff_unverified', 'reason' => $e->reason]];
            $this->audit->record('pos.offline_close_staff_unverified', $order, [], [], [
                'claimed_staff_id' => $claimed,
                'reason' => $e->reason,
                'closed' => $outcome['result']['closed'] ?? false,
            ], ['actor' => null, 'device' => $device]);

            return $this->record($base, ['result' => $outcome['result'] + ['flags' => $flags], 'flags' => $flags] + $outcome);
        });
    }

    /**
     * Whether the order's money is all in: closed already, or everything billed and every bill covered by its
     * payments (a bill waiting for its receipt counts: the order closes with the receipt).
     */
    private function fullyPaid(Order $order): bool
    {
        if ($order->closed_at !== null) {
            return true;
        }
        if (!in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true)) {
            return false;
        }

        $bills = OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->get();
        foreach ($bills as $bill) {
            $paid = Money::sum(...Payment::where('bill_id', $bill->id)->where('status', 'paid')->pluck('amount')->all());
            if ($bill->status !== BillStatus::PAID && $paid->isLessThan($bill->total)) {
                return false;
            }
        }

        return $bills->isNotEmpty() && BillableOrder::of($order)->isEmpty();
    }

    /**
     * Wave M5 S1: the person money is attributed to when the staff check failed. The operation's staff id is only a
     * claim (the till's token alone sends it), so: the same store, terminal role and branch as staff(), and at least
     * one PIN sign-in on this till, whenever it was. Anybody else: nobody.
     */
    private function attributable(Device $device, ?int $staffId): ?User
    {
        $staff = $this->terminalStaff($device, $staffId);

        return $staff !== null && AuditLog::where('action', 'staff.session_started')->where('user_id', $staff->id)->where('device_id', $device->id)->exists()
            ? $staff
            : null;
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
     * @param  array<string, mixed>|null  $result
     */
    private function rejected(array $base, string $reason, string $message, ?array $result = null): PosSyncOperation
    {
        return PosSyncOperation::create($base + [
            'status' => PosSyncStatus::REJECTED->value,
            'reason' => $reason,
            'error' => $message,
            'result' => $result,
        ]);
    }

    /**
     * P6 local bridge: the terminal an operation is applied as — the sender itself, or (relayed by the branch's hub)
     * the origin terminal of the same branch.
     *
     * @throws PosSyncException
     */
    private function origin(Device $device, ?string $origin, PosSyncOperationType $type, ?Carbon $occurredAt): Device
    {
        if ($origin === null || $origin === '' || $origin === strtolower((string) $device->uuid)) {
            return $device;
        }
        if (!$type->relayable()) {
            throw new PosSyncException('relay_not_allowed', "Bu amal faqat kassaning o'zida bajariladi (to'lov, schyot, yopish).");
        }
        if (!app(BranchLan::class)->acceptsRelayFrom($device, $occurredAt)) {
            throw new PosSyncException('relay_not_hub', "Boshqa qurilmaning amalini faqat filialning markaz kassasi yuboradi.");
        }

        $source = Str::isUuid($origin) ? Device::where('uuid', $origin)->first() : null;
        if ($source === null || !$source->isTerminal() || (int) $source->store_id !== (int) $device->store_id) {
            throw new PosSyncException('origin_unknown', 'Amal qilingan qurilma topilmadi.');
        }
        if ($source->branch_id === null || (int) $source->branch_id !== (int) $device->branch_id) {
            throw new PosSyncException('origin_wrong_branch', 'Amal qilingan qurilma boshqa filialniki.');
        }
        if ($source->revoked_at !== null) {
            throw new PosSyncException('origin_revoked', 'Amal qilingan qurilma uzilgan.');
        }

        return $source;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status?: PosSyncStatus, reason?: ?string, error?: ?string, result: array<string, mixed>, order?: ?Order, bills?: list<OrderBill>, flags?: list<array<string, mixed>>}
     */
    private function run(Device $device, PosSyncOperationType $type, User $staff, array $payload, ?Carbon $occurredAt): array
    {
        $replayed = $this->replayed($type, (int) $staff->id, $payload);
        if ($replayed !== null && $type === PosSyncOperationType::ORDER_OPEN && isset($replayed['order_uuid'])) {
            // Review P6 C1: the order this very request opened online (its answer lost): that order, not a second one
            $adopted = $this->adoptOpened($device, $payload, $replayed['order_uuid']);
            if ($adopted !== null) {
                return $adopted;
            }
        }
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
            PosSyncOperationType::ORDER_COURSE_SEND => $this->courseSend($device, $staff, $payload),
            PosSyncOperationType::ORDER_WEIGHT => $this->weight($device, $staff, $payload),
            PosSyncOperationType::KITCHEN_BUMP => $this->kitchen($device, $staff, $payload, true),
            PosSyncOperationType::KITCHEN_RECALL => $this->kitchen($device, $staff, $payload, false),
            PosSyncOperationType::KITCHEN_ASSEMBLED => $this->assembled($device, $staff, $payload),
            PosSyncOperationType::BILL_REQUEST => $this->billRequest($device, $staff, $payload),
        };
    }

    // --- operations -------------------------------------------------------------------------------------------------

    /**
     * order.open: {order (the device's uuid, stored as external_id), local_number, queue_number? (P6-13), channel, table_id?, guests_count?,
     * waiter_id?, note?, table_label? (P6-25), items?, send?, menu_taken_at?}; items may carry course_no and
     * awaiting_weight (P6-21 / P6-25)
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
        // P6-25: the table plate of a counter order carried to the table (kept as the till took it)
        $options['table_label'] = $this->text($payload['table_label'] ?? null, 20);
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

        // Review M2 (40P01): the round added below locks in the same order as the open (StockLocks); lines that cannot
        // be read are refused by that round, as before
        try {
            $options['also_lock'] = array_map(fn (array $line) => (string) $line['variant_uuid'], $this->lineInputs($payload['items'] ?? []));
        } catch (ValidationException) {
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
     * P6-21 order.course_send: {order, course_no} — the held lines of the course still waiting; nothing left (sent from
     * another device meanwhile, voided): applied, nothing to do.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function courseSend(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);
        $course = isset($payload['course_no']) && is_numeric($payload['course_no']) ? (int) $payload['course_no'] : 0;
        if ($course < 1 || $course > \App\Domain\Pos\Orders\PosCourses::MAX_COURSE) {
            throw new PosSyncException('course_invalid', 'Kurs raqami noto\'g\'ri.');
        }
        if (app(\App\Domain\Pos\Orders\PosCourses::class)->heldLines($order, $course) === []) {
            return $this->finish($order, [], $payload, ['sent' => []]);
        }

        $order = $this->orders->sendCourse($order, (int) $order->version, $staff, $course);

        return $this->finish($order, [], $payload);
    }

    /**
     * P6-25 order.weight: {order, line, quantity} — the real weight the till entered offline. A line weighed meanwhile on
     * the server keeps the server's weight (a manager re-weighs it online): rejected `line_weighed`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function weight(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);
        $item = $this->item($order, $payload['line'] ?? null);
        if ($item->weight_status === \App\Domain\Pos\Orders\WeightStatus::WEIGHED) {
            throw new PosSyncException('line_weighed', "Taom shu orada boshqa qurilmada tortilgan: og'irlikni menejer onlayn tuzatsin.", ['item_id' => (int) $item->id]);
        }
        $weight = \App\Domain\Pos\Orders\PosWeights::parseWeight($payload['quantity'] ?? null);

        $order = $this->orders->setWeight($order, (int) $order->version, $staff, (int) $item->id, $weight);

        return $this->finish($order, [], $payload);
    }

    /**
     * P6 kitchen.bump / kitchen.recall: {order, line} — a KDS marked a line ready (or took it back) offline, through the
     * hub. The same rules as the kitchen display online (KitchenService); bumped already: applied, nothing changes. An
     * order that left the kitchen meanwhile: rejected (`illegal`, `stale`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function kitchen(Device $device, User $staff, array $payload, bool $ready): array
    {
        $order = $this->posOrder($device, $payload);
        $ref = $payload['line'] ?? null;
        // P6-31: a combo part's kitchen line too
        $query = OrderItem::withComboParts()->where('order_id', $order->id);
        $item = is_int($ref) || (is_string($ref) && ctype_digit($ref))
            ? $query->whereKey((int) $ref)->first()
            : (is_string($ref) && Str::isUuid($ref) ? $query->where('client_line_id', strtolower($ref))->first() : null);
        if ($item === null) {
            throw new PosSyncException('line_not_found', 'Taom qatori serverda topilmadi.');
        }

        $kitchen = app(KitchenService::class);
        $result = $ready ? $kitchen->bump($order, $item, $staff) : $kitchen->recall($order, $item, $staff);
        $this->statusAlerts($result['order'], $result['statuses']);

        return $this->finish(Order::findOrFail($order->id), [], $payload, ['changed' => $result['changed']]);
    }

    /**
     * P6 kitchen.assembled: {order} — the expo station assembled the order offline (KitchenAssembly). An order that
     * does not go through the expo (the last line bump made it ready) or was assembled already: applied, nothing to do.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function assembled(Device $device, User $staff, array $payload): array
    {
        $order = $this->posOrder($device, $payload);
        try {
            $result = app(KitchenAssembly::class)->assemble($order, $staff);
        } catch (PosOrderException $e) {
            if ($e->reason !== 'assembly_not_required') {
                throw $e;
            }

            return $this->finish(Order::findOrFail($order->id), [], $payload, ['changed' => false]);
        }
        $this->statusAlerts($result['order'], $result['statuses']);

        return $this->finish(Order::findOrFail($order->id), [], $payload, ['changed' => $result['changed']]);
    }

    /**
     * P6 bill.request: {order, printed_on_lan?} — the waiter asked for the bill offline (PosOrderService::requestBill:
     * the waiter rule, asked once already: unchanged). `printed_on_lan: true` (P6 stage B): the hub took the request
     * over the LAN while the server was out of reach and printed the pre-check itself, so the branch's automatic bill
     * print (BillPrintRequests) does not print it again now.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function billRequest(Device $device, User $staff, array $payload): array
    {
        $order = $this->openOrder($device, $payload);

        // Review P6 hardware/LAN (C6): the print request keeps the relayed op's key, so a hub that printed it itself
        // at the start of an outage (before it knew the server was out of reach) does not print it again now
        $key = is_string($payload['idempotency_key'] ?? null) ? $payload['idempotency_key'] : null;

        return $this->finish($this->orders->requestBill($order, null, $staff, null, ($payload['printed_on_lan'] ?? false) !== true, $key), [], $payload);
    }

    /**
     * The customer and courier alerts of the order statuses a kitchen change moved it through (as the KDS online).
     *
     * @param  list<OrderStatus>  $statuses
     */
    private function statusAlerts(Order $order, array $statuses): void
    {
        foreach ($statuses as $status) {
            app(OrderAlertService::class)->statusChanged($order, $status->value);
        }
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
     * bill.pay: {order, bill (the device's id or a server bill uuid), shift_uuid?, tip_staff_id?, waiter_id?, tenders:
     * [{tender: cash|card, amount, tendered_amount?, card_type?, transaction_id?, card_last4?, tip_amount?}]} — the
     * money as the till took it (P6-22: a tip on top of a tender is recorded as taken; review P6 wave 2: a tip to
     * someone other than the order's waiter, or outside the tip roles, goes to the waiter — no approval offline).
     * Review P6 wave 2 (C10): `waiter_id` is the order's waiter as the till saw it: the bill is that waiter's sales (and
     * the personal tip theirs) even when the table was handed over before the sync; without it, the waiter now.
     *
     * Wave M5 S1: `$staffUnverified` — the staff check failed (staffUnverified()): `$staff` stands in for the shift and
     * the drawer, the payment is `$attributed`'s or nobody's.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pay(Device $device, User $staff, array $payload, ?Carbon $occurredAt, bool $staffUnverified = false, ?User $attributed = null): array
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
        $tipStaff = isset($payload['tip_staff_id']) && is_numeric($payload['tip_staff_id']) ? (int) $payload['tip_staff_id'] : null;
        $tillWaiter = isset($payload['waiter_id']) && is_numeric($payload['waiter_id']) ? (int) $payload['waiter_id'] : null;
        $bill = $this->payments->pay($staff, $bill, $applied, null, $shift?->uuid, null, $device, null, $paidAt, $tipStaff, offlineWaiterId: $tillWaiter, staffUnverified: $staffUnverified, attributed: $attributed);
        $paymentUuids = Payment::where('bill_id', $bill->id)->where('offline_paid_at', '!=', null)->orderBy('id')->pluck('uuid')->all();

        return $this->finish(Order::findOrFail($order->id), $flags, $payload, [
            'bill_uuid' => $bill->uuid,
            'payments' => $paymentUuids,
            'fiscal_status' => $bill->getAttribute('fiscal_status'),
        ]) + ['bills' => [$bill]];
    }

    /**
     * order.close: {order} — closes it once every bill is paid and receipted (a pending receipt closes it later).
     * `$staff` null: closeUnverified() (the audit's actor is nobody).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function close(Device $device, ?User $staff, array $payload): array
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
     * Wave M5 S1: `$unverified` — the staff check failed (staffUnverified()): the flag is `staff_unverified` (with
     * `refund_needed` beside it on a cancelled order), `$staff` is the attributable() person or nobody, the audit's
     * actor is the device with the claimed staff id in its payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $base
     * @param  array{claimed_staff_id: int|null}|null  $unverified
     */
    private function unallocated(Device $device, ?User $staff, array $payload, ?Carbon $occurredAt, array $base, string $reason, string $message, ?array $unverified = null): PosSyncOperation
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

        $staffUnverified = $unverified !== null;

        return DB::transaction(function () use ($device, $staff, $order, $tenders, $occurredAt, $base, $reason, $message, $staffUnverified, $unverified) {
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
                    'store_id' => $order->store_id,
                    'bill_id' => null,
                    'shift_id' => $shift?->id,
                    'device_id' => $device->id,
                    'received_by' => $staff?->id,
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
                    // The drawer's count needs a person: nobody named (staff_unverified) leaves the shift's opener
                    $drawerUser = $staff ?? User::find($shift->opened_by);
                    if ($drawerUser !== null) {
                        $this->shifts->recordSale($shift, $payment, $drawerUser, $device);
                    }
                }
                $created[] = $payment;
            }

            $canceled = $order->status === OrderStatus::CANCELED;
            $flag = $staffUnverified ? 'staff_unverified' : ($canceled ? 'refund_needed' : 'payment_unallocated');
            $amount = Money::sum(...array_map(fn (Payment $payment) => Money::of($payment->amount), $created));
            $flags = [['flag' => $flag, 'amount' => $amount->toString(), 'reason' => $reason, 'message' => $message]];
            if ($staffUnverified && $canceled) {
                $flags[] = ['flag' => 'refund_needed', 'amount' => $amount->toString(), 'reason' => 'order_closed', 'message' => 'Buyurtma serverda bekor qilingan.'];
            }
            $this->flag($order, $flags, (string) $base['op_id']);

            $this->audit->record($staffUnverified ? 'pos.offline_payment_staff_unverified' : 'pos.offline_payment_unallocated', $order, [], [], ($unverified ?? []) + [
                'amount' => $amount->toString(),
                'reason' => $reason,
                'payments' => array_map(fn (Payment $payment) => $payment->uuid, $created),
            ], ['actor' => $staffUnverified ? null : $staff, 'device' => $device]);

            return $this->record($base, [
                'status' => PosSyncStatus::CONFLICT,
                'reason' => $flag,
                'error' => match (true) {
                    $staffUnverified => "To'lov ({$amount->toString()} so'm) xodim tekshiruvidan o'tmadi: {$message} Pul yozildi, schyotga tushmadi: menejer tekshirsin."
                        . ($canceled ? ' Buyurtma bekor qilingan: mijozga qaytarish kerak.' : ''),
                    $canceled => "Buyurtma bekor qilingan, lekin offlayn {$amount->toString()} so'm olingan: pul yozildi, mijozga qaytarish kerak.",
                    default => "To'lov ({$amount->toString()} so'm) schyotga tushmadi: {$message} Pul yozildi, menejer tekshirsin.",
                },
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
        'order.course_send' => 'pos.orders.course_send',
        'order.weight' => 'pos.orders.weight',
        'kitchen.assembled' => 'pos.kds.assembled',
        'bill.request' => 'pos.orders.request_bill',
    ];

    /**
     * When the operation carries the Idempotency-Key of an online request the staff member made just before the
     * outage (`idempotency_key`) and the server did that request: what it answered (a bill's uuid), else null.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function replayed(PosSyncOperationType $type, int $staffId, array $payload): ?array
    {
        $key = $payload['idempotency_key'] ?? null;
        if (!is_string($key) || $key === '') {
            return null;
        }

        $scope = self::SCOPES[$type->value] ?? null;
        if ($scope === null) {
            return null;
        }

        $row = \App\Models\IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('actor_type', 'user')
            ->where('actor_id', $staffId)
            ->where('key', $key)
            ->where(fn ($query) => $query->whereNull('locked_until')->orWhereBetween('response_status', [200, 299]))
            ->first();
        if ($row === null) {
            return null;
        }

        $body = json_decode((string) $row->getAttribute('response_body'), true);
        if ($type === PosSyncOperationType::ORDER_OPEN) {
            $order = is_array($body) ? ($body['data']['uuid'] ?? null) : null;

            return is_string($order) ? ['order_uuid' => $order] : [];
        }
        $bill = is_array($body) ? ($body['data']['bills'][0]['uuid'] ?? $body['data']['bill']['uuid'] ?? null) : null;

        return is_string($bill) ? ['bill_uuid' => $bill] : [];
    }

    /**
     * Review P6 C1: order.open whose Idempotency-Key the server answered online (POST /pos/orders opened [orderUuid],
     * the answer was lost, the same request went to the hub or the till's queue): that order, known from now on by the
     * device's id for it too (its later operations name it so), instead of a second order on the same room.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function adoptOpened(Device $device, array $payload, string $orderUuid): ?array
    {
        $order = Order::where('store_id', $device->store_id)->where('branch_id', $device->branch_id)->where('uuid', $orderUuid)->first();
        if ($order === null) {
            return null;
        }
        $clientId = $this->uuidOf($payload['order'] ?? null, 'order');
        if ($order->external_id === null && !Order::where('store_id', $device->store_id)->where('external_id', $clientId)->exists()) {
            $order->forceFill(['external_id' => $clientId])->save();
        }

        return $this->finish($order, [], $payload, ['replayed' => true]);
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
    private function staff(Device $device, ?int $staffId, PosSyncOperationType $type, ?Carbon $happenedAt = null): User
    {
        $staff = $this->terminalStaff($device, $staffId);
        if ($staff === null) {
            throw new PosSyncException('staff_invalid', "Xodim topilmadi yoki bu kassada ishlamaydi.");
        }

        $signedIn = AuditLog::query()
            ->where('action', 'staff.session_started')
            ->where('user_id', $staff->id)
            ->where('device_id', $device->id)
            // Review P6 C8: measured at the operation's time (a long outage late in a session still syncs; the LAN
            // grant ends as long after the sign-in), the time kept within the device-time window (deviceTime)
            ->where('created_at', '>=', ($happenedAt ?? now())->copy()->subHours(self::SESSION_WINDOW_HOURS))
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

    /**
     * The user a staff id names when they may work on this till: this store's, in a terminal role, on its branch.
     */
    private function terminalStaff(Device $device, ?int $staffId): ?User
    {
        return $staffId !== null ? User::where('store_id', $device->store_id)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::terminalRoles()))
            ->when($device->branch_id !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $device->branch_id)))
            ->find($staffId) : null;
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
            // P6-22: the tip the till took with the tender (kept whatever the module says now: the money was taken)
            $tip = isset($tender['tip_amount']) && is_scalar($tender['tip_amount']) ? self::money((string) $tender['tip_amount']) : null;
            if ($tip !== null && $tip->isPositive()) {
                $row['tip_amount'] = $tip->toString();
                if (isset($row['tendered_amount']) && Money::of($row['tendered_amount'])->isLessThan($amount->plus($tip))) {
                    unset($row['tendered_amount']); // never refused for it: the change is then not recorded
                }
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
                // P6-31: what the till added to the combo's price for its picks (compared with the server's)
                'combo_delta' => isset($item['combo_delta']) ? self::money((string) $item['combo_delta']) : null,
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
            $e instanceof OrderTransitionException => [$e->reason, $e->getMessage()],
            $e instanceof ValidationException => ['invalid', (string) collect($e->errors())->flatten()->first()],
            default => ['invalid', $e->getMessage()],
        };
    }
}
