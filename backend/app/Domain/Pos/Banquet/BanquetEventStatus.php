<?php

namespace App\Domain\Pos\Banquet;

/**
 * A banquet event's life (P6-51): hold (the hall is held without a deposit until `hold_expires_at`; the minute
 * command `pos:banquet-release-holds` cancels an expired hold and releases the hall) → confirmed (the deposit row of
 * the payment schedule is paid, P6-53) → in_progress (the event-day tab is open, P6-54) → settled (the final sale
 * receipt, P6-54). hold or confirmed → cancelled (P6-53 computes the refund/retained parts).
 */
enum BanquetEventStatus: string
{
    case HOLD = 'hold';
    case CONFIRMED = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::HOLD => 'Vaqtincha band',
            self::CONFIRMED => 'Tasdiqlangan',
            self::IN_PROGRESS => "O'tkazilmoqda",
            self::SETTLED => 'Yakunlangan',
            self::CANCELLED => 'Bekor qilingan',
        };
    }

    /**
     * Still holds its halls and time (the double-booking check counts it).
     */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::HOLD, self::CONFIRMED, self::IN_PROGRESS], true);
    }

    /**
     * Details, halls, guests, package and extras may still change.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::HOLD, self::CONFIRMED], true);
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::HOLD => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::SETTLED],
            self::SETTLED, self::CANCELLED => [],
        }, true);
    }

    /**
     * @return list<self>
     */
    public static function holding(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->holdsSlot()));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
