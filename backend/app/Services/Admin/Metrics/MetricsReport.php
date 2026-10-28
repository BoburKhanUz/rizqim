<?php

namespace App\Services\Admin\Metrics;

use App\Services\Admin\Today\PlatformStats;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * P4-31 Ko'rsatkichlar: GET /admin/metrics. One answer for the page: the cards, the MRR series with its movement,
 * churn, active stores and the GMV / commission per month. Definitions: MrrCalculator, MrrMovement, ChurnReport,
 * StoreActivity, RevenueSeries (docs/API.md "Ko'rsatkichlar"). Cached for a few minutes: MRR and churn read the
 * subscription, device and status-history tables, not orders; the revenue part reads the Bugun aggregate.
 */
class MetricsReport
{
    public function __construct(
        private readonly MrrCalculator $mrr,
        private readonly MrrMovement $movement,
        private readonly StoreActivity $activity,
        private readonly ChurnReport $churn,
        private readonly RevenueSeries $revenue,
    ) {}

    public static function ttl(): int
    {
        return (int) config('admin.metrics.cache_seconds', 300);
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(int $months = MetricsMonths::DEFAULT, ?CarbonImmutable $now = null): array
    {
        $months = MetricsMonths::clamp($months);

        if ($now !== null) {
            return $this->compute($months, $now);
        }

        return Cache::remember(self::cacheKey($months), self::ttl(), fn () => $this->compute($months, PlatformStats::now()));
    }

    public static function cacheKey(int $months): string
    {
        return 'admin.metrics.overview.' . $months;
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(int $months, CarbonImmutable $now): array
    {
        $boundaries = MetricsMonths::boundaries($months, $now);
        $snapshots = $this->mrr->at($boundaries);
        $active = $this->activity->at($boundaries);

        $movement = $this->movement->months($boundaries, $snapshots);
        $churn = $this->churn->months($boundaries, $snapshots, $active);
        $revenue = $this->revenue->months($boundaries);

        $series = [];
        foreach ($movement as $i => $row) {
            $series[] = [
                'month' => $row['month'],
                'partial' => $row['partial'],
                'mrr' => $row['closing'],
                'paying_stores' => $row['paying_stores'],
                'trialing_stores' => $row['trialing_stores'],
                'active_stores' => $churn[$i]['active_at_end'],
            ];
        }

        $current = $snapshots[count($snapshots) - 1];
        $mrr = $current->total();
        $paying = $current->payingStores();
        $monthStart = $snapshots[count($snapshots) - 2]->total();
        $lastFull = $this->lastFull($churn);

        return [
            'timezone' => PlatformStats::TIMEZONE,
            'generated_at' => $now->setTimezone(PlatformStats::TIMEZONE)->toIso8601String(),
            'months' => $months,
            'cards' => [
                'mrr' => $mrr->__toString(),
                'mrr_change' => $this->change($mrr, $monthStart),
                'arr' => $mrr->multipliedBy(12)->toScale(2)->__toString(),
                'paying_stores' => $paying,
                'trialing_stores' => $current->trialing,
                'arpa' => $paying > 0 ? $mrr->dividedBy($paying, 2, RoundingMode::HALF_UP)->__toString() : null,
                'active_stores' => count($active[count($active) - 1]),
                'churn_rate' => $lastFull['rate'] ?? null,
                'churn_month' => $lastFull['month'] ?? null,
                'gmv_month' => $revenue[count($revenue) - 1]['gmv'],
                'commission_month' => $revenue[count($revenue) - 1]['commission'],
                'revenue_complete' => $revenue[count($revenue) - 1]['complete'],
            ],
            'series' => $series,
            'movement' => $movement,
            'churn' => $churn,
            'revenue' => $revenue,
        ];
    }

    /**
     * The latest finished month's churn row.
     *
     * @param  list<array<string, mixed>>  $churn
     * @return array<string, mixed>|null
     */
    private function lastFull(array $churn): ?array
    {
        for ($i = count($churn) - 1; $i >= 0; $i--) {
            if (!$churn[$i]['partial']) {
                return $churn[$i];
            }
        }

        return null;
    }

    /**
     * `up` / `down` / `same` against the start of the month, and by how many percent (one decimal; null from nothing).
     *
     * @return array{direction: string, amount: string, percent: string|null}
     */
    private function change(BigDecimal $now, BigDecimal $before): array
    {
        $difference = $now->minus($before);

        return [
            'direction' => match ($now->compareTo($before)) {
                1 => 'up',
                -1 => 'down',
                default => 'same',
            },
            'amount' => $difference->toScale(2)->__toString(),
            'percent' => $before->isPositive()
                ? $difference->abs()->multipliedBy(100)->dividedBy($before, 1, RoundingMode::HALF_UP)->__toString()
                : null,
        ];
    }
}
