<?php

namespace App\Enums;

/**
 * P4-21: how often a subscription pays its base fee. Monthly: each month is billed when it ends. Yearly: 12 billing
 * months are billed in advance when the term starts, for config('billing.yearly_months_charged') months' price; extra
 * devices are still billed monthly when each month ends (docs/BILLING.md, "Yillik davr").
 */
enum BillingPeriod: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Oylik',
            self::YEARLY => 'Yillik',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $period) => $period->value, self::cases());
    }
}
