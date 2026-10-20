<?php

namespace App\Services\Notifications;

use App\Services\Telegram\TelegramMessenger;

/**
 * Platform alerts for superadmins, queued to the Telegram chat in config('services.telegram.superadmin_chat_id').
 * Without that chat (or a bot token) nothing is sent; callers log the event themselves.
 */
class SuperadminAlertService
{
    public function __construct(
        protected TelegramMessenger $telegram
    ) {}

    public function chatId(): ?string
    {
        $chatId = trim((string) config('services.telegram.superadmin_chat_id'));

        return $chatId !== '' ? $chatId : null;
    }

    /**
     * Plain text (no parse mode): alerts quote error messages, whose stray Markdown characters would get them refused.
     * Queued after the current transaction commits, so an alert about a rolled-back action is never sent.
     */
    public function send(string $text): void
    {
        $chatId = $this->chatId();

        if ($chatId !== null) {
            $this->telegram->sendMessage($chatId, $text);
        }
    }
}
