<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\TlsProbe;
use Illuminate\Support\Carbon;

/**
 * M3: the public HTTPS certificate (TlsProbe: APP_URL's host or monitoring.tls.host) expires within `threshold` days
 * (warning) or `threshold_secondary` days (critical). No https host: nothing to check; the host not reachable from
 * the server: unknown (the external uptime monitor sees an outage).
 */
class TlsExpiryCheck implements AlertCheck
{
    public function __construct(
        private readonly TlsProbe $tls,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $state = $this->tls->check();
        if ($state === null) {
            return [];
        }
        if ($state['expires_at'] === null) {
            return null;
        }

        $expires = Carbon::parse($state['expires_at']);
        $seconds = now()->diffInSeconds($expires, false);
        $days = (int) floor($seconds / 86400);
        if ($days >= $rule->threshold) {
            return [];
        }

        $critical = $rule->threshold_secondary ?? 3;

        return [new AlertFinding($state['host'], $seconds <= 0
            ? "{$state['host']} sertifikati muddati tugagan: mijozlar saytni ocholmaydi"
            : "{$state['host']} sertifikati {$days} kundan keyin tugaydi (" . $expires->setTimezone('Asia/Tashkent')->format('d.m.Y') . ')', [
                'host' => $state['host'],
                'expires_at' => $expires->toIso8601String(),
                'days_left' => $days,
                'threshold_days' => $rule->threshold,
                'critical_days' => $critical,
            ], null, $days < $critical ? 'critical' : 'warning')];
    }
}
