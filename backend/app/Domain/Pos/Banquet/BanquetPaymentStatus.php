<?php

namespace App\Domain\Pos\Banquet;

/**
 * A schedule row's state (P6-53): due (not paid yet), paid (money taken, ADVANCE receipt), refunded or retained on a
 * cancellation.
 */
enum BanquetPaymentStatus: string
{
    case DUE = 'due';
    case PAID = 'paid';
    case REFUNDED = 'refunded';
    case RETAINED = 'retained';

    public function label(): string
    {
        return match ($this) {
            self::DUE => "To'lanmagan",
            self::PAID => "To'langan",
            self::REFUNDED => 'Qaytarilgan',
            self::RETAINED => 'Ushlab qolingan',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
