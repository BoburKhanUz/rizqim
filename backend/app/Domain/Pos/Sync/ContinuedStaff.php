<?php

namespace App\Domain\Pos\Sync;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PosShift;
use App\Support\Money;

/**
 * Wave M5 S6-a (docs/scale/M5_DESIGN.md §2 "App restart during an outage", owner answer 8.1-3): operations a till
 * queued for a staff member who went on WITHOUT a PIN — "PIN'siz davom etilgan" in the reports.
 *
 * The mark. The till puts `staff_continued: true` in the payload of every operation such a staff member queues (M5 S1:
 * their PIN session ran out during the outage; S6-a: the app restarted during the outage and the last staff member
 * was resumed from the till's secure storage, then also `staff_resumed: true`; the till sends that only to a server
 * whose bootstrap says `offline.staff_resume: true`). The server's own staff check does not change (the 36 h PIN
 * sign-in window, PosSyncApplier::staff()): the mark is recorded, never trusted to widen anything.
 *
 * What is recorded: the payload as sent on the operation's row (pos_sync_operations; "Sinxronlash xatolari" shows
 * `staff_continued`), and on the order a `staff_continued` sync flag (informational, never a conflict): one per order,
 * plus one per `bill.pay` with the payments that operation took (`payments`). The X/Z report counts those payments
 * (`continued: {count, amount}`).
 */
final class ContinuedStaff
{
    /** The order's sync flag (informational). */
    public const FLAG = 'staff_continued';

    /**
     * Whether the till marked the operation as queued by a PIN-less continued staff member.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function marked(array $payload): bool
    {
        return filter_var($payload['staff_continued'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The flag an applied marked operation adds to its order, or null (not marked; the order already says so and the
     * operation took no payment).
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $payments  the uuids of the payments this operation took (bill.pay)
     * @return array<string, mixed>|null
     */
    public static function flag(Order $order, array $payload, ?int $staffId, array $payments = []): ?array
    {
        if (!self::marked($payload)) {
            return null;
        }
        $already = array_filter((array) ($order->sync_flags ?? []), fn (array $flag) => ($flag['flag'] ?? null) === self::FLAG);
        if ($already !== [] && $payments === []) {
            return null;
        }

        return array_filter([
            'flag' => self::FLAG,
            'resumed' => filter_var($payload['staff_resumed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'staff_id' => $staffId,
            'payments' => $payments !== [] ? $payments : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * The payments of $shift taken by a PIN-less continued staff member (the `payments` of the orders' flags).
     *
     * @return array{count: int, amount: string}
     */
    public static function ofShift(PosShift $shift): array
    {
        $orders = Order::query()
            ->whereIn('id', Payment::query()->where('shift_id', $shift->id)->select('order_id'))
            ->whereNotNull('sync_flags')
            ->get(['id', 'sync_flags']);
        $uuids = [];
        foreach ($orders as $order) {
            foreach ((array) ($order->sync_flags ?? []) as $flag) {
                if (($flag['flag'] ?? null) === self::FLAG) {
                    foreach ((array) ($flag['payments'] ?? []) as $uuid) {
                        $uuids[] = (string) $uuid;
                    }
                }
            }
        }
        if ($uuids === []) {
            return ['count' => 0, 'amount' => '0.00'];
        }
        $amounts = Payment::query()
            ->where('shift_id', $shift->id)
            ->whereIn('status', ['paid', 'refunded'])
            ->whereIn('uuid', array_values(array_unique($uuids)))
            ->pluck('amount')
            ->all();

        return ['count' => count($amounts), 'amount' => Money::sum(...$amounts)->toString()];
    }
}
