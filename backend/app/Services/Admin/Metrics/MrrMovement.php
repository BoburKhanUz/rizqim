<?php

namespace App\Services\Admin\Metrics;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * P4-31 Ko'rsatkichlar: how the recurring revenue moved month by month. For each store the MRR at the start of the
 * month is compared with the MRR at its end:
 *
 *  - new: nothing before, something after (a store began paying, a trial ended included; a store that returns counts here too);
 *  - expansion: paying both times, more at the end (a higher plan, an extra terminal, monthly → no change in price);
 *  - contraction: paying both times, less at the end;
 *  - churned: something before, nothing after.
 *
 * opening + new + expansion − contraction − churned = closing, to the tiyin.
 */
class MrrMovement
{
    /**
     * @param  list<CarbonImmutable>  $boundaries  MetricsMonths::boundaries()
     * @param  list<MrrSnapshot>  $snapshots  one per boundary
     * @return list<array<string, mixed>> one row per month
     */
    public function months(array $boundaries, array $snapshots): array
    {
        $rows = [];
        $last = count($boundaries) - 2;

        for ($i = 0; $i <= $last; $i++) {
            $from = $snapshots[$i]->byStore;
            $to = $snapshots[$i + 1]->byStore;

            $new = $expansion = $contraction = $churned = BigDecimal::zero();
            $counts = ['new' => 0, 'expansion' => 0, 'contraction' => 0, 'churned' => 0];

            foreach ($to as $storeId => $after) {
                $before = $from[$storeId] ?? null;
                if ($before === null) {
                    $new = $new->plus($after);
                    $counts['new']++;
                } elseif ($after->isGreaterThan($before)) {
                    $expansion = $expansion->plus($after->minus($before));
                    $counts['expansion']++;
                } elseif ($after->isLessThan($before)) {
                    $contraction = $contraction->plus($before->minus($after));
                    $counts['contraction']++;
                }
            }
            foreach ($from as $storeId => $before) {
                if (!isset($to[$storeId])) {
                    $churned = $churned->plus($before);
                    $counts['churned']++;
                }
            }

            $opening = $snapshots[$i]->total();
            $closing = $snapshots[$i + 1]->total();

            $rows[] = [
                'month' => MetricsMonths::key($boundaries[$i]),
                'partial' => $i === $last,
                'opening' => $opening->toScale(2)->__toString(),
                'closing' => $closing->toScale(2)->__toString(),
                'new' => ['mrr' => $new->toScale(2)->__toString(), 'stores' => $counts['new']],
                'expansion' => ['mrr' => $expansion->toScale(2)->__toString(), 'stores' => $counts['expansion']],
                'contraction' => ['mrr' => $contraction->toScale(2)->__toString(), 'stores' => $counts['contraction']],
                'churned' => ['mrr' => $churned->toScale(2)->__toString(), 'stores' => $counts['churned']],
                'net_new' => $new->plus($expansion)->minus($contraction)->minus($churned)->toScale(2)->__toString(),
                'paying_stores' => $snapshots[$i + 1]->payingStores(),
                'trialing_stores' => $snapshots[$i + 1]->trialing,
            ];
        }

        return $rows;
    }
}
