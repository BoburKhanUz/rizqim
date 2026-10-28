<?php

namespace App\Enums;

/**
 * A store subscription's state (docs/BILLING.md). Only `cancelled` rows are history; every other status is the
 * store's current subscription, whose plan sets its commission and monthly fee.
 *
 * P4-21: `locked` — an invoice is unpaid more than config('billing.dunning.lock_after_days') days after its due date:
 * settings and features outside the plan close (App\Services\Finance\Billing\Entitlements); the till keeps taking
 * payments and printing receipts. Paying unlocks at once.
 */
enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case LOCKED = 'locked';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TRIALING => 'Sinov davri',
            self::ACTIVE => 'Faol',
            self::PAST_DUE => "To'lov muddati o'tgan",
            self::LOCKED => 'Cheklangan (qarz)',
            self::CANCELLED => 'Tugagan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TRIALING => 'info',
            self::ACTIVE => 'success',
            self::PAST_DUE => 'danger',
            self::LOCKED => 'danger',
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
