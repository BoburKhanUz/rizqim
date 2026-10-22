<?php

namespace App\Domain\Pos\Booking;

/**
 * A booking's life (research report §4): requested → confirmed → arrived (the order is open) → completed (the order
 * is closed); requested or confirmed → canceled; confirmed → no_show; requested → arrived for a guest who came before
 * the request was confirmed. A taken deposit does not change the status: it
 * is the booking's `deposit_total` (the report's "avans olindi" is confirmed + a deposit).
 */
enum BookingStatus: string
{
    case REQUESTED = 'requested';
    case CONFIRMED = 'confirmed';
    case ARRIVED = 'arrived';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
    case NO_SHOW = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => "So'rov",
            self::CONFIRMED => 'Tasdiqlangan',
            self::ARRIVED => 'Keldi',
            self::COMPLETED => 'Yopildi',
            self::CANCELED => 'Bekor qilingan',
            self::NO_SHOW => 'Kelmadi',
        };
    }

    /**
     * Still holds its room and time (the conflict check counts it).
     */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::REQUESTED, self::CONFIRMED, self::ARRIVED], true);
    }

    /**
     * Details, tables and pre-ordered dishes may still change.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::REQUESTED, self::CONFIRMED], true);
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, match ($this) {
            // A walk-in on an unconfirmed request still arrives; only a confirmed booking can be a no-show
            self::REQUESTED => [self::CONFIRMED, self::CANCELED, self::ARRIVED],
            self::CONFIRMED => [self::ARRIVED, self::CANCELED, self::NO_SHOW],
            self::ARRIVED => [self::COMPLETED],
            self::COMPLETED, self::CANCELED, self::NO_SHOW => [],
        }, true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
