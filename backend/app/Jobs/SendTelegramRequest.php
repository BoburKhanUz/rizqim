<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Telegram\TelegramBotApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One Telegram Bot API call (sendMessage, editMessageText, answerCallbackQuery…), made by a queue worker once the
 * dispatching transaction has committed. Rate limits and Telegram/network outages are retried with backoff; a call
 * Telegram refuses (blocked bot, unknown chat, bad markup) is not. With $notification (NotificationLog user_id,
 * order_id, type) the final outcome is recorded once, as the in-app notification feed shows it.
 */
class SendTelegramRequest implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public string $method,
        public array $params,
        public ?array $notification = null,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function handle(TelegramBotApi $telegram): void
    {
        if (!$telegram->isConfigured()) {
            Log::warning("Telegram {$this->method} dropped: the bot token is no longer configured");
            return;
        }

        try {
            $response = $this->send($telegram);
        } catch (ConnectionException $e) {
            $this->retryOrGiveUp($telegram->redact($e->getMessage()));
            return;
        }

        if ($response->successful()) {
            $this->record('sent');
            return;
        }

        if ($telegram->isRetryable($response)) {
            $this->retryOrGiveUp($response->body(), $telegram->retryAfter($response));
            return;
        }

        $this->giveUp($response->body());
    }

    /**
     * The worker gave up after an unexpected exception (or a timeout): the call's outcome is unknown.
     */
    public function failed(?Throwable $e): void
    {
        $this->giveUp(app(TelegramBotApi::class)->redact((string) $e?->getMessage()));
    }

    protected function send(TelegramBotApi $telegram): Response
    {
        return $telegram->call($this->method, $this->params);
    }

    protected function retryOrGiveUp(string $error, ?int $retryAfter = null): void
    {
        // The sync driver (tests, local) cannot retry later, so it gives up at once like a last attempt
        if ($this->job && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
            $this->release($retryAfter ?? $this->backoff()[$this->attempts() - 1] ?? 300);
            return;
        }

        $this->giveUp($error);
    }

    protected function giveUp(string $error): void
    {
        Log::warning("Telegram {$this->method} failed", [
            'chat_id' => $this->params['chat_id'] ?? null,
            'attempts' => $this->attempts(),
            'error' => $error,
        ]);

        $this->record('failed', $error);
    }

    protected function record(string $status, ?string $error = null): void
    {
        if (!$this->notification) {
            return;
        }

        try {
            NotificationLog::create($this->notification + [
                'status' => $status,
                'sent_at' => now(),
                'error_message' => $error,
            ]);
        } catch (Throwable $e) {
            // e.g. the recipient was deleted meanwhile; never fail (and so resend) a call that already went out
            Log::error("Failed to record Telegram notification: {$e->getMessage()}", $this->notification);
        }
    }
}
