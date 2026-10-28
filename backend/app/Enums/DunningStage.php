<?php

namespace App\Enums;

/**
 * P4-21: a step of the unpaid-invoice routine (subscription_dunning_events.stage): reminders 1, 3 and 7 days after the
 * due date, then the lock, and the unlock once paid.
 */
enum DunningStage: string
{
    case REMINDER_1 = 'reminder_1';
    case REMINDER_3 = 'reminder_3';
    case REMINDER_7 = 'reminder_7';
    case LOCKED = 'locked';
    case UNLOCKED = 'unlocked';

    public static function reminderFor(int $day): ?self
    {
        return self::tryFrom("reminder_{$day}");
    }

    public function label(): string
    {
        return match ($this) {
            self::REMINDER_1 => '1-kun eslatmasi',
            self::REMINDER_3 => '3-kun eslatmasi',
            self::REMINDER_7 => '7-kun eslatmasi',
            self::LOCKED => 'Cheklandi',
            self::UNLOCKED => 'Cheklov olindi',
        };
    }
}
