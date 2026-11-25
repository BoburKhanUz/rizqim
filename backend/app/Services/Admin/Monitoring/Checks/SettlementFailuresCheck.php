<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Models\Order;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Finance\SettlementRecoveryService;

/**
 * At least `threshold` delivered orders are waiting for their ledger booking (orders.settlement_failed_at set; the
 * partial index keeps this off the table). Cleared by SettlementRecoveryService when the booking succeeds.
 *
 * Review H1: a second finding (subject `unmarked`) for the delivered, platform-collected, paid orders whose money was
 * never credited to the store and that carry no marker, paid between 15 minutes and 30 days ago
 * (SettlementRecoveryService::unbookedLatePayments): a late payment's settlement that failed when even the marker
 * could not be written. No retry books those by itself; the same threshold applies. Finance may have credited one by
 * hand already (an adjustment carries no order_id): docs/OPERATIONS.md, check the adjustments, then
 * `orders:retry-settlements <id> --confirm-unbooked`.
 */
class SettlementFailuresCheck implements AlertCheck
{
    public function findings(AlertRule $rule): ?array
    {
        $findings = [];
        $failed = Order::query()->whereNotNull('settlement_failed_at');

        $count = (clone $failed)->count();
        if ($count >= $rule->threshold) {
            $oldest = (clone $failed)->min('settlement_failed_at');

            $findings[] = new AlertFinding('global', "{$count} ta buyurtma puli hisobga yozilmadi", [
                'count' => $count,
                'threshold' => $rule->threshold,
                'oldest_failed_at' => $oldest !== null ? \Illuminate\Support\Carbon::parse($oldest)->toIso8601String() : null,
                'orders' => (clone $failed)->orderBy('settlement_failed_at')->limit(5)->pluck('uuid')->all(),
            ]);
        }

        $unmarked = SettlementRecoveryService::unbookedLatePayments();
        $unmarkedCount = (clone $unmarked)->count();
        if ($unmarkedCount >= $rule->threshold) {
            $oldest = (clone $unmarked)->toBase()->selectSub(SettlementRecoveryService::paidAtSubquery(), 'paid_at')
                ->orderBy('paid_at')->limit(1)->value('paid_at');

            $findings[] = new AlertFinding('unmarked', "{$unmarkedCount} ta to'langan buyurtma puli do'konga yozilmadi (qayta urinish belgisisiz)", [
                'count' => $unmarkedCount,
                'threshold' => $rule->threshold,
                'oldest_paid_at' => $oldest !== null ? \Illuminate\Support\Carbon::parse((string) $oldest)->toIso8601String() : null,
                'orders' => (clone $unmarked)->select('orders.*')->selectSub(SettlementRecoveryService::paidAtSubquery(), 'paid_at')
                    ->orderBy('paid_at')->orderBy('id')->limit(5)->pluck('uuid')->all(),
            ]);
        }

        return $findings;
    }
}
