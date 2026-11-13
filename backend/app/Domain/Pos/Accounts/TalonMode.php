<?php

namespace App\Domain\Pos\Accounts;

/**
 * customer_accounts.talon_mode (P6-44), how a corporate account's people eat:
 *
 *  - none: the company is charged as a whole; naming a member is optional (recorded when given);
 *  - daily_limit: every charge names a member (the talon: their code, or their phone), at most their `daily_limit`
 *    sum per business day;
 *  - subscription (abonement): every charge names a member, at most their `meals_per_day` charges per business day; the
 *    monthly statement counts the meals. A member's `daily_limit`, when set, is checked in this mode too.
 *
 * "Talon" is the member's code (printed as a QR chit or on a badge), not a paper ticket that is used up: the limits
 * are counted on the server, so a lost or copied chit can only be used within its owner's limits.
 */
enum TalonMode: string
{
    case NONE = 'none';
    case DAILY_LIMIT = 'daily_limit';
    case SUBSCRIPTION = 'subscription';

    public function label(): string
    {
        return match ($this) {
            self::NONE => "Talonsiz (korxona bo'yicha)",
            self::DAILY_LIMIT => 'Kunlik limit',
            self::SUBSCRIPTION => 'Abonement (kuniga ovqat soni)',
        };
    }

    public function needsMember(): bool
    {
        return $this !== self::NONE;
    }
}
