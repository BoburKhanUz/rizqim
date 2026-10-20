<?php

namespace App\Enums;

/**
 * Ways a message can reach a person (App\Services\Notifications\NotificationRouter).
 */
enum NotificationChannel: string
{
    case TELEGRAM = 'telegram';
    case PUSH = 'push';
    // Only a critical message with no other way through (the router's last resort); not a user setting
    case SMS = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::TELEGRAM => 'Telegram',
            self::PUSH => 'Push xabar',
            self::SMS => 'SMS',
        };
    }
}
