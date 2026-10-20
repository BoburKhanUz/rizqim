<?php

namespace App\Enums;

enum DeviceType: string
{
    // Shared store terminals, paired with a one-time code (they are not a person)
    case POS = 'pos';
    case KDS = 'kds';
    // A person's own app install, registered after they signed in
    case COURIER = 'courier';
    case PARTNER_MOBILE = 'partner_mobile';
    case CUSTOMER_MOBILE = 'customer_mobile';
    case WEB = 'web';

    public function isTerminal(): bool
    {
        return in_array($this, self::terminals(), true);
    }

    /**
     * @return list<self>
     */
    public static function terminals(): array
    {
        return [self::POS, self::KDS];
    }

    /**
     * Types a signed-in user may register for themselves.
     *
     * @return list<string>
     */
    public static function personalValues(): array
    {
        return array_values(array_map(
            fn (self $type) => $type->value,
            array_filter(self::cases(), fn (self $type) => !$type->isTerminal())
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::POS => 'Kassa (POS)',
            self::KDS => 'Oshxona ekrani (KDS)',
            self::COURIER => 'Kuryer ilovasi',
            self::PARTNER_MOBILE => 'Hamkor ilovasi',
            self::CUSTOMER_MOBILE => 'Mijoz ilovasi',
            self::WEB => 'Brauzer',
        };
    }
}
