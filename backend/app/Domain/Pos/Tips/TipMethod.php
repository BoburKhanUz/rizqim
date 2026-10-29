<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Contracts\PosTender;

/**
 * P6-22: how a tip was given (pos_tips.method): with a cash, card (the bank terminal charged it with the bill) or
 * QR payment. A cash tip stays in the drawer until it is paid out (a `wage` movement, category `tips`).
 */
enum TipMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case QR = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Naqd',
            self::CARD => 'Karta',
            self::QR => 'QR',
        };
    }

    /**
     * The tip's method for a tender; null: the tender takes no tip (a deposit, nasiya).
     */
    public static function forTender(PosTender $tender): ?self
    {
        return match ($tender) {
            PosTender::CASH => self::CASH,
            PosTender::CARD => self::CARD,
            PosTender::QR => self::QR,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $method) => $method->value, self::cases());
    }
}
