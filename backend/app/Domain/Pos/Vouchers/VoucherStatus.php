<?php

namespace App\Domain\Pos\Vouchers;

/**
 * P6-43: a voucher's state. `used`: nothing left of it; `blocked`: a manager stopped it (vouchers.manage; it can be
 * unblocked); `expired`: its last business day (valid_until) has passed. Only `active` pays a bill.
 */
enum VoucherStatus: string
{
    case ACTIVE = 'active';
    case USED = 'used';
    case BLOCKED = 'blocked';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Faol',
            self::USED => 'Ishlatilgan',
            self::BLOCKED => 'Bloklangan',
            self::EXPIRED => "Muddati o'tgan",
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
