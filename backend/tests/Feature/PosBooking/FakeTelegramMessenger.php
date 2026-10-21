<?php

namespace Tests\Feature\PosBooking;

use App\Services\Telegram\TelegramMessenger;

/**
 * Records the owner alerts instead of queuing Telegram requests.
 */
class FakeTelegramMessenger extends TelegramMessenger
{
    /** @var list<array{chat: string, text: string}> */
    public array $sent = [];

    public function __construct() {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function sendMessage(int|string $chatId, string $text, array $options = [], ?array $notification = null): void
    {
        $this->sent[] = ['chat' => (string) $chatId, 'text' => $text];
    }
}
