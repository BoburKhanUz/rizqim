<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Setup\PosSettings;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\PosSyncOperation;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\StoreTimezone;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Wave M5 S2 (docs/scale/M5_DESIGN.md §3, owner answer 8.1-1): discounts given at the till without internet
 * (`order.discount` operations, PosSyncApplier::discount), for the reports and for the owner.
 *
 * The mark. An offline discount leaves three traces: the operation's row (pos_sync_operations, type
 * `order.discount`, not rejected), the audit row `pos.offline_discount` on the order, and the `offline_discount` flag
 * in the order's sync_flags (what the order screen shows). A report asks orders(): an order's discount is an offline
 * one while the last discount decision on it in the audit log is `pos.offline_discount` (a discount or a promo code
 * set online afterwards makes it an ordinary one again).
 *
 * The offline limit (owner decision of 20.11.2026). Through sync EVERYONE, a manager and a holder of orders.discount
 * too, gives at most the branch's `cashier_discount_max_percent`, by the cashier's own rule (the value as typed: a
 * percent ≤ the limit, an amount × 100 ≤ the dishes × the limit); no limit configured: no offline discount (limit(),
 * within()). A larger discount is given online.
 *
 * The owner's summary. No alert per discount: the owner gets ONE message per till and outage: how many orders were
 * discounted, the total, who gave them, and how many offline discounts the server REFUSED with what those left
 * unpaid on the bills. The rule:
 *  - what is told (pending()): the till's `order.discount` operations after the last summary of that till (the audit
 *    row `pos.offline_discount_summary` keeps how far it went), read from pos_sync_operations each time: nothing is
 *    kept in the cache, so a deploy or a Redis restart loses nothing. Applied ones: one per order (the last one
 *    counts; taken off again within the outage: nothing to tell), the amount as the order carries it now (a percent
 *    follows later rounds). Rejected ones: counted, with the `underpaid` amounts of their orders;
 *  - when: at the end of the batch that empties the till's queue (the till reports what is left: devices.
 *    sync_pending_ops; a till that reports nothing: at the end of each batch). While more is waiting the message
 *    waits too, so a queue drained in several batches is one message; never longer than 30 minutes after the first
 *    of them reached the server: a later batch of the till sends it then, and so does the schedule
 *    (`pos:offline-discount-summaries`, every 10 minutes: sendOverdue()) when the till never syncs again;
 *  - once: both paths run inside the till's sync lock (PosSyncService::lockName) and the audit row is written right
 *    after the message was handed to PosOwnerAlerts. A sender that throws leaves no row: the next batch or run tries
 *    again. PosOwnerAlerts::send itself swallows and logs a delivery problem (Telegram or push down): that message is
 *    not repeated (at most once).
 * Sent through PosOwnerAlerts (the store's admin chat and every owner, by their notification preferences). The
 * `alerts.discount_percent` threshold of the per-discount alert does not apply: the owner asked to hear of every one.
 * A notification problem never fails the batch.
 */
final class OfflineDiscounts
{
    /** The order's sync flag (informational). */
    public const FLAG = 'offline_discount';

    public const AUDIT = 'pos.offline_discount';

    public const SUMMARY_AUDIT = 'pos.offline_discount_summary';

    /** The summary of a queue that is still draining waits for its end at most this long. */
    public const MAX_WAIT_MINUTES = 30;

    /** How far back an untold discount is looked for. */
    public const LOOKBACK_DAYS = 7;

    /** The audit actions that decide an order's discount (the last one tells whose it is). */
    private const DECISIONS = ['pos.order.discount', 'pos.order.promo', self::AUDIT];

    public function __construct(
        private readonly PosOwnerAlerts $alerts,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Which of these orders carry a discount given offline (see the class comment).
     *
     * @param  iterable<int|string>  $orderIds
     * @return list<int>
     */
    public static function orders(iterable $orderIds): array
    {
        $ids = array_values(array_unique(array_map('intval', [...$orderIds])));
        if ($ids === []) {
            return [];
        }

        $last = [];
        AuditLog::query()
            ->where('model_type', (new Order())->getMorphClass())
            ->whereIn('model_id', $ids)
            ->whereIn('action', self::DECISIONS)
            ->orderBy('id')
            ->get(['id', 'model_id', 'action', 'payload_json'])
            ->each(function (AuditLog $row) use (&$last) {
                $last[(int) $row->model_id] = $row->action === self::AUDIT && !($row->payload_json['removed'] ?? false);
            });

        return array_keys(array_filter($last));
    }

    /**
     * The paid discounts of these bills that were given offline, for the shift report.
     *
     * @param  Collection<int, \App\Models\OrderBill>  $bills
     * @return array{count: int, amount: string}
     */
    public static function ofBills(Collection $bills): array
    {
        $discounted = $bills->filter(fn ($bill) => Money::of($bill->discount)->isPositive());
        $offline = self::orders($discounted->pluck('order_id')->all());
        $rows = $discounted->filter(fn ($bill) => in_array((int) $bill->order_id, $offline, true));

        return ['count' => $rows->count(), 'amount' => Money::sum(...$rows->pluck('discount')->all())->toString()];
    }

    /**
     * The most anybody gives without internet at $branch, as a percent of the dishes; null: no offline discount.
     */
    public static function limit(?Branch $branch): ?Money
    {
        $stored = $branch?->getAttribute('pos_settings');
        $limit = PosSettings::resolve(is_array($stored) ? $stored : null)['cashier_discount_max_percent'];

        return $limit !== null && Money::of($limit)->isPositive() ? Money::of($limit) : null;
    }

    /**
     * Whether a discount typed as $value is within $limit percent of $items (the cashier's rule,
     * PosOrderService::cashierMayDiscount): a percent by itself, an amount by amount × 100 ≤ items × limit, exactly.
     */
    public static function within(Money $limit, string $kind, Money $value, Money $items): bool
    {
        return $kind === 'percent'
            ? $value->isLessThanOrEqualTo($limit)
            : BigDecimal::of($value->toString())->multipliedBy(100)->isLessThanOrEqualTo(BigDecimal::of($items->toString())->multipliedBy($limit->toString()));
    }

    /**
     * After a sync batch of $device (PosSyncService::sync, inside the till's lock, after the queue report was stored).
     */
    public function afterBatch(Device $device): void
    {
        try {
            $pending = $this->pending($device);
            if ($pending->isEmpty()) {
                return;
            }
            // More of the queue is coming: one message for the whole drain, unless it has waited long enough
            if ((int) $device->getAttribute('sync_pending_ops') > 0 && !self::overdue($pending)) {
                return;
            }
            $this->summarize($device, $pending);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The schedule (`pos:offline-discount-summaries`): the summary of every till whose oldest untold offline discount
     * reached the server more than MAX_WAIT_MINUTES ago, whether the till synced again or not. A till whose batch is
     * being applied right now is left to that batch (the same lock). Returns how many summaries were sent.
     */
    public function sendOverdue(): int
    {
        $devices = PosSyncOperation::query()
            ->where('type', PosSyncOperationType::ORDER_DISCOUNT->value)
            ->whereBetween('received_at', [now()->subDays(self::LOOKBACK_DAYS), now()->subMinutes(self::MAX_WAIT_MINUTES)])
            ->distinct()
            ->orderBy('device_id')
            ->pluck('device_id');

        $sent = 0;
        foreach ($devices as $id) {
            $device = Device::find($id);
            if ($device === null) {
                continue;
            }
            $lock = Cache::lock(PosSyncService::lockName($device), 120);
            if (!$lock->get()) {
                continue;
            }
            try {
                $pending = $this->pending($device);
                if ($pending->isNotEmpty() && self::overdue($pending)) {
                    $sent += $this->summarize($device, $pending) ? 1 : 0;
                }
            } catch (Throwable $e) {
                report($e);
            } finally {
                $lock->release();
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, PosSyncOperation>  $pending
     */
    private static function overdue(Collection $pending): bool
    {
        return $pending->min('received_at')->lte(now()->subMinutes(self::MAX_WAIT_MINUTES));
    }

    /**
     * The till's offline discounts (applied and refused) nobody told the owner about yet, oldest first. One query,
     * served by pos_sync_operations_discount_device_index (the till's `order.discount` rows by id); the last summary's
     * mark comes from audit_logs (action, created_at) inside it.
     *
     * @return Collection<int, PosSyncOperation>
     */
    public function pending(Device $device): Collection
    {
        $since = now()->subDays(self::LOOKBACK_DAYS);
        $told = AuditLog::query()
            ->selectRaw("coalesce(max(cast(payload_json->>'through_op' as bigint)), 0)")
            ->where('action', self::SUMMARY_AUDIT)
            ->where('created_at', '>=', $since)
            ->where('device_id', $device->id);

        return PosSyncOperation::query()
            ->where('device_id', $device->id)
            ->where('type', PosSyncOperationType::ORDER_DISCOUNT->value)
            ->where('received_at', '>=', $since)
            ->where('id', '>', $told)
            ->orderBy('id')
            ->get()
            // A request the server took online before the outage (its answer lost): that one alerted then
            ->reject(fn (PosSyncOperation $op) => (bool) ($op->result['replayed'] ?? false))
            ->values();
    }

    /**
     * One message to the owner about $pending (a till's untold offline discounts), then the audit row that says how
     * far it went. Returns whether there was anything to say.
     *
     * @param  Collection<int, PosSyncOperation>  $pending
     */
    public function summarize(Device $device, Collection $pending): bool
    {
        [$refused, $applied] = $pending->partition(fn (PosSyncOperation $op) => $op->status === PosSyncStatus::REJECTED->value);
        // One per order: the last decision of the outage; a discount taken off again is not one
        $given = $applied->filter(fn (PosSyncOperation $op) => $op->order_id !== null)->keyBy('order_id')
            ->filter(fn (PosSyncOperation $op) => ($op->result['discount_kind'] ?? null) !== null)->values();
        $through = (int) $pending->max('id');
        $context = ['actor' => null, 'device' => $device, 'store_id' => (int) $device->store_id, 'branch_id' => $device->branch_id];
        if ($given->isEmpty() && $refused->isEmpty()) {
            $this->audit->record(self::SUMMARY_AUDIT, null, [], [], ['count' => 0, 'amount' => '0.00', 'through_op' => $through, 'sent' => false], $context);

            return false;
        }

        $orders = Order::whereIn('id', $given->pluck('order_id')->merge($refused->pluck('order_id'))->filter()->unique()->all())->get()->keyBy('id');
        $rows = $given->map(function (PosSyncOperation $op) use ($orders) {
            $order = $orders->get($op->order_id);
            $applied = Money::of((string) ($op->result['discount'] ?? '0'));
            // The order's amount now when it still carries this discount (a percent follows the rounds after it)
            $same = $order !== null
                && $order->getAttribute('discount_kind') === ($op->result['discount_kind'] ?? null)
                && $order->getAttribute('discount_value') !== null
                && Money::of((string) $order->getAttribute('discount_value'))->equals((string) ($op->result['discount_value'] ?? '0'));

            return ['op' => $op, 'staff_id' => $op->staff_id !== null ? (int) $op->staff_id : null, 'amount' => $same ? Money::of($order->discount) : $applied];
        });

        $total = Money::sum(...$rows->pluck('amount')->all());
        $names = User::whereIn('id', $rows->pluck('staff_id')->filter()->unique()->all())->pluck('name', 'id');
        $byStaff = $rows->groupBy(fn (array $row) => (string) $row['staff_id'])->map(fn (Collection $group) => [
            'staff_id' => $group->first()['staff_id'],
            'name' => (string) ($names[$group->first()['staff_id']] ?? "Noma'lum xodim"),
            'count' => $group->count(),
            'amount' => Money::sum(...$group->pluck('amount')->all()),
        ])->sortByDesc(fn (array $row) => $row['amount']->toTiyin())->values();

        // What the refused discounts left unpaid: the guest paid the discounted bill, the server made it at the full
        // price (the `underpaid` marks of those orders; an order not paid yet has none)
        $underpaid = Money::sum(...$refused->pluck('order_id')->filter()->unique()
            ->flatMap(fn (int $id) => array_filter((array) ($orders->get($id)->sync_flags ?? []), fn (array $flag) => ($flag['flag'] ?? null) === 'underpaid'))
            ->map(fn (array $flag) => (string) ($flag['amount'] ?? '0'))
            ->all());

        $zone = StoreTimezone::of(Store::find($device->store_id));
        $times = $given->concat($refused)->map(fn (PosSyncOperation $op) => $op->occurred_at ?? $op->received_at)->filter();
        $clock = fn (Carbon $time) => $time->copy()->setTimezone($zone)->format('d.m.Y H:i');
        $from = $times->min();
        $to = $times->max();
        $branch = $device->branch_id !== null ? Branch::find($device->branch_id) : null;

        $text = ($rows->isNotEmpty()
                ? '🏷 Internetsiz berilgan chegirmalar: ' . $rows->count() . ' ta, jami ' . PosOwnerAlerts::sum($total)
                : '🏷 Internetsiz berilgan chegirmalar serverda rad etildi')
            . ($branch?->name ? "\nFilial: {$branch->name}" : '')
            . "\nKassa: " . ($device->name ?: (string) $device->getAttribute('sync_prefix'))
            . ($from !== null && $to !== null ? "\nVaqti: " . ($clock($from) === $clock($to) ? $clock($from) : $clock($from) . ' – ' . $clock($to)) : '')
            . ($rows->isNotEmpty() ? "\nKim berdi:\n" . $byStaff->map(fn (array $row) => "• {$row['name']} — {$row['count']} ta, " . PosOwnerAlerts::sum($row['amount']))->implode("\n") : '')
            . ($refused->isNotEmpty() ? "\nRad etilgan: {$refused->count()} ta — " . ($underpaid->isPositive() ? 'schyotlarda ' . PosOwnerAlerts::sum($underpaid) . " kam to'langan, " : '') . 'menejer tekshirsin ("Sinxronlash xatolari").' : '')
            . ($rows->isNotEmpty() ? "\nBu chegirmalar kassa internetsiz ishlagan vaqtda berilgan va aloqa tiklangach serverga yozildi. Batafsil: smena hisobotidagi \"Internetsiz chegirma\" qatori." : '');

        // The message first, the mark after it: a sender that throws leaves these discounts untold for the next batch
        // or run (PosOwnerAlerts::send swallows and logs a delivery problem itself: that one is not repeated)
        $this->alerts->send((int) $device->store_id, $text);
        $this->audit->record(self::SUMMARY_AUDIT, null, [], [], [
            'count' => $rows->count(),
            'amount' => $total->toString(),
            'through_op' => $through,
            'sent' => true,
            'ops' => $rows->map(fn (array $row) => $row['op']->op_id)->all(),
            'by_staff' => $byStaff->map(fn (array $row) => ['staff_id' => $row['staff_id'], 'count' => $row['count'], 'amount' => $row['amount']->toString()])->all(),
            'refused' => $refused->count(),
            'refused_underpaid' => $underpaid->toString(),
            'refused_ops' => $refused->pluck('op_id')->all(),
        ], $context);

        return true;
    }
}
