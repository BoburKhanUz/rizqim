<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\TerminalBusinessHours;
use Carbon\CarbonImmutable;

/**
 * A paired POS or KDS terminal of an active store has not been heard from for `threshold` minutes of its branch's
 * business hours. Time outside the working hours does not count: a till switched on at opening time is not "offline
 * since last night". "Open now" is TerminalBusinessHours, the same answer Bugun gives (branch hours, else the store's;
 * a store switched to "closed" is skipped); a round-the-clock branch or store is measured from the terminal's own
 * last_seen_at, so the local midnight changes nothing. Terminals unseen for a week are taken as retired, not as an
 * outage.
 *
 * One subject per device. Every silent terminal of an open branch is evaluated (the candidates are not cut off before
 * the opening hours are applied). MAX_FINDINGS bounds the alerts, not the evaluation: devices that already have an
 * unresolved alert always keep their finding (so a long outage never resolves the alerts it already raised), and NEW
 * alerts are opened only while there is room; the rest are covered by one aggregate finding (subject `many`).
 */
class PosDeviceOfflineCheck implements AlertCheck
{
    private const RETIRED_AFTER_DAYS = 7;

    public const MAX_FINDINGS = 100;

    public const AGGREGATE = 'many';

    private const CHUNK = 500;

    public function __construct(
        private readonly TerminalBusinessHours $hours,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $now = CarbonImmutable::now();

        $existing = Alert::query()->where('alert_rule_id', $rule->id)->unresolved()
            ->where('subject_key', '<>', self::AGGREGATE)->pluck('subject_key')->flip();

        /** @var list<array{finding: AlertFinding, last_seen: CarbonImmutable, known: bool}> $silent */
        $silent = [];
        $this->hours->constrain(Device::query()->whereNull('user_id'))
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $now->subMinutes($rule->threshold))
            ->where('last_seen_at', '>=', $now->subDays(self::RETIRED_AFTER_DAYS))
            ->select(['id', 'uuid', 'store_id', 'branch_id', 'type', 'name', 'last_seen_at'])
            ->chunkById(self::CHUNK, function ($devices) use (&$silent, $rule, $now, $existing) {
                foreach ($devices as $device) {
                    $window = $this->hours->window($device, $now);
                    if ($window === null) {
                        continue; // closed right now
                    }

                    $lastSeen = CarbonImmutable::instance($device->last_seen_at);
                    // Only the time since the branch opened counts; round the clock: since the last sign of life
                    $silentSince = $window->since !== null && $window->since->gt($lastSeen) ? $window->since : $lastSeen;
                    $minutes = (int) floor($silentSince->diffInMinutes($now, true));
                    if ($minutes < $rule->threshold) {
                        continue;
                    }

                    $silent[] = [
                        'finding' => $this->finding($device, $lastSeen, $minutes, $rule->threshold),
                        'last_seen' => $lastSeen,
                        'known' => isset($existing[$device->uuid]),
                    ];
                }
            });

        // Alerts that already exist first (their problem is still there), then the longest silent
        usort($silent, fn (array $a, array $b) => [$b['known'], $a['last_seen']] <=> [$a['known'], $b['last_seen']]);

        $room = max(0, self::MAX_FINDINGS - count($existing));
        $findings = [];
        $withheld = 0;
        foreach ($silent as $row) {
            if ($row['known'] || $room > 0) {
                $findings[] = $row['finding'];
                $room -= $row['known'] ? 0 : 1;
            } else {
                $withheld++;
            }
        }

        if ($withheld > 0) {
            $total = count($silent);
            $findings[] = new AlertFinding(
                self::AGGREGATE,
                "{$total} ta kassa yoki oshxona ekrani aloqada emas",
                [
                    'offline_total' => $total,
                    'individual_alerts' => $total - $withheld,
                    'withheld' => $withheld,
                    'threshold_minutes' => $rule->threshold,
                ],
            );
        }

        return $findings;
    }

    private function finding(Device $device, CarbonImmutable $lastSeen, int $minutes, int $threshold): AlertFinding
    {
        $store = $device->store;
        $label = $device->type === \App\Enums\DeviceType::KDS ? 'Oshxona ekrani' : 'Kassa';
        $name = $device->name ?: $label;

        return new AlertFinding(
            $device->uuid,
            "{$store->name}: {$name} {$minutes} daqiqadan beri aloqada emas",
            [
                'device_uuid' => $device->uuid,
                'device_name' => $device->name,
                'device_type' => $device->type->value,
                'branch' => $device->branch?->name,
                'store' => $store->name,
                'last_seen_at' => $lastSeen->toIso8601String(),
                'offline_minutes' => $minutes,
                'threshold_minutes' => $threshold,
            ],
            (int) $store->id,
        );
    }
}
