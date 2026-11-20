<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
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
 * The owner's summary. No alert per discount: after a sync batch the owner gets ONE message per till and outage: how
 * many orders were discounted, the total, who gave them. The rule (afterBatch()):
 *  - what is told: the till's applied `order.discount` operations after the last summary of that till (the audit row
 *    `pos.offline_discount_summary` keeps how far it went), one per order (the last one counts; taken off again within
 *    the outage: nothing to tell), the amount as the order carries it now (a percent follows later rounds);
 *  - when: at the end of the batch that empties the till's queue (the till reports what is left: devices.
 *    sync_pending_ops; a till that reports nothing: at the end of each batch). While more is waiting the message
 *    waits too, so a queue drained in several batches is one message; never longer than 30 minutes after the first
 *    of them reached the server (a later batch of the till then sends it).
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
     * After a sync batch of $device (PosSyncService::sync, inside the till's lock, after the queue report was stored).
     *
     * @param  list<PosSyncOperation>  $results
     */
    public function afterBatch(Device $device, array $results): void
    {
        try {
            $key = "pos-sync:offline-discounts:{$device->id}";
            foreach ($results as $op) {
                if ($op->type === PosSyncOperationType::ORDER_DISCOUNT->value && $op->status !== PosSyncStatus::REJECTED->value) {
                    // Something to tell: kept until told, so a later batch without a discount sends it too
                    Cache::put($key, true, now()->addDays(self::LOOKBACK_DAYS));
                    break;
                }
            }
            if (!Cache::has($key)) {
                return;
            }

            $pending = $this->pending($device);
            $waiting = (int) $device->getAttribute('sync_pending_ops') > 0;
            if ($pending->isNotEmpty() && $waiting && $pending->min('received_at')->gt(now()->subMinutes(self::MAX_WAIT_MINUTES))) {
                return;
            }
            if ($pending->isNotEmpty()) {
                $this->summarize($device, $pending);
            }
            Cache::forget($key);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The till's applied offline discounts nobody told the owner about yet, oldest first.
     *
     * @return Collection<int, PosSyncOperation>
     */
    public function pending(Device $device): Collection
    {
        $since = now()->subDays(self::LOOKBACK_DAYS);
        $told = AuditLog::query()
            ->where('action', self::SUMMARY_AUDIT)
            ->where('created_at', '>=', $since)
            ->where('device_id', $device->id)
            ->orderByDesc('id')
            ->first();

        return PosSyncOperation::query()
            ->where('device_id', $device->id)
            ->where('type', PosSyncOperationType::ORDER_DISCOUNT->value)
            ->where('status', '!=', PosSyncStatus::REJECTED->value)
            ->where('received_at', '>=', $since)
            ->where('id', '>', (int) ($told?->payload_json['through_op'] ?? 0))
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->get()
            // A request the server took online before the outage (its answer lost): that one alerted then
            ->reject(fn (PosSyncOperation $op) => (bool) ($op->result['replayed'] ?? false))
            ->values();
    }

    /**
     * One message to the owner about $pending (a till's untold offline discounts), and the audit row that says how
     * far it went.
     *
     * @param  Collection<int, PosSyncOperation>  $pending
     */
    public function summarize(Device $device, Collection $pending): void
    {
        // One per order: the last decision of the outage; a discount taken off again is not one
        $given = $pending->keyBy('order_id')->filter(fn (PosSyncOperation $op) => ($op->result['discount_kind'] ?? null) !== null)->values();
        $through = (int) $pending->max('id');
        $context = ['actor' => null, 'device' => $device, 'store_id' => (int) $device->store_id, 'branch_id' => $device->branch_id];
        if ($given->isEmpty()) {
            $this->audit->record(self::SUMMARY_AUDIT, null, [], [], ['count' => 0, 'amount' => '0.00', 'through_op' => $through, 'sent' => false], $context);

            return;
        }

        $orders = Order::whereIn('id', $given->pluck('order_id')->all())->get()->keyBy('id');
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

        $zone = StoreTimezone::of(Store::find($device->store_id));
        $times = $rows->map(fn (array $row) => $row['op']->occurred_at ?? $row['op']->received_at)->filter();
        $clock = fn (Carbon $time) => $time->copy()->setTimezone($zone)->format('d.m.Y H:i');
        $from = $times->min();
        $to = $times->max();
        $branch = $device->branch_id !== null ? Branch::find($device->branch_id) : null;

        $text = '🏷 Internetsiz berilgan chegirmalar: ' . $rows->count() . ' ta, jami ' . PosOwnerAlerts::sum($total)
            . ($branch?->name ? "\nFilial: {$branch->name}" : '')
            . "\nKassa: " . ($device->name ?: (string) $device->getAttribute('sync_prefix'))
            . ($from !== null && $to !== null ? "\nVaqti: " . ($clock($from) === $clock($to) ? $clock($from) : $clock($from) . ' – ' . $clock($to)) : '')
            . "\nKim berdi:\n" . $byStaff->map(fn (array $row) => "• {$row['name']} — {$row['count']} ta, " . PosOwnerAlerts::sum($row['amount']))->implode("\n")
            . "\nBu chegirmalar kassa internetsiz ishlagan vaqtda berilgan va aloqa tiklangach serverga yozildi. Batafsil: smena hisobotidagi \"Internetsiz chegirma\" qatori.";

        // The mark first: a message that fails to go out is not sent a second time by the next batch
        $this->audit->record(self::SUMMARY_AUDIT, null, [], [], [
            'count' => $rows->count(),
            'amount' => $total->toString(),
            'through_op' => $through,
            'sent' => true,
            'ops' => $rows->map(fn (array $row) => $row['op']->op_id)->all(),
            'by_staff' => $byStaff->map(fn (array $row) => ['staff_id' => $row['staff_id'], 'count' => $row['count'], 'amount' => $row['amount']->toString()])->all(),
        ], $context);
        $this->alerts->send((int) $device->store_id, $text);
    }
}
