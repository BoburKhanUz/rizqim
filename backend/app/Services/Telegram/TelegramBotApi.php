<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The HTTP side of the Telegram Bot API. Queue jobs call it (SendTelegramRequest, SendOrderReport), and since M3 the
 * ops alerts (AlertNotifier, sent at once so a dead queue worker cannot hold back its own alert): everything else
 * queues its calls through TelegramMessenger.
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
     * @param  float|null  $timeout seconds; M3: the ops alerts sent at once by the scheduler wait less (AlertNotifier)
     *
     * @throws ConnectionException
     */
    public function call(string $method, array $params, ?array $attachment = null, ?float $timeout = null): Response
    {
        $request = Http::timeout($timeout ?? ($attachment ? 30 : 10));
        if ($timeout !== null) {
            $request = $request->connectTimeout(min($timeout, 3.0));
        }

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
