<?php

namespace App\Services\Admin\Monitoring;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * P4-15: tells the ops Telegram group (TELEGRAM_OPS_CHAT_ID, else the superadmin chat) when an alert opens and when it
 * is resolved, through the queued Telegram path (TelegramMessenger). Plain text, numbers and names only.
 *
 * Rate limits keep a flapping problem from flooding the group: an alert of the same rule and subject is announced at
 * most once per `cooldown` minutes, and at most `hourly_limit` announcements go out per hour in all (one notice says
 * the rest were held back). A resolve message is sent only for an alert whose open message went out, so the group
 * never reads a "resolved" about something it was not told.
 */
class AlertNotifier
{
    private const LIMIT_KEY = 'monitoring:telegram:hour';

    public function __construct(
        private readonly TelegramMessenger $telegram,
    ) {}

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

        $this->telegram->sendMessage($chatId, implode("\n", array_filter([
            '[' . $this->severityLabel($alert->severity) . '] ' . $alert->title,
            AlertKind::from($alert->kind)->label(),
            'Boshlangan: ' . $alert->first_seen_at->setTimezone('Asia/Tashkent')->format('d.m.Y H:i'),
            $this->link(),
        ])));
        $alert->forceFill(['telegram_opened_at' => now()])->save();

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
        $this->telegram->sendMessage($chatId, implode("\n", array_filter([
            '[HAL QILINDI] ' . $alert->title,
            "Davomiyligi: {$minutes} daqiqa",
            $by !== null ? "Hal qildi: {$by}" : 'Muammo o\'z-o\'zidan tugadi',
            $this->link(),
        ])));
        $alert->forceFill(['telegram_resolved_at' => now()])->syncOriginal();

        return true;
    }

    private function allow(Alert $alert, string $chatId): bool
    {
        $limit = (int) config('monitoring.telegram.hourly_limit', 20);
        if (RateLimiter::tooManyAttempts(self::LIMIT_KEY, $limit)) {
            // One notice per hour that more are being held back
            if (Cache::add(self::LIMIT_KEY . ':notice', true, 3600)) {
                $this->telegram->sendMessage($chatId, "Ogohlantirishlar ko'p: bir soatda {$limit} tadan ortig'i Telegram'ga yuborilmaydi. Hammasi markazda: " . $this->link());
            }

            return false;
        }

        $cooldown = max(1, (int) config('monitoring.telegram.cooldown_minutes', 15));
        if (!Cache::add("monitoring:telegram:{$alert->alert_rule_id}:" . md5($alert->subject_key), true, $cooldown * 60)) {
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
