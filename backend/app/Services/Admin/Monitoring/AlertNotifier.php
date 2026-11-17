<?php

namespace App\Services\Admin\Monitoring;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * P4-15: tells the ops Telegram group (TELEGRAM_OPS_CHAT_ID, else the superadmin chat) when an alert opens and when it
 * is resolved. Plain text, numbers and names only.
 *
 * M3: sent AT ONCE by the calling process (the scheduler's evaluator), not through the queue: a dead queue worker is
 * one of the things the alerts are about, and its alert must not wait in the queue behind it. An unreachable Telegram
 * never holds the scheduler for long: each request waits at most monitoring.telegram.timeout seconds, one evaluation
 * spends at most run_budget_seconds on Telegram, and after a connection failure or a Telegram outage (5xx/429)
 * Telegram is skipped for breaker_seconds. A message not sent at once is handed to the queue (TelegramMessenger), so
 * it still arrives once a worker runs. A refusal (unknown chat, blocked bot) is final either way.
 *
 * Rate limits keep a flapping problem from flooding the group: an alert of the same rule and subject is announced at
 * most once per `cooldown` minutes, and at most `hourly_limit` announcements go out per hour in all (one notice says
 * the rest were held back). A resolve message is sent only for an alert whose open message went out, so the group
 * never reads a "resolved" about something it was not told.
 */
class AlertNotifier
{
    private const LIMIT_KEY = 'monitoring:telegram:hour';

    private const BREAKER_KEY = 'monitoring:telegram:down';

    private ?Carbon $deadline = null;

    public function __construct(
        private readonly TelegramMessenger $telegram,
        private readonly TelegramBotApi $api,
    ) {}

    /**
     * A new evaluation: its Telegram time budget starts now. Without a call (a member's action in a request) the
     * budget starts with the first message.
     */
    public function startRun(): void
    {
        $this->deadline = now()->addMilliseconds((int) round(1000 * max(1, (float) config('monitoring.telegram.run_budget_seconds', 10))));
    }

    public function chatId(): ?string
    {
        foreach ([config('services.telegram.ops_chat_id'), config('services.telegram.superadmin_chat_id')] as $chatId) {
            $chatId = trim((string) $chatId);
            if ($chatId !== '') {
                return $chatId;
            }
        }

        return null;
    }

    public function opened(Alert $alert, AlertRule $rule): bool
    {
        $chatId = $this->chatId();
        if (!$rule->telegram || $chatId === null || !$this->allow($alert, $chatId)) {
            return false;
        }

        $this->send($chatId, $this->openText($alert));
        $alert->forceFill(['telegram_opened_at' => now()])->save();

        return true;
    }

    /**
     * An open alert that became more severe (a warning grew critical): announced again, under its own cooldown.
     */
    public function escalated(Alert $alert, AlertRule $rule): bool
    {
        $chatId = $this->chatId();
        if (!$rule->telegram || $chatId === null || !$this->allow($alert, $chatId, ':' . $alert->severity)) {
            return false;
        }

        $this->send($chatId, $this->openText($alert));
        if ($alert->telegram_opened_at === null) {
            Alert::query()->whereKey($alert->getKey())->update(['telegram_opened_at' => now()]);
            $alert->forceFill(['telegram_opened_at' => now()])->syncOriginal();
        }

        return true;
    }

    private function openText(Alert $alert): string
    {
        $kind = AlertKind::from($alert->kind);

        return implode("\n", array_filter([
            '[' . $this->severityLabel($alert->severity) . '] ' . $alert->title,
            $kind->label(),
            'Nima qilish kerak: ' . $kind->action(),
            'Boshlangan: ' . $alert->first_seen_at->setTimezone('Asia/Tashkent')->format('d.m.Y H:i'),
            $this->link(),
        ]));
    }

    /**
     * At once when Telegram is reachable and the run's budget allows, else through the queue.
     */
    private function send(string $chatId, string $text): void
    {
        $this->deadline ??= now()->addMilliseconds((int) round(1000 * max(1, (float) config('monitoring.telegram.run_budget_seconds', 10))));
        $left = (float) now()->diffInMilliseconds($this->deadline, false) / 1000;
        $timeout = min(max(1, (float) config('monitoring.telegram.timeout', 5)), $left);

        if (!$this->api->isConfigured() || $timeout < 0.5 || Cache::has(self::BREAKER_KEY)) {
            $this->telegram->sendMessage($chatId, $text);

            return;
        }

        try {
            $response = $this->api->call('sendMessage', ['chat_id' => $chatId, 'text' => $text], null, $timeout);
        } catch (ConnectionException $e) {
            $this->trip($this->api->redact($e->getMessage()));
            $this->telegram->sendMessage($chatId, $text);

            return;
        } catch (Throwable $e) {
            Log::warning('Monitoring: Telegram alert could not be sent at once', ['exception' => $e::class]);
            $this->telegram->sendMessage($chatId, $text);

            return;
        }

        if ($response->successful()) {
            return;
        }
        if ($this->api->isRetryable($response)) {
            $this->trip('HTTP ' . $response->status());
            $this->telegram->sendMessage($chatId, $text);

            return;
        }

        // Unknown chat, blocked bot, bad text: the queue would not retry it either
        Log::warning('Monitoring: Telegram refused an alert message', ['chat_id' => $chatId, 'status' => $response->status(), 'error' => mb_substr($response->body(), 0, 300)]);
    }

    private function trip(string $error): void
    {
        Cache::put(self::BREAKER_KEY, true, max(10, (int) config('monitoring.telegram.breaker_seconds', 120)));
        Log::warning('Monitoring: Telegram is not reachable, alert messages go through the queue for a while', ['error' => mb_substr($error, 0, 300)]);
    }

    public function resolved(Alert $alert, ?string $by = null): bool
    {
        $chatId = $this->chatId();
        if ($chatId === null || $alert->telegram_opened_at === null || $alert->telegram_resolved_at !== null) {
            return false;
        }

        // Claim the announcement in the database first: whoever resolves the alert, it is announced once
        $claimed = Alert::query()->whereKey($alert->getKey())->whereNull('telegram_resolved_at')->update(['telegram_resolved_at' => now()]);
        if ($claimed !== 1) {
            return false;
        }

        $minutes = max(0, (int) floor($alert->first_seen_at->diffInMinutes($alert->resolved_at ?? now(), true)));
        $this->send($chatId, implode("\n", array_filter([
            '[HAL QILINDI] ' . $alert->title,
            "Davomiyligi: {$minutes} daqiqa",
            $by !== null ? "Hal qildi: {$by}" : 'Muammo o\'z-o\'zidan tugadi',
            $this->link(),
        ])));
        $alert->forceFill(['telegram_resolved_at' => now()])->syncOriginal();

        return true;
    }

    private function allow(Alert $alert, string $chatId, string $suffix = ''): bool
    {
        $limit = (int) config('monitoring.telegram.hourly_limit', 20);
        if (RateLimiter::tooManyAttempts(self::LIMIT_KEY, $limit)) {
            // One notice per hour that more are being held back
            if (Cache::add(self::LIMIT_KEY . ':notice', true, 3600)) {
                $this->send($chatId, "Ogohlantirishlar ko'p: bir soatda {$limit} tadan ortig'i Telegram'ga yuborilmaydi. Hammasi markazda: " . $this->link());
            }

            return false;
        }

        $cooldown = max(1, (int) config('monitoring.telegram.cooldown_minutes', 15));
        if (!Cache::add("monitoring:telegram:{$alert->alert_rule_id}:" . md5($alert->subject_key) . $suffix, true, $cooldown * 60)) {
            return false;
        }

        RateLimiter::hit(self::LIMIT_KEY, 3600);

        return true;
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'KRITIK',
            'warning' => 'DIQQAT',
            default => "MA'LUMOT",
        };
    }

    private function link(): string
    {
        return config('admin.app_url') . '/monitoring?tab=alerts';
    }
}
