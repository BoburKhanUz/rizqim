<?php

namespace App\Services\Telegram;

use App\Jobs\SendTelegramRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outgoing Telegram Bot API calls. Each is queued as a SendTelegramRequest that a worker sends after the current
 * DB transaction commits, so no request or transaction waits on Telegram. Without a bot token nothing is queued.
 */
class TelegramMessenger
{
    public function __construct(
        protected TelegramBotApi $api
    ) {}

    public function isConfigured(): bool
    {
        return $this->api->isConfigured();
    }

    /**
     * @param array $options further sendMessage fields; parse_mode null is left out, as is an empty reply_markup
     * @param array|null $notification NotificationLog fields (user_id, order_id, type) recorded with the outcome
     */
    public function sendMessage(int|string $chatId, string $text, array $options = [], ?array $notification = null): void
    {
        $this->queue(new SendTelegramRequest('sendMessage', $this->message($chatId, $text, $options), $notification));
    }

    /**
     * Messages to one chat that must arrive in this order: a worker sends each only after the previous one.
     *
     * @param list<array{0: string, 1?: array}> $messages text and sendMessage options of each message
     */
    public function sendMessagesInOrder(int|string $chatId, array $messages): void
    {
        $this->queue(...array_map(
            fn (array $message) => new SendTelegramRequest('sendMessage', $this->message($chatId, $message[0], $message[1] ?? [])),
            $messages
        ));
    }

    public function editMessageText(int|string $chatId, int|string $messageId, string $text, array $options = []): void
    {
        $this->queue(new SendTelegramRequest(
            'editMessageText',
            ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text] + $this->encode($options)
        ));
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void
    {
        $this->queue(new SendTelegramRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]));
    }

    protected function message(int|string $chatId, string $text, array $options): array
    {
        return ['chat_id' => $chatId, 'text' => $text] + $this->encode($options);
    }

    protected function encode(array $options): array
    {
        $options = array_filter($options, fn ($value) => $value !== null);

        if (array_key_exists('reply_markup', $options)) {
            if (empty($options['reply_markup'])) {
                unset($options['reply_markup']);
            } elseif (is_array($options['reply_markup'])) {
                $options['reply_markup'] = json_encode($options['reply_markup']);
            }
        }

        return $options;
    }

    protected function queue(SendTelegramRequest ...$jobs): void
    {
        if (!$jobs || !$this->isConfigured()) {
            return;
        }

        try {
            if (count($jobs) > 1) {
                Bus::chain($jobs)->dispatch();
            } else {
                Bus::dispatch($jobs[0]);
            }
        } catch (Throwable $e) {
            // A notification must never fail (or roll back) the action it reports
            Log::error("Failed to queue Telegram {$jobs[0]->method}: {$e->getMessage()}");
        }
    }
}
