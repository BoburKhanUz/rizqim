<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Enums\DeviceType;
use App\Enums\StoreStatus;
use App\Models\AlertRule;
use App\Models\Device;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;

/**
 * A paired POS or KDS terminal of an active store has not been heard from for `threshold` minutes of the store's
 * business hours. Time outside the working hours does not count: a till switched on at opening time is not "offline
 * since last night". Hours are the store's open_time / close_time in its time zone (none set = open all day, like
 * Store::is_open). Terminals unseen for a week are taken as retired, not as an outage. One subject per device.
 */
class PosDeviceOfflineCheck implements AlertCheck
{
    private const RETIRED_AFTER_DAYS = 7;

    public const MAX_FINDINGS = 100;

    public function findings(AlertRule $rule): ?array
    {
        $now = CarbonImmutable::now();

        $devices = Device::query()
            ->active()
            ->whereNull('user_id')
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()))
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $now->subMinutes($rule->threshold))
            ->where('last_seen_at', '>=', $now->subDays(self::RETIRED_AFTER_DAYS))
            ->whereHas('store', fn ($query) => $query->where('status', StoreStatus::ACTIVE->value))
            ->with(['store:id,name,timezone,open_time,close_time', 'branch:id,name'])
            ->orderBy('last_seen_at')
            ->limit(self::MAX_FINDINGS * 3)
            ->get();

        $findings = [];
        foreach ($devices as $device) {
            $store = $device->store;
            $lastSeen = CarbonImmutable::instance($device->last_seen_at);
            $openedAt = $this->openSince($store->open_time, $store->close_time, StoreTimezone::of($store), $now);
            if ($openedAt === null) {
                continue; // closed right now
            }

            // Only the time since the store opened counts
            $silentSince = $lastSeen->gt($openedAt) ? $lastSeen : $openedAt;
            $minutes = (int) floor($silentSince->diffInMinutes($now, true));
            if ($minutes < $rule->threshold) {
                continue;
            }

            $label = $device->type === DeviceType::KDS ? 'Oshxona ekrani' : 'Kassa';
            $name = $device->name ?: $label;
            $findings[] = new AlertFinding(
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
                    'threshold_minutes' => $rule->threshold,
                ],
                (int) $store->id,
            );

            if (count($findings) >= self::MAX_FINDINGS) {
                break;
            }
        }

        return $findings;
    }

    /**
     * When the current business period began (in absolute time), or null if the store is closed at $now.
     */
    private function openSince(?string $open, ?string $close, string $timezone, CarbonImmutable $now): ?CarbonImmutable
    {
        $local = $now->setTimezone($timezone);
        $start = $this->time($open);
        $end = $this->time($close);

        // No hours, or open = close (24/7): open all the time; the start of the local day is as far back as the
        // check needs to look
        if ($start === null || $end === null || $start === $end) {
            return $local->startOfDay();
        }

        $time = $local->format('H:i:s');
        if ($end > $start) {
            return $time >= $start && $time <= $end ? $local->setTimeFromTimeString($start) : null;
        }

        // Overnight (18:00 - 02:00): in the evening part it opened today, in the small hours yesterday
        if ($time >= $start) {
            return $local->setTimeFromTimeString($start);
        }

        return $time <= $end ? $local->subDay()->setTimeFromTimeString($start) : null;
    }

    private function time(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);

        return strlen($value) === 5 ? $value . ':00' : substr($value, 0, 8);
    }
}
