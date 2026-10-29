<?php

namespace App\Domain\Pos\Tips;

/**
 * P6-22: how a tip pool is split among the people taking part (TipPoolDistributor): `equal` — the same to each;
 * `hours` — by minutes worked in the period; `sales` — by the paid bills of their orders (orders.waiter_id) in the
 * period; `role_weights` — by the weight the owner gave their role.
 */
enum TipPoolRule: string
{
    case EQUAL = 'equal';
    case HOURS = 'hours';
    case SALES = 'sales';
    case ROLE_WEIGHTS = 'role_weights';

    public function label(): string
    {
        return match ($this) {
            self::EQUAL => 'Teng',
            self::HOURS => 'Ishlagan soatiga qarab',
            self::SALES => 'Savdosiga qarab',
            self::ROLE_WEIGHTS => "Rol ulushiga qarab",
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $rule) => $rule->value, self::cases());
    }
}
