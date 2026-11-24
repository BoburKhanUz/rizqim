<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Sms\SmsFailures;

/**
 * Review 17 M2: SMS that did not go out in the last `window_minutes`, from the counter the SMS drivers keep in the
 * cache (SmsFailures). Three subjects:
 *  - `config` (critical, from one failure): the driver cannot send at all — no API key, the gateway refused the key
 *    (401/403: revoked, or without the sms:send scope), a base URL that is not https, an unknown sms_driver. Every
 *    sign-in code fails until it is fixed;
 *  - `disabled` (critical, from one): sms_driver = log outside local/testing, so codes reach nobody;
 *  - `delivery` (warning, from `threshold`): the gateway's limit (429), refused data, no answer / 5xx through every
 *    try, an answer that is not the gateway's message.
 * Each resolves once its window holds no such failure. Counts only: never a phone, a text or a key.
 */
class SmsFailuresCheck implements AlertCheck
{
    private const DELIVERY = [SmsFailures::RATE_LIMITED, SmsFailures::REFUSED, SmsFailures::UNEXPECTED, SmsFailures::UNAVAILABLE];

    public function __construct(
        private readonly SmsFailures $failures,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $window = max(1, min(SmsFailures::MAX_WINDOW, (int) ($rule->window_minutes ?? 15)));
        $counts = $this->failures->counts($window);
        $details = ['window_minutes' => $window, 'threshold' => (int) $rule->threshold];

        $findings = [];
        if (($counts[SmsFailures::CONFIG] ?? 0) > 0) {
            $findings[] = new AlertFinding('config', "SMS yuborilmayapti: SMS xizmati sozlanmagan yoki API kalitni rad etdi (oxirgi {$window} daqiqada {$counts[SmsFailures::CONFIG]} ta)", $details + [
                'count' => $counts[SmsFailures::CONFIG],
            ], null, 'critical');
        }
        if (($counts[SmsFailures::DISABLED] ?? 0) > 0) {
            $findings[] = new AlertFinding('disabled', "SMS o'chiq (sms_driver = log): oxirgi {$window} daqiqada {$counts[SmsFailures::DISABLED]} ta SMS hech kimga yuborilmadi", $details + [
                'count' => $counts[SmsFailures::DISABLED],
            ], null, 'critical');
        }

        $delivery = array_intersect_key($counts, array_flip(self::DELIVERY));
        $total = array_sum($delivery);
        if ($total > 0 && $total >= $rule->threshold) {
            $findings[] = new AlertFinding('delivery', "Oxirgi {$window} daqiqada {$total} ta SMS yuborilmadi", $details + [
                'count' => $total,
                'reasons' => $delivery,
            ], null, 'warning');
        }

        return $findings;
    }
}
