<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The HTTP side of the Telegram Bot API. Only queue jobs call it (SendTelegramRequest, SendOrderReport):
 * everything else queues its calls through TelegramMessenger.
 */
class TelegramBotApi
{
    public function token(): ?string
    {
        $token = config('services.telegram.bot_token');

        return filled($token) ? (string) $token : null;
    }

    public function isConfigured(): bool
    {
        return $this->token() !== null;
    }

    /**
     * @param array{name: string, contents: string|resource, filename: string}|null $attachment multipart file (sendDocument),
     *     its contents a string or a stream read as it is sent
     *
     * @throws ConnectionException
     */
    public function call(string $method, array $params, ?array $attachment = null): Response
    {
        $request = Http::timeout($attachment ? 30 : 10);

        if ($attachment) {
            $request = $request->attach($attachment['name'], $attachment['contents'], $attachment['filename']);
        }

        return $request->post("https://api.telegram.org/bot{$this->token()}/{$method}", $params);
    }

    /**
     * Rate limits and Telegram/network outages pass; any other 4xx (blocked bot, unknown chat, bad markup) won't.
     */
    public function isRetryable(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    /**
     * Seconds Telegram asked us to wait before calling again (429 responses).
     */
    public function retryAfter(Response $response): ?int
    {
        $seconds = $response->json('parameters.retry_after');

        return is_numeric($seconds) ? max(1, (int) $seconds) : null;
    }

    /**
     * Error text safe to log: transport errors quote the request URL, which carries the bot token.
     */
    public function redact(string $message): string
    {
        $token = $this->token();

        return $token ? str_replace($token, '***', $message) : $message;
    }
}
