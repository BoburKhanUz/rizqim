<?php

namespace App\Domain\Pos\StaffPay;

use App\Support\Money;

/**
 * P6-23: what a person's wage rules give for one pay period, from their day-by-day work and sales (StaffPayMetrics).
 * Pure arithmetic, no database.
 *
 * Each business day of the period carries the rule version in force that day (the person's own, else their role's;
 * null: no rule). Days under the same version form one part of the period and each component of that version is
 * computed over its days (a rule changed mid-period pays the old one up to the change and the new one after it):
 *
 *   shift_rate              rate × shifts worked
 *   hourly_rate             rate × minutes / 60
 *   sales_percent           % × own sales (refunds taken off)
 *   service_charge_percent  % × service charge collected
 *   event_rate              rate × events of the counted kinds
 *   tips                    the tips of those days
 *   guarantee per shift     Σ over shifts worked: max(guarantee, % × that day's base) or guarantee + % × that day's base
 *   guarantee per period    G = guarantee × days of the part / days of the period (the whole guarantee when one
 *                           version covers the period); max(G, % × base) or G + % × base. It does not depend on the
 *                           number of shifts worked.
 *
 * Every component's amount is rounded once, half up, to the whole so'm; the gross is their sum. A percent is worked
 * out exactly and rounded straight to the so'm (never to the tiyin first: 5% × 10 089.90 = 504.495 gives 504, not
 * 505); a per-shift guarantee rounds each shift's percent to the so'm this way.
 */
final class StaffPayCalculator
{
    /** One component's result on a statement line. */
    public const RESULT = 'array{type: string, label: string, rule_uuid: string|null, days: int, quantity: string|null, rate: string|null, base: string|null, amount: string, detail: string}';

