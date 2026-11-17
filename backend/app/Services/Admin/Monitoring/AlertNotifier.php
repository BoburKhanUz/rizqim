<?php

namespace App\Services\Admin\Monitoring;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P4-15: tells the ops Telegram group (TELEGRAM_OPS_CHAT_ID, else the superadmin chat) when an alert opens and when it
 * is resolved. Plain text, numbers and names only.
 *
 * Delivery rules (M3, review round 3):
 * - An unresolved alert whose announcement has not gone out (`telegram_opened_at` NULL) is offered again on EVERY
 *   evaluation (announcePending) until it is sent or resolved: a limit, a cooldown, a cache outage or an unreachable
 *   Telegram only delays it. Criticals are offered first, then the oldest.
 * - Warnings: at most `hourly_limit` (20) announcements per rolling hour in all. Criticals never count against that
 *   limit; they have their own cap of `critical_hourly_limit_per_rule` (10) per rule and hour, only to stop a runaway
 *   loop. What a limit holds back is not silenced: ONE digest per hour ("Yana N ta ogohlantirish ochiq: ...") and, as
 *   the hour frees room, the held alerts that are still open go out one by one.
 * - Cooldown (`cooldown_minutes`, 15): an alert is held while another alert of the same rule and subject was announced
 *   within the cooldown and its resolve notice did NOT go out. A problem that comes back after the group read
 *   "[HAL QILINDI]" is announced again at once (the last word must not be "resolved").
 * - A resolve notice is sent for every announced alert, never limited. An alert that grows critical (a warning that
 *   became critical) is announced again (the evaluator clears its `telegram_opened_at`).
 * - Limits and cooldowns are counted from the alerts table, not the cache: a cache/Redis failure cannot lose a
 *   message. The cache is used only for the Telegram breaker below and fails open (logged once per process).
 * - Sent AT ONCE by the calling process (the scheduler's evaluator), not through the queue: a dead queue worker is one
 *   of the things the alerts are about. Each request waits at most `timeout` (5 s); the run's Telegram budget
 *   (`run_budget_seconds`, 10 s) starts at the first message, not before the checks; after a connection failure or
 *   a Telegram outage (5xx/429) Telegram is skipped for `breaker_seconds`. A message not sent at once is handed to the
 *   queue (TelegramMessenger) and counts as announced. A refusal (unknown chat, blocked bot) is final.
 * So at most 20 warning messages + 10 per rule of criticals + resolve notices + 1 digest per hour; never suppressed:
 * the first announcement of a critical (within its cap), resolve notices, a reopen after a resolve notice.
 */
class AlertNotifier
{
    private const BREAKER_KEY = 'monitoring:telegram:down';

    private const NO_CHAT_KEY = 'monitoring:telegram:no-chat-logged';

    /** monitoring_markers key: when the last digest went out (in the database, like the limits). */
    public const DIGEST_MARKER = 'telegram_digest';

    private ?Carbon $deadline = null;

    private static bool $cacheFailureLogged = false;

    public function __construct(
        private readonly TelegramMessenger $telegram,
        private readonly TelegramBotApi $api,
    ) {}

    /**
     * A new evaluation: the Telegram time budget starts again with the run's first message (slow checks before it
     * never use it up).
     */
    public function startRun(): void
    {
        $this->deadline = null;
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

    /** Whether an alert announcement reaches anyone: a bot token and a chat. */
    public function configured(): bool
    {
        return $this->api->isConfigured() && $this->chatId() !== null;
    }

    /**
     * Offers every unresolved alert whose announcement has not gone out, under the rules above. Returns how many
     * were announced.
     */
    public function announcePending(): int
    {
        $rules = AlertRule::query()->where('enabled', true)->where('telegram', true)->pluck('id');
        $pending = Alert::query()->unresolved()->whereNull('telegram_opened_at')->whereIn('alert_rule_id', $rules)
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderBy('first_seen_at')->orderBy('id')
            ->get();
        if ($pending->isEmpty()) {
            return 0;
        }

        $chatId = $this->chatId();
        if ($chatId === null) {
            $this->noChat($pending->count());

            return 0;
        }

        $hourAgo = now()->subHour();
        $warningsSent = Alert::query()->where('telegram_opened_at', '>=', $hourAgo)->where('severity', '<>', 'critical')->count();
        $criticalsSent = Alert::query()->where('telegram_opened_at', '>=', $hourAgo)->where('severity', 'critical')
            ->selectRaw('alert_rule_id, count(*) AS n')->groupBy('alert_rule_id')->pluck('n', 'alert_rule_id')->map(fn ($n) => (int) $n)->all();
        $warningLimit = max(1, (int) config('monitoring.telegram.hourly_limit', 20));
        $criticalLimit = max(1, (int) config('monitoring.telegram.critical_hourly_limit_per_rule', 10));

        $sent = 0;
        $held = [];
        foreach ($pending as $alert) {
            if ($this->coolingDown($alert)) {
                continue;
            }
            if ($alert->severity === 'critical') {
                if (($criticalsSent[$alert->alert_rule_id] ?? 0) >= $criticalLimit) {
                    $held[] = $alert;

                    continue;
                }
                $criticalsSent[$alert->alert_rule_id] = ($criticalsSent[$alert->alert_rule_id] ?? 0) + 1;
            } else {
                if ($warningsSent >= $warningLimit) {
                    $held[] = $alert;

                    continue;
                }
                $warningsSent++;
            }

            $this->announce($alert, $chatId);
            $sent++;
        }

        if ($held !== []) {
            $this->digest($held, $chatId);
        }

        return $sent;
    }

    /**
     * Sends one alert's announcement now (no limits: the evaluator goes through announcePending).
     */
    public function opened(Alert $alert, AlertRule $rule): bool
    {
        $chatId = $this->chatId();
        if (!$rule->telegram || $chatId === null) {
            return false;
        }
        $this->announce($alert, $chatId);

        return true;
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

    // ------------------------------------------------------------------------------------------------------------

    private function announce(Alert $alert, string $chatId): void
    {
        $this->send($chatId, $this->openText($alert));
        Alert::query()->whereKey($alert->getKey())->update(['telegram_opened_at' => now()]);
        $alert->forceFill(['telegram_opened_at' => now()])->syncOriginal();
    }

    /**
     * Held while another alert of the same rule and subject was announced within the cooldown and the group was not
     * told it ended (its resolve notice did not go out).
     */
    private function coolingDown(Alert $alert): bool
    {
        $cooldown = max(1, (int) config('monitoring.telegram.cooldown_minutes', 15));

        return Alert::query()
            ->where('alert_rule_id', $alert->alert_rule_id)
            ->where('subject_key', $alert->subject_key)
            ->whereKeyNot($alert->getKey())
            ->where('telegram_opened_at', '>=', now()->subMinutes($cooldown))
            ->whereNull('telegram_resolved_at')
            ->exists();
    }

    /**
     * @param  list<Alert>  $held
     */
    private function digest(array $held, string $chatId): void
    {
        $last = MonitoringMarker::query()->find(self::DIGEST_MARKER);
        if ($last !== null && $last->recorded_at->gt(now()->subHour())) {
            return;
        }
        MonitoringMarker::query()->updateOrCreate(['key' => self::DIGEST_MARKER], ['recorded_at' => now(), 'meta' => ['held' => count($held)]]);

        $titles = array_map(fn (Alert $alert) => mb_substr($alert->title, 0, 80), array_slice($held, 0, 5));
        $more = count($held) > 5 ? ' va yana ' . (count($held) - 5) . ' ta' : '';
        $this->send($chatId, 'Yana ' . count($held) . " ta ogohlantirish ochiq (soatlik chegara to'ldi): " . implode('; ', $titles) . $more
            . ". Boshqaruv markazida ko'ring: " . $this->link() . "\nChegara bo'shagach, hali ochiq turganlari alohida yuboriladi.");
    }

    private function noChat(int $pending): void
    {
        if (!$this->cacheAdd(self::NO_CHAT_KEY, 3600)) {
            return;
        }
        Log::error("Monitoring: {$pending} alert(s) cannot be announced: TELEGRAM_OPS_CHAT_ID and TELEGRAM_SUPERADMIN_CHAT_ID are empty");
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

        if (!$this->api->isConfigured() || $timeout < 0.5 || $this->breakerOpen()) {
            $this->queue($chatId, $text);

            return;
        }

        try {
            $response = $this->api->call('sendMessage', ['chat_id' => $chatId, 'text' => $text], null, $timeout);
        } catch (ConnectionException $e) {
            $this->trip($this->api->redact($e->getMessage()));
            $this->queue($chatId, $text);

            return;
        } catch (Throwable $e) {
            Log::warning('Monitoring: Telegram alert could not be sent at once', ['exception' => $e::class]);
            $this->queue($chatId, $text);

            return;
        }

        if ($response->successful()) {
            return;
        }
        if ($this->api->isRetryable($response)) {
            $this->trip('HTTP ' . $response->status());
            $this->queue($chatId, $text);

            return;
        }

        // Unknown chat, blocked bot, bad text: the queue would not retry it either
        Log::warning('Monitoring: Telegram refused an alert message', ['chat_id' => $chatId, 'status' => $response->status(), 'error' => mb_substr($response->body(), 0, 300)]);
    }

    private function queue(string $chatId, string $text): void
    {
        try {
            $this->telegram->sendMessage($chatId, $text);
        } catch (Throwable $e) {
            Log::error('Monitoring: alert message could not be queued either', ['exception' => $e::class]);
        }
    }

    private function breakerOpen(): bool
    {
        try {
            return Cache::has(self::BREAKER_KEY);
        } catch (Throwable $e) {
            $this->cacheFailed($e);

            return false;
        }
    }

    private function trip(string $error): void
    {
        try {
            Cache::put(self::BREAKER_KEY, true, max(10, (int) config('monitoring.telegram.breaker_seconds', 120)));
        } catch (Throwable $e) {
            $this->cacheFailed($e);
        }
        Log::warning('Monitoring: Telegram is not reachable, alert messages go through the queue for a while', ['error' => mb_substr($error, 0, 300)]);
    }

    /** Cache::add that fails open: true (go ahead) when the cache cannot be reached. */
    private function cacheAdd(string $key, int $seconds): bool
    {
        try {
            return Cache::add($key, true, $seconds);
        } catch (Throwable $e) {
            $this->cacheFailed($e);

            return true;
        }
    }

    private function cacheFailed(Throwable $e): void
    {
        if (self::$cacheFailureLogged) {
            return;
        }
        self::$cacheFailureLogged = true;
        Log::warning('Monitoring: the cache is not reachable; alert delivery goes on without it', ['exception' => $e::class]);
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
