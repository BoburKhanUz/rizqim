<?php

namespace App\Enums;

/**
 * A store subscription's state (docs/BILLING.md). Only `cancelled` rows are history; every other status is the
 * store's current subscription, whose plan sets its commission and monthly fee.
 */
enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TRIALING => 'Sinov davri',
            self::ACTIVE => 'Faol',
            self::PAST_DUE => "To'lov muddati o'tgan",
            self::CANCELLED => 'Tugagan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TRIALING => 'info',
            self::ACTIVE => 'success',
            self::PAST_DUE => 'danger',
            self::CANCELLED => 'gray',
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
