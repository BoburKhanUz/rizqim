<?php

namespace App\Domain\Pos\StaffPay;

/**
 * P6-23: how a guarantee meets its percent: the larger of the two ("max(garant, %)"), or both ("garant + %").
 */
enum GuaranteeMode: string
{
    case MAX = 'max';
    case PLUS = 'plus';

    public function label(): string
    {
        return match ($this) {
            self::MAX => 'Garant yoki foiz (qaysi katta)',
            self::PLUS => 'Garant + foiz',
        };
    }
}
