<?php

namespace App\Domain\Pos\Menu;

/**
 * Why unsold portions of a batch were taken off (P6-15): `unsold` is the end-of-day leftover, the others are losses.
 */
enum WriteOffReason: string
{
    case UNSOLD = 'unsold';
    case SPOILED = 'spoiled';
    case STAFF_MEAL = 'staff_meal';
    case DROPPED = 'dropped';
    case EXPIRED = 'expired';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::UNSOLD => 'Sotilmay qoldi',
            self::SPOILED => 'Buzildi',
            self::STAFF_MEAL => 'Xodimlar ovqati',
            self::DROPPED => "To'kildi yoki tushib ketdi",
            self::EXPIRED => 'Muddati tugadi',
            self::OTHER => 'Boshqa sabab',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $reason) => $reason->value, self::cases());
    }
}
