<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalService;
use App\Models\AlertRule;
use App\Models\FiscalReceipt;
use App\Models\Store;
use App\Services\Admin\Monitoring\AlertFinding;

/**
 * At least `threshold` fiscal receipts turned `failed` in the last `window_minutes` (subject `global`).
 *
 * M3: the fiscal retry window (FiscalService::maxAgeDays, config fiscal.retry.max_age_days). The sweep fails a receipt
 * still pending beyond it; those failures are counted in `global` and also reported on their own (subject `expired`,
 * from one receipt: a sale that never reached the tax office and is no longer retried by itself). A receipt still
 * `pending` beyond the window (subject `pending_expired`) should not exist at all: the sweep is not running.
 * "Outlived the window" is decided by the receipt's age, not by its error text.
 */
class FiscalFailuresCheck implements AlertCheck
{
    public function __construct(
        private readonly FiscalService $fiscal,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $window = max(1, (int) ($rule->window_minutes ?? 60));
        $days = $this->fiscal->maxAgeDays();
        $since = now()->subDays($days);

        $failed = FiscalReceipt::query()
            ->where('status', FiscalReceiptStatus::FAILED->value)
            ->where('updated_at', '>=', now()->subMinutes($window));

        $count = (clone $failed)->count();
        $expired = (clone $failed)->where('created_at', '<', $since)->count();

        $findings = [];
        if ($count >= $rule->threshold) {
            $stores = (clone $failed)->selectRaw('store_id, count(*) as failures')->groupBy('store_id')->orderByDesc('failures')->limit(5)->get();
            $names = Store::query()->whereIn('id', $stores->pluck('store_id'))->pluck('name', 'id');

            $findings[] = new AlertFinding('global', "Oxirgi {$window} daqiqada {$count} ta fiskal chek yiqildi" . ($expired > 0 ? " (shundan {$expired} tasi {$days} kundan ortiq kutib qolgan)" : ''), [
                'count' => $count,
                'expired' => $expired,
                'window_minutes' => $window,
                'threshold' => $rule->threshold,
                'stores' => $stores->map(fn ($row) => ['store_id' => (int) $row->store_id, 'name' => $names[$row->store_id] ?? null, 'failures' => (int) $row->getAttribute('failures')])->all(),
            ]);
        }

        if ($expired > 0) {
            $findings[] = new AlertFinding('expired', "{$expired} ta savdoning fiskal cheki {$days} kun ichida soliq tizimida ro'yxatdan o'tmadi va endi o'zi qayta yuborilmaydi: Kassalar bo'limida chekni qo'lda qayta yuboring", [
                'count' => $expired,
                'max_age_days' => $days,
                'window_minutes' => $window,
            ], null, 'critical');
        }

        return array_merge($findings, $this->pendingBeyondRetry($since, $days));
    }

    /**
     * Receipts still `pending` older than the retry window: always 0 while the sweep (fiscal:retry-pending) runs.
     *
     * @return list<AlertFinding>
     */
    protected function pendingBeyondRetry(\DateTimeInterface $since, int $days): array
    {
        $pending = FiscalReceipt::query()->where('status', FiscalReceiptStatus::PENDING->value)->where('created_at', '<', $since);
        $count = (clone $pending)->count();
        if ($count === 0) {
            return [];
        }
        $oldest = (clone $pending)->min('created_at');

        return [new AlertFinding('pending_expired', "{$count} ta savdo {$days} kundan ortiq fiskal cheksiz turibdi (soliqda ko'rinmaydi): fiskal qayta yuborish ishi to'xtagan, scheduler'ni tekshiring va cheklarni qo'lda qayta yuboring", [
            'count' => $count,
            'max_age_days' => $days,
            'oldest_created_at' => $oldest !== null ? \Illuminate\Support\Carbon::parse((string) $oldest)->toIso8601String() : null,
        ], null, 'critical')];
    }
}
