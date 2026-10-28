<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Models\Order;
use App\Services\Admin\Monitoring\AlertFinding;

/**
 * At least `threshold` delivered orders are waiting for their ledger booking (orders.settlement_failed_at set; the
 * partial index keeps this off the table). Cleared by SettlementRecoveryService when the booking succeeds.
 */
class SettlementFailuresCheck implements AlertCheck
{
    public function findings(AlertRule $rule): ?array
    {
        $failed = Order::query()->whereNotNull('settlement_failed_at');

        $count = (clone $failed)->count();
        if ($count < $rule->threshold) {
            return [];
        }

        $oldest = (clone $failed)->min('settlement_failed_at');

        return [new AlertFinding('global', "{$count} ta buyurtma puli hisobga yozilmadi", [
            'count' => $count,
            'threshold' => $rule->threshold,
            'oldest_failed_at' => $oldest !== null ? \Illuminate\Support\Carbon::parse($oldest)->toIso8601String() : null,
            'orders' => (clone $failed)->orderBy('settlement_failed_at')->limit(5)->pluck('uuid')->all(),
        ])];
    }
}
