<?php

namespace App\Services\Admin\Monitoring;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Telegram\TelegramBotApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P4-15: tells the ops Telegram group (TELEGRAM_OPS_CHAT_ID, else the superadmin chat) when an alert opens and when it
 * is resolved. Plain text, numbers and names only.
 *
 * Delivery rules (M3, review rounds 3 and 4):
 * - An unresolved alert whose announcement has not gone out (`telegram_opened_at` NULL, or raised to critical: an
 *   `ops:escalated:<id>` marker) is offered again on EVERY evaluation (announcePending) until it is sent or resolved: a
 *   limit, a cooldown, a cache outage or an unreachable Telegram only delays it. Criticals first.
 * - Grouping: when one rule has more than GROUP_SIZE (3) alerts to announce in a run, they go out as ONE message
 *   ("<rule>: 100 ta: A; B; C va yana 97 ta"); the same for resolve notices ("<rule>: 30 tasi tiklandi"). Between
 *   rules the turns are round-robin, so one noisy rule (offline tills after an internet outage) never starves another.
 * - Warnings: at most `hourly_limit` (20) messages per rolling hour (a grouped message counts once). Criticals never
 *   count against it; they have their own cap of `critical_hourly_limit_per_rule` (10) messages per rule and hour, only
 *   to stop a runaway loop. What a limit holds back gets ONE digest per hour ("Yana N ta ogohlantirish ochiq: ...")
 *   and goes out as the hour frees room.
 * - Cooldown (`cooldown_minutes`, 15): an alert is held while another alert of the same rule and subject was announced
 *   within the cooldown and its resolve notice did NOT go out. A reopen after "[HAL QILINDI]" is announced at once.
 * - A resolve notice goes out for every announced alert (also one raised to critical but held by the cap), never
 *   limited, grouped per rule.
 * - Limits, cooldown, digest time and queued messages are kept in the database (alerts, monitoring_markers), not the
 *   cache; the cache only holds the Telegram breaker and fails open (logged once per process).
 * - Sent AT ONCE by the calling process (the scheduler), not through the queue: each request waits at most `timeout`
 *   (5 s); the run's Telegram budget (`run_budget_seconds`, 10 s) starts at its first message; after a connection
 *   failure or a 5xx/429 Telegram is skipped for `breaker_seconds`. Otherwise the message is pushed to the queue
 *   (QueuedOpsMessage) and counts as announced only if the push succeeded; if the worker has not sent it within
 *   QUEUED_RESEND_MINUTES (10) it is sent directly. A refusal (unknown chat, blocked bot) is final.
 * At most per hour: 20 warning messages + 10 critical messages per rule + one resolve message per rule and run (one per
 * alert when 3 or fewer resolve together) + 1 digest + direct resends of queued messages. Never suppressed: the first
 * announcement of a critical (within its cap), resolve notices, a reopen after a resolve notice.
 */
class AlertNotifier
{
    private const BREAKER_KEY = 'monitoring:telegram:down';

    private const NO_CHAT_KEY = 'monitoring:telegram:no-chat-logged';

    /** monitoring_markers key: when the last digest went out (in the database, like the limits). */
    public const DIGEST_MARKER = 'telegram_digest';

    /** monitoring_markers key prefixes: an alert raised to critical to announce again; a message waiting in the queue. */
    public const ESCALATED_PREFIX = 'ops:escalated:';

    public const QUEUED_PREFIX = 'ops:queued:';

    /** Default of monitoring.telegram.group_size: more alerts than this of one rule in a run are one message. */
    public const GROUP_SIZE = 3;

    public const QUEUED_RESEND_MINUTES = 10;

    /** @var array<int, list<array{alert: Alert, by: string|null}>> resolve notices of this run, per rule */
    private array $resolvedBuffer = [];

    private ?Carbon $deadline = null;

    private static bool $cacheFailureLogged = false;

