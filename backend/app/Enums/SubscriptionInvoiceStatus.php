<?php

namespace App\Enums;

enum SubscriptionInvoiceStatus: string
{
    case ISSUED = 'issued';
    case PAID = 'paid';
    case VOID = 'void';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => "To'lanmagan",
            self::PAID => "To'langan",
            self::VOID => 'Bekor qilingan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ISSUED => 'warning',
            self::PAID => 'success',
            self::VOID => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
