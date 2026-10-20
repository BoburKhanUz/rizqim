<?php

namespace App\Services\Notifications;

/**
 * A Telegram message for NotificationRouter: the chat is the recipient's own telegram_id.
 */
final class TelegramContent
{
    /**
     * @param array<string, mixed> $options further sendMessage fields (parse_mode, reply_markup)
     * @param array{user_id: int, order_id: int, type: string}|null $log NotificationLog row written with the outcome
     */
    public function __construct(
        public readonly string $text,
        public readonly array $options = [],
        public readonly ?array $log = null,
    ) {}
}
