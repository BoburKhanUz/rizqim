<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Cache;

/**
 * Review 17 M2: every SMS that did not go out is counted here, per reason and per minute, in the cache (Redis in
 * production), and the monitoring rule `sms_failures` (SmsFailuresCheck) reads the counts of its window: the owner /
 * ops group hears that sign-in codes stopped instead of only a log line saying so. Numbers only: never a phone, a
 * text or a key.
 *
 * It also remembers, for the current request or job (a scoped binding), why the last send failed, so the sign-in
 * code endpoints answer a gateway 429 like their own throttle.
 */
final class SmsFailures
{
    /** The driver cannot send at all: no API key, the gateway refused the key (401/403), a non-https base URL */
    public const CONFIG = 'config';

    /** sms_driver = log outside local/testing: nothing reaches a phone */
    public const DISABLED = 'disabled';

    /** The gateway's own limit (429) */
    public const RATE_LIMITED = 'rate_limited';

    /** Another 4xx (422: the gateway did not accept the data) */
    public const REFUSED = 'refused';

    /** An answer that is not the gateway's message (a 2xx without id / known status, failed or expired at once) */
    public const UNEXPECTED = 'unexpected';

    /** No answer or 5xx through every try */
    public const UNAVAILABLE = 'unavailable';

    public const REASONS = [self::CONFIG, self::DISABLED, self::RATE_LIMITED, self::REFUSED, self::UNEXPECTED, self::UNAVAILABLE];

    /** The longest window a rule can read (AlertKind::SMS_FAILURES fields), in minutes */
    public const MAX_WINDOW = 1440;

    private ?string $last = null;

    public function record(string $reason): void
    {
        $this->last = $reason;
        $key = self::key($reason, (int) floor(now()->getTimestamp() / 60));
        Cache::add($key, 0, (self::MAX_WINDOW + 5) * 60);
        Cache::increment($key);
    }

    /**
     * Why the last send of this request / job failed (null: it did not, or nothing was sent yet).
     */
    public function last(): ?string
    {
        return $this->last;
    }

    public function clearLast(): void
    {
        $this->last = null;
    }

    /**
     * The failures of the last `$windowMinutes` minutes (the current one included), per reason; reasons without
     * any are left out.
     *
     * @return array<string, int>
     */
    public function counts(int $windowMinutes): array
    {
        $window = max(1, min(self::MAX_WINDOW, $windowMinutes));
        $now = (int) floor(now()->getTimestamp() / 60);

        $counts = [];
        foreach (self::REASONS as $reason) {
            $keys = [];
            for ($minute = $now - $window + 1; $minute <= $now; $minute++) {
                $keys[] = self::key($reason, $minute);
            }
            $sum = array_sum(array_map('intval', array_filter(Cache::many($keys), fn ($value) => $value !== null)));
            if ($sum > 0) {
                $counts[$reason] = $sum;
            }
        }

        return $counts;
    }

    private static function key(string $reason, int $minute): string
    {
        return "sms:failures:{$reason}:{$minute}";
    }
}