    /**
     * @param  list<array{date: string, rule: array{uuid: string, components: list<array<string, mixed>>}|null, worked: bool, minutes: int, sales: Money, service_charge: Money, events: array<string, int>, tips: Money}>  $days
     * @return array{components: list<array<string, mixed>>, gross: string}
     */
    public function calculate(array $days, int $periodDays): array
    {
        $parts = [];
        foreach ($days as $day) {
            if ($day['rule'] !== null) {
                $parts[$day['rule']['uuid']] ??= ['rule' => $day['rule'], 'days' => []];
                $parts[$day['rule']['uuid']]['days'][] = $day;
            }
        }

        $results = [];
        $gross = Money::zero();
        foreach ($parts as $part) {
            foreach ($part['rule']['components'] as $component) {
                $result = $this->component($component, $part['days'], $part['rule']['uuid'], max(1, $periodDays));
                if ($result === null) {
                    continue;
                }
                $gross = $gross->plus($result['amount']);
                $results[] = $result;
            }
        }

        return ['components' => $results, 'gross' => $gross->toString()];
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  list<array{date: string, rule: array{uuid: string, components: list<array<string, mixed>>}|null, worked: bool, minutes: int, sales: Money, service_charge: Money, events: array<string, int>, tips: Money}>  $days
     * @return array<string, mixed>|null
     */
    private function component(array $component, array $days, string $ruleUuid, int $periodDays): ?array
    {
        $type = PayComponentType::tryFrom((string) ($component['type'] ?? ''));
        if ($type === null) {
            return null;
        }
        $worked = array_values(array_filter($days, fn (array $day) => $day['worked']));
        $rate = Money::of((string) ($component['amount'] ?? '0'));
        $percent = (string) ($component['percent'] ?? '0');
        $sum = fn (string $field, ?array $only = null) => Money::sum(...array_map(fn (array $day) => $day[$field], $only ?? $days));

        $quantity = null;
        $base = null;
        $shownRate = null;
        switch ($type) {
            case PayComponentType::SHIFT_RATE:
                $amount = $rate->times(count($worked));
                $quantity = (string) count($worked);
                $shownRate = $rate->toString();
                $detail = count($worked) . ' smena × ' . self::sum($rate);
                break;
            case PayComponentType::HOURLY_RATE:
                $minutes = array_sum(array_map(fn (array $day) => $day['minutes'], $days));
                $amount = Money::fromTiyin(intdiv($rate->toTiyin() * $minutes + 30, 60));
                $quantity = Money::fromTiyin(intdiv($minutes * 100 + 30, 60))->toString();
                $shownRate = $rate->toString();
                $detail = self::hours($minutes) . ' × ' . self::sum($rate) . '/soat';
                break;
            case PayComponentType::SALES_PERCENT:
            case PayComponentType::SERVICE_CHARGE_PERCENT:
                $field = $type === PayComponentType::SALES_PERCENT ? 'sales' : 'service_charge';
                $baseMoney = $sum($field);
                $amount = $baseMoney->percent($percent, 0);
                $base = $baseMoney->toString();
                $shownRate = $percent;
                $detail = self::percentText($percent) . ' × ' . self::sum($baseMoney);
                break;
            case PayComponentType::EVENT_RATE:
                $kinds = PayComponents::countedEventTypes($component);
                $events = 0;
                foreach ($days as $day) {
                    foreach ($kinds as $kind) {
                        $events += (int) ($day['events'][$kind] ?? 0);
                    }
                }
                $amount = $rate->times($events);
                $quantity = (string) $events;
                $shownRate = $rate->toString();
                $detail = $events . ' tadbir × ' . self::sum($rate);
                break;
            case PayComponentType::TIPS:
                $amount = $sum('tips');
                $detail = 'Davrdagi choy puli';
                break;
            case PayComponentType::GUARANTEE:
                $field = ($component['base'] ?? 'sales') === PercentBase::SERVICE_CHARGE->value ? 'service_charge' : 'sales';
                $mode = GuaranteeMode::tryFrom((string) ($component['mode'] ?? '')) ?? GuaranteeMode::MAX;
                $shownRate = $rate->toString();
                if (($component['per'] ?? 'shift') === GuaranteePer::PERIOD->value) {
                    $guarantee = Money::fromTiyin(intdiv($rate->toTiyin() * count($days) * 2 + $periodDays, 2 * $periodDays));
                    $baseMoney = $sum($field);
                    $share = $baseMoney->percent($percent, 0);
                    $amount = $mode === GuaranteeMode::MAX ? Money::max($guarantee, $share) : $guarantee->plus($share);
                    $base = $baseMoney->toString();
                    $quantity = (string) count($days);
                    $detail = ($mode === GuaranteeMode::MAX ? 'max(' : '') . 'garant ' . self::sum($guarantee)
                        . ($mode === GuaranteeMode::MAX ? ', ' : ' + ') . self::percentText($percent) . ' × ' . self::sum($baseMoney) . ($mode === GuaranteeMode::MAX ? ')' : '')
                        . (count($days) < $periodDays ? ' (' . count($days) . '/' . $periodDays . ' kun)' : '');
                } else {
                    $amount = Money::zero();
                    $covered = 0;
                    foreach ($worked as $day) {
                        $share = $day[$field]->percent($percent, 0);
                        if ($mode === GuaranteeMode::MAX) {
                            $covered += $share->isLessThan($rate) ? 1 : 0;
                            $amount = $amount->plus(Money::max($rate, $share));
                        } else {
                            $amount = $amount->plus($rate)->plus($share);
                        }
                    }
                    $baseMoney = $sum($field, $worked);
                    $base = $baseMoney->toString();
                    $quantity = (string) count($worked);
                    $detail = $mode === GuaranteeMode::MAX
                        ? count($worked) . ' smena: har biri max(' . self::sum($rate) . ', ' . self::percentText($percent) . ') — ' . $covered . ' tasida garant'
                        : count($worked) . ' smena × ' . self::sum($rate) . ' + ' . self::percentText($percent) . ' × ' . self::sum($baseMoney);
                }
                break;
        }

        return [
            'type' => $type->value,
            'label' => $type->label(),
            'rule_uuid' => $ruleUuid,
            'days' => count($days),
            'quantity' => $quantity,
            'rate' => $shownRate,
            'base' => $base,
            'amount' => $amount->roundTo(0)->toString(),
            'detail' => $detail,
        ];
    }

    private static function sum(Money $amount): string
    {
        return number_format((float) $amount->roundTo(0)->toString(), 0, '.', ' ') . " so'm";
    }

    private static function percentText(string $percent): string
    {
        return rtrim(rtrim($percent, '0'), '.') . '%';
    }

    private static function hours(int $minutes): string
    {
        return intdiv($minutes, 60) . ' soat ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . ' daq';
    }
}
