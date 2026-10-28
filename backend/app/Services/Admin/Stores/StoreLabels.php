<?php

namespace App\Services\Admin\Stores;

use App\Enums\StoreStatus;

/**
 * P4-22: Uzbek words of the Ovqatxonalar section (StoreStatus::getLabel() is English).
 */
final class StoreLabels
{
    public static function status(StoreStatus|string|null $status): string
    {
        $status = $status instanceof StoreStatus ? $status : StoreStatus::tryFrom((string) $status);

        return match ($status) {
            StoreStatus::PENDING => 'Ulanmoqda',
            StoreStatus::UNDER_REVIEW => 'Tekshiruvda',
            StoreStatus::ACTIVE => 'Faol',
            StoreStatus::SUSPENDED => "To'xtatilgan",
            StoreStatus::REJECTED => 'Rad etilgan',
            StoreStatus::PAYMENT_REQUIRED => "Qarz limitidan oshgan",
            StoreStatus::FINANCE_HOLD => "Moliyaviy tekshiruv",
            null => "Noma'lum",
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        $labels = [];
        foreach (StoreStatus::cases() as $status) {
            $labels[$status->value] = self::status($status);
        }

        return $labels;
    }
}
