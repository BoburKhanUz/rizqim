<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Models\AlertRule;
use App\Models\FiscalReceipt;
use App\Models\Store;
use App\Services\Admin\Monitoring\AlertFinding;

/**
 * At least `threshold` fiscal receipts turned `failed` in the last `window_minutes`.
 */
class FiscalFailuresCheck implements AlertCheck
{
    public function findings(AlertRule $rule): ?array
    {
        $window = max(1, (int) ($rule->window_minutes ?? 60));

        $failed = FiscalReceipt::query()
            ->where('status', FiscalReceiptStatus::FAILED->value)
            ->where('updated_at', '>=', now()->subMinutes($window));

        $count = (clone $failed)->count();
        if ($count < $rule->threshold) {
            return $this->pendingBeyondRetry($rule);
        }

        $stores = (clone $failed)->selectRaw('store_id, count(*) as failures')->groupBy('store_id')->orderByDesc('failures')->limit(5)->get();
        $names = Store::query()->whereIn('id', $stores->pluck('store_id'))->pluck('name', 'id');

        return array_merge([new AlertFinding('global', "Oxirgi {$window} daqiqada {$count} ta fiskal chek yiqildi", [
            'count' => $count,
            'window_minutes' => $window,
            'threshold' => $rule->threshold,
            'stores' => $stores->map(fn ($row) => ['store_id' => (int) $row->store_id, 'name' => $names[$row->store_id] ?? null, 'failures' => (int) $row->getAttribute('failures')])->all(),
        ])], $this->pendingBeyondRetry($rule));
    }

    /**
     * M3 extension point (not measured yet): receipts still `pending` after the fiscal retry window
     * (config fiscal.retry) are as lost to the tax office as failed ones. The fiscal domain decides what "beyond the
     * retry window" means; it returns its findings here under its own subject key (e.g. `pending`), never `global`.
     *
     * @return list<AlertFinding>
     */
    protected function pendingBeyondRetry(AlertRule $rule): array
    {
        return [];
    }
}
