<?php

namespace App\Enums;

/**
 * P4-31: where a complaint is on the RIZQIM support side (complaints.support_status, Mijozlar va yordam →
 * Shikoyatlar): new → in_progress (someone took it) → answered (support wrote to the customer) → resolved (closed with
 * a note). The customer ↔ store flow (complaints.status: pending, owner_replied, in_review, closed) runs beside it.
 */
enum ComplaintSupportStatus: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case ANSWERED = 'answered';
    case RESOLVED = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Yangi',
            self::IN_PROGRESS => "Ko'rib chiqilmoqda",
            self::ANSWERED => 'Javob berildi',
            self::RESOLVED => 'Hal qilindi',
        };
    }

    /**
     * Still waiting for the support team (the attention source and the `waiting` filter look at these).
     */
    public function isOpen(): bool
    {
        return $this === self::NEW || $this === self::IN_PROGRESS;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::NEW->value, self::IN_PROGRESS->value];
    }
}
