<?php

namespace App\Services\Admin\Metrics;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * P4-31 Ko'rsatkichlar: churn per month. The base is the stores active at the start of the month (status `active`,
 * StoreActivity). A store of the base churned in the month when it
 *
 *  - went inactive (suspended, finance hold, rejected, deleted...) by the month's end, or
 *  - was paying (MrrCalculator) at the start and no longer at the end: its subscription ended.
 *
 * Rate = churned stores / stores active at the start, in percent with one decimal (null for an empty base). The
 * revenue churn is the MRR those paying stores took away, as a share of the MRR at the start (the movement table's
 * "churned" row). The month in progress is partial: its rate is for the days so far.
 */
class ChurnReport
{
    /**
     * @param  list<CarbonImmutable>  $boundaries
     * @param  list<MrrSnapshot>  $snapshots  one per boundary
     * @param  list<array<int, true>>  $active  one per boundary
     * @return list<array<string, mixed>>
     */
    public function months(array $boundaries, array $snapshots, array $active): array
    {
        $rows = [];
        $last = count($boundaries) - 2;

        for ($i = 0; $i <= $last; $i++) {
            $start = $active[$i];
            $end = $active[$i + 1];
            $paidBefore = $snapshots[$i]->byStore;
            $paidAfter = $snapshots[$i + 1]->byStore;

            $inactive = 0;
            $ended = 0;
            $lostMrr = BigDecimal::zero();
            foreach ($start as $storeId => $_) {
                $wentInactive = !isset($end[$storeId]);
                $subscriptionEnded = isset($paidBefore[$storeId]) && !isset($paidAfter[$storeId]);
                if ($wentInactive) {
                    $inactive++;
                } elseif ($subscriptionEnded) {
                    $ended++;
                }
                if ($subscriptionEnded) {
                    $lostMrr = $lostMrr->plus($paidBefore[$storeId]);
                }
            }

            $base = count($start);
            $churned = $inactive + $ended;
            $opening = $snapshots[$i]->total();

            $rows[] = [
                'month' => MetricsMonths::key($boundaries[$i]),
                'partial' => $i === $last,
                'active_at_start' => $base,
                'active_at_end' => count($end),
                'churned' => $churned,
                'went_inactive' => $inactive,
                'subscription_ended' => $ended,
                'rate' => $this->percent($churned, $base),
                'revenue_rate' => $opening->isPositive()
                    ? $lostMrr->multipliedBy(100)->dividedBy($opening, 1, RoundingMode::HALF_UP)->__toString()
                    : null,
            ];
        }

        return $rows;
    }

    private function percent(int $part, int $whole): ?string
    {
        return $whole > 0
            ? BigDecimal::of($part)->multipliedBy(100)->dividedBy($whole, 1, RoundingMode::HALF_UP)->__toString()
            : null;
    }
}
