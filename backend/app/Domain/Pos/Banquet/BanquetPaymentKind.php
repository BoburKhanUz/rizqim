<?php

namespace App\Domain\Pos\Banquet;

/**
 * A row of an event's payment schedule (P6-53): the deposit (zaklad) that confirms the event, interims, the final.
 */
enum BanquetPaymentKind: string
{
    case DEPOSIT = 'deposit';
    case INTERIM = 'interim';
    case FINAL = 'final';

    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT => 'Zaklad',
            self::INTERIM => "Oraliq to'lov",
            self::FINAL => "Yakuniy to'lov",
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $kind) => $kind->value, self::cases());
    }
}
