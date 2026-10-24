<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use App\Support\StoreTimezone;
use Illuminate\Support\Carbon;

/**
 * The owner's alerts about POS order decisions (R20, POS_SPEC 2.10), through PosOwnerAlerts (the store's admin chat
 * and every owner, by their notification preferences, after the change commits). Thresholds are the branch's
 * `pos_settings.alerts` (P3-13, PosSettings; null switches one off):
 *
 *  - a line voided after it was sent to the kitchen, worth at least `void_amount`;
 *  - a waived charge line (room fee, minimum spend top-up, service charge, place fee): always — the research names
 *    each one (R20);
 *  - a discount (P3-37) of at least `discount_percent` of the dishes (an amount by its share of them);
 *  - a cancelled order: always;
 *  - a hall order open longer than `open_room_hours`: once per order (orders.long_open_alerted_at), by the
 *    pos:alert-long-open schedule.
 *
 * Deposits refunded or retained and nasiya alert from P3-17's own services, not here.
 */
class PosOrderAlerts
{
    /** Hall orders looked at per run of the long-open check */
    public const LONG_OPEN_BATCH = 500;

    public function __construct(
        protected PosOwnerAlerts $alerts,
    ) {}

    /**
     * @return array{open_room_hours: int|null, void_amount: string|null, discount_percent: string|null, unaccepted_online_minutes: int|null, cash_out_amount: string|null}
     */
    public function thresholds(?Branch $branch): array
    {
        $stored = $branch?->getAttribute('pos_settings');

        return PosSettings::resolve(is_array($stored) ? $stored : null)['alerts'];
    }

    public function voidedAfterSend(Order $order, OrderItem $item, User $actor, ?int $approvedBy, ?string $reason): void
    {
        $threshold = $this->thresholds($order->branch)['void_amount'];
        $amount = Money::of($item->total_price);
        if ($threshold === null || $amount->isLessThan($threshold)) {
            return;
        }

        $this->alerts->send((int) $order->store_id, "🗑 Oshxonaga yuborilgan taom o'chirildi: " . PosOwnerAlerts::sum($amount)
            . "\n{$item->displayLine()}"
            . "\n{$this->place($order)}"
            . ($reason !== null ? "\nSabab: {$reason}" : '')
            . "\n{$this->people($actor, $approvedBy)}");
    }

    public function chargeWaived(Order $order, PosCharge $charge, string $amount, string $reason, User $actor, ?int $approvedBy): void
    {
        if (!Money::of($amount)->isPositive()) {
            return;
        }

        $this->alerts->send((int) $order->store_id, "✂️ {$charge->label()} olib tashlandi: " . PosOwnerAlerts::sum($amount)
            . "\n{$this->place($order)}"
            . "\nSabab: {$reason}"
            . "\n{$this->people($actor, $approvedBy)}");
    }

    /**
     * P3-37: a discount given at the till, when its percent of the dishes reaches the branch's `discount_percent`.
     */
    public function discountGiven(Order $order, string $kind, Money $value, Money $percent, string $reason, User $actor, ?int $approvedBy): void
    {
        $threshold = $this->thresholds($order->branch)['discount_percent'];
        if ($threshold === null || $percent->isLessThan($threshold)) {
            return;
        }

        $given = $kind === 'percent'
            ? rtrim(rtrim($value->toString(), '0'), '.') . '%'
            : PosOwnerAlerts::sum($value) . ' (' . rtrim(rtrim($percent->toString(), '0'), '.') . '%)';

        $this->alerts->send((int) $order->store_id, "🏷 Chegirma berildi: {$given} — " . PosOwnerAlerts::sum((string) $order->discount)
            . "\n{$this->place($order)}"
            . "\nSabab: {$reason}"
            . "\n{$this->people($actor, $approvedBy)}");
    }

    /**
     * @param  Money  $leftDeposit  booking deposit still taken (to refund or retain)
     */
    public function orderCancelled(Order $order, string $amount, string $reason, User $actor, ?int $approvedBy, Money $leftDeposit): void
    {
        $this->alerts->send((int) $order->store_id, "❌ Buyurtma bekor qilindi: " . PosOwnerAlerts::sum($amount)
            . "\n{$this->place($order)}"
            . "\nSabab: {$reason}"
            . ($leftDeposit->isPositive() ? "\nBron avansi qoldi: " . PosOwnerAlerts::sum($leftDeposit) . ' (qaytarish yoki ushlab qolish kerak)' : '')
            . "\n{$this->people($actor, $approvedBy)}");
    }

    /**
     * The owner hears once about each open hall order (at a table, room or tapchan) older than its branch's
     * `open_room_hours`. Returns how many were reported.
     */
    public function checkLongOpen(?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        $orders = Order::query()
            ->with('branch')
            ->where('channel', OrderChannel::POS_HALL->value)
            ->whereIn('status', array_map(fn ($status) => $status->value, PosOrderService::OPEN_STATUSES))
            ->whereNull('closed_at')
            ->whereNotNull('table_id')
            ->whereNull('long_open_alerted_at')
            // The smallest threshold is one hour: younger orders are never due
            ->where('created_at', '<=', $now->copy()->subHour())
            ->orderBy('id')
            ->limit(self::LONG_OPEN_BATCH)
            ->get();

        foreach ($orders as $order) {
            $hours = $this->thresholds($order->branch)['open_room_hours'];
            if ($hours === null || $order->created_at === null || $order->created_at->gt($now->copy()->subHours($hours))) {
                continue;
            }

            // Claimed atomically: two schedulers (or a slow run overlapping the next) report an order once
            $claimed = Order::whereKey($order->id)->whereNull('long_open_alerted_at')->update(['long_open_alerted_at' => $now]);
            if ($claimed === 0) {
                continue;
            }

            $open = (int) floor($order->created_at->diffInMinutes($now, true) / 60);
            $this->alerts->send((int) $order->store_id, "⏰ Xona {$open} soatdan beri ochiq (chegara: {$hours} soat)"
                . "\n{$this->place($order)}"
                . "\nOchildi: " . $order->created_at->copy()->setTimezone(StoreTimezone::of($order->store))->format('d.m.Y H:i')
                . "\nHisob: " . PosOwnerAlerts::sum((string) $order->total_price));
            $sent++;
        }

        return $sent;
    }

    private function place(Order $order): string
    {
        $where = array_filter([$order->hall_ref, $order->table_ref]);

        return "Buyurtma #{$order->short_code}" . ($where !== [] ? ' (' . implode(', ', $where) . ')' : '')
            . ($order->branch?->name ? "\nFilial: {$order->branch->name}" : '');
    }

    private function people(User $actor, ?int $approvedBy): string
    {
        $approver = $approvedBy !== null ? User::whereKey($approvedBy)->value('name') : null;

        return "Kim: {$actor->name}" . ($approver !== null ? "\nTasdiqladi: {$approver}" : '');
    }
}