    public function __construct(
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
     * alerts were announced.
     */
    public function announcePending(): int
    {
        $this->resendStaleQueued();

        $escalated = $this->markedIds(self::ESCALATED_PREFIX);
        $rules = AlertRule::query()->where('enabled', true)->where('telegram', true)->pluck('id');
        $pending = Alert::query()->unresolved()->whereIn('alert_rule_id', $rules)
            ->where(fn ($q) => $q->whereNull('telegram_opened_at')->when($escalated !== [], fn ($q) => $q->orWhereIn('id', $escalated)))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderBy('first_seen_at')->orderBy('id')
            ->get()
            ->reject(fn (Alert $alert) => $this->coolingDown($alert));
        if ($pending->isEmpty()) {
            return 0;
        }

        $chatId = $this->chatId();
        if ($chatId === null) {
            $this->noChat($pending->count());

            return 0;
        }

        // Messages of the last hour (a grouped message counts once): timestamps kept in monitoring_markers
        $log = $this->sentLog();
        $warningsSent = count($log['w']);
        $criticalsSent = array_map('count', $log['c']);
        $warningLimit = max(1, (int) config('monitoring.telegram.hourly_limit', 20));
        $criticalLimit = max(1, (int) config('monitoring.telegram.critical_hourly_limit_per_rule', 10));

        $announced = 0;
        $held = [];
        foreach (['critical', 'other'] as $class) {
            $ofClass = $pending->filter(fn (Alert $a) => ($a->severity === 'critical') === ($class === 'critical'));
            // One turn per rule at a time: a grouped message for a rule with many, else its oldest alert
            $queues = $ofClass->groupBy('alert_rule_id')->map(fn ($alerts) => $alerts->values()->all())->all();
            while ($queues !== []) {
                foreach (array_keys($queues) as $ruleId) {
                    $batch = count($queues[$ruleId]) > $this->groupSize() ? $queues[$ruleId] : [array_shift($queues[$ruleId])];
                    if (count($batch) > 1) {
                        $queues[$ruleId] = [];
                    }
                    $room = $class === 'critical'
                        ? ($criticalsSent[$ruleId] ?? 0) < $criticalLimit
                        : $warningsSent < $warningLimit;
                    if (!$room) {
                        array_push($held, ...$batch);
                    } elseif ($this->announce($batch, $chatId)) {
                        $announced += count($batch);
                        if ($class === 'critical') {
                            $criticalsSent[$ruleId] = ($criticalsSent[$ruleId] ?? 0) + 1;
                            $log['c'][$ruleId][] = now()->getTimestamp();
                        } else {
                            $warningsSent++;
                            $log['w'][] = now()->getTimestamp();
                        }
                    }
                    if ($queues[$ruleId] === []) {
                        unset($queues[$ruleId]);
                    }
                }
            }
        }

        $this->saveSentLog($log);
        if ($held !== []) {
            $this->digest($held, $chatId);
        }

        return $announced;
    }

    /** monitoring_markers key: timestamps of the last hour's announcement messages (warnings, criticals per rule). */
    public const SENT_LOG_MARKER = 'telegram_sent_log';

    /**
     * @return array{w: list<int>, c: array<int, list<int>>}
     */
    private function sentLog(): array
    {
        $since = now()->subHour()->getTimestamp();
        $meta = (array) (MonitoringMarker::query()->find(self::SENT_LOG_MARKER)->meta ?? []);
        $recent = fn ($list) => array_values(array_filter(array_map('intval', (array) $list), fn ($t) => $t >= $since));
        $critical = [];
        foreach ((array) ($meta['c'] ?? []) as $rule => $list) {
            $critical[(int) $rule] = $recent($list);
        }

        return ['w' => $recent($meta['w'] ?? []), 'c' => $critical];
    }

    /**
     * @param  array{w: list<int>, c: array<int, list<int>>}  $log
     */
    private function saveSentLog(array $log): void
    {
        MonitoringMarker::query()->updateOrCreate(['key' => self::SENT_LOG_MARKER], ['recorded_at' => now(), 'meta' => $log]);
    }

    /**
     * Ids of alerts that carry a marker with this prefix.
     *
     * @return list<int>
     */
    private function markedIds(string $prefix): array
    {
        return MonitoringMarker::query()->where('key', 'like', $prefix . '%')->pluck('key')
            ->map(fn ($key) => (int) substr((string) $key, strlen($prefix)))->filter()->values()->all();
    }

    /** The evaluator raised this alert to critical: announce it again (its first announcement stays, for the resolve). */
    public function markEscalated(Alert $alert): void
    {
        MonitoringMarker::query()->updateOrCreate(['key' => self::ESCALATED_PREFIX . $alert->getKey()], ['recorded_at' => now(), 'meta' => null]);
    }

    /**
     * Queued messages the worker has not sent within QUEUED_RESEND_MINUTES: sent directly now (removed on success).
     */
    private function resendStaleQueued(): void
    {
        $stale = MonitoringMarker::query()->where('key', 'like', self::QUEUED_PREFIX . '%')
            ->where('recorded_at', '<', now()->subMinutes(self::QUEUED_RESEND_MINUTES))->orderBy('recorded_at')->limit(10)->get();
        foreach ($stale as $marker) {
            $meta = (array) $marker->meta;
            if (!isset($meta['chat_id'], $meta['text']) || $this->direct((string) $meta['chat_id'], (string) $meta['text']) !== false) {
                $marker->delete();
            }
        }
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

        return $this->announce([$alert], $chatId);
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

        $alert->forceFill(['telegram_resolved_at' => now()])->syncOriginal();
        MonitoringMarker::query()->whereKey(self::ESCALATED_PREFIX . $alert->getKey())->delete();
        // Sent by flushResolved() (grouped per rule when many resolve together)
        $this->resolvedBuffer[(int) $alert->alert_rule_id][] = ['alert' => $alert, 'by' => $by];

        return true;
    }

    /**
     * Sends this run's resolve notices: one per alert, or one per rule when more than GROUP_SIZE resolved together.
     */
    public function flushResolved(): void
    {
        $chatId = $this->chatId();
        $buffer = $this->resolvedBuffer;
        $this->resolvedBuffer = [];
        if ($chatId === null) {
            return;
        }

        foreach ($buffer as $entries) {
            if (count($entries) > $this->groupSize()) {
                $titles = array_map(fn ($e) => mb_substr($e['alert']->title, 0, 80), array_slice($entries, 0, self::GROUP_SIZE));
                $label = AlertKind::from($entries[0]['alert']->kind)->label();
                $this->send($chatId, "[HAL QILINDI] {$label}: " . count($entries) . ' tasi tiklandi: ' . implode('; ', $titles)
                    . (count($entries) > self::GROUP_SIZE ? ' va yana ' . (count($entries) - self::GROUP_SIZE) . ' ta' : '') . "\n" . $this->link());

                continue;
            }
            foreach ($entries as ['alert' => $alert, 'by' => $by]) {
                $minutes = max(0, (int) floor($alert->first_seen_at->diffInMinutes($alert->resolved_at ?? now(), true)));
                $this->send($chatId, implode("\n", array_filter([
                    '[HAL QILINDI] ' . $alert->title,
                    "Davomiyligi: {$minutes} daqiqa",
                    $by !== null ? "Hal qildi: {$by}" : 'Muammo o\'z-o\'zidan tugadi',
                    $this->link(),
                ])));
            }
        }
    }

    // ------------------------------------------------------------------------------------------------------------

    /**
     * One message for the batch (one alert, or a rule's group); the alerts count as announced only when the message
     * was sent or handed to the queue.
     *
     * @param  list<Alert>  $batch
     */
    private function announce(array $batch, string $chatId): bool
    {
        $text = count($batch) === 1 ? $this->openText($batch[0]) : $this->groupText($batch);
        if (!$this->send($chatId, $text)) {
            return false;
        }
        $at = now();
        $ids = array_map(fn (Alert $a) => $a->getKey(), $batch);
        Alert::query()->whereKey($ids)->whereNull('telegram_opened_at')->update(['telegram_opened_at' => $at]);
        MonitoringMarker::query()->whereIn('key', array_map(fn ($id) => self::ESCALATED_PREFIX . $id, $ids))->delete();
        foreach ($batch as $alert) {
            if ($alert->telegram_opened_at === null) {
                $alert->forceFill(['telegram_opened_at' => $at])->syncOriginal();
            }
        }

        return true;
    }

    /**
     * @param  list<Alert>  $batch  alerts of one rule
     */
    private function groupText(array $batch): string
    {
        $kind = AlertKind::from($batch[0]->kind);
        $critical = collect($batch)->contains(fn (Alert $a) => $a->severity === 'critical');
        $titles = array_map(fn (Alert $a) => mb_substr($a->title, 0, 80), array_slice($batch, 0, self::GROUP_SIZE));

        return implode("\n", [
            '[' . $this->severityLabel($critical ? 'critical' : 'warning') . '] ' . $kind->label() . ': ' . count($batch) . ' ta: ' . implode('; ', $titles)
                . (count($batch) > self::GROUP_SIZE ? ' va yana ' . (count($batch) - self::GROUP_SIZE) . ' ta' : ''),
            'Nima qilish kerak: ' . $kind->action(),
            $this->link(),
        ]);
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

    private function groupSize(): int
    {
        return max(1, (int) config('monitoring.telegram.group_size', self::GROUP_SIZE));
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
     * At once when Telegram is reachable and the run's budget allows, else through the queue. False only when the
     * message was neither sent nor queued (it is then offered again next run).
     */
    private function send(string $chatId, string $text): bool
    {
        $this->deadline ??= now()->addMilliseconds((int) round(1000 * max(1, (float) config('monitoring.telegram.run_budget_seconds', 10))));
        $left = (float) now()->diffInMilliseconds($this->deadline, false) / 1000;
        $timeout = min(max(1, (float) config('monitoring.telegram.timeout', 5)), $left);

        if (!$this->api->isConfigured()) {
            return true; // nothing configured to send with: like before, counted as told (the page says it is off)
        }
        if ($timeout < 0.5 || $this->breakerOpen()) {
            return $this->queue($chatId, $text);
        }

        return $this->direct($chatId, $text, $timeout) ?? $this->queue($chatId, $text);
    }

    /**
     * One direct request: true sent, false refused for good (logged), null not reachable (try the queue).
     */
    private function direct(string $chatId, string $text, ?float $timeout = null): ?bool
    {
        if ($this->breakerOpen()) {
            return null;
        }
        try {
            $response = $this->api->call('sendMessage', ['chat_id' => $chatId, 'text' => $text], null, $timeout ?? (float) config('monitoring.telegram.timeout', 5));
        } catch (ConnectionException $e) {
            $this->trip($this->api->redact($e->getMessage()));

            return null;
        } catch (Throwable $e) {
            Log::warning('Monitoring: Telegram alert could not be sent at once', ['exception' => $e::class]);

            return null;
        }

        if ($response->successful()) {
            return true;
        }
        if ($this->api->isRetryable($response)) {
            $this->trip('HTTP ' . $response->status());

            return null;
        }

        // Unknown chat, blocked bot, bad text: the queue would not retry it either; final
        Log::warning('Monitoring: Telegram refused an alert message', ['chat_id' => $chatId, 'status' => $response->status(), 'error' => mb_substr($response->body(), 0, 300)]);

        return true;
    }

    /** Pushes the message to the queue with its resend marker; false when the push failed. */
    private function queue(string $chatId, string $text): bool
    {
        $key = self::QUEUED_PREFIX . (string) \Illuminate\Support\Str::uuid();
        try {
            MonitoringMarker::query()->create(['key' => $key, 'recorded_at' => now(), 'meta' => ['chat_id' => $chatId, 'text' => $text]]);
        } catch (Throwable $e) {
            Log::warning('Monitoring: queued alert message marker could not be written', ['exception' => $e::class]);
        }
        try {
            \Illuminate\Support\Facades\Bus::dispatch(new QueuedOpsMessage($chatId, $text, $key));

            return true;
        } catch (Throwable $e) {
            Log::error('Monitoring: alert message could not be queued either', ['exception' => $e::class]);
            try {
                MonitoringMarker::query()->whereKey($key)->delete();
            } catch (Throwable) {
                // nothing more to do
            }

            return false;
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
