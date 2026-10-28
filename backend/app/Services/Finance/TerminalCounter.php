<?php

namespace App\Services\Finance;

use App\Enums\DeviceType;
use App\Models\Device;
use Carbon\CarbonInterface;

/**
 * P1-23: how many terminals a store is billed for in a period (docs/BILLING.md): the most POS/KDS terminals that were
 * paired and not revoked at the same moment within [from, to). A terminal replaced by revoking the old one first
 * counts once; one revoked on the last day still counts for the month it was used in.
 */
class TerminalCounter
{
    /**
     * @param  list<DeviceType>|null  $types  P4-21: only these terminal types (Billing v2 bills kassa and KDS apart)
     */
    public function maxConcurrent(int $storeId, CarbonInterface $from, CarbonInterface $to, ?array $types = null): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        // Query bindings are written in the date's own timezone; the columns hold the app's
        $from = $from->copy()->setTimezone(config('app.timezone'));
        $to = $to->copy()->setTimezone(config('app.timezone'));

        $devices = Device::query()
            ->where('store_id', $storeId)
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, $types ?? DeviceType::terminals()))
            ->where('created_at', '<', $to)
            ->where(fn ($query) => $query->whereNull('revoked_at')->orWhere('revoked_at', '>', $from))
            ->get(['id', 'created_at', 'revoked_at']);

        // +1 when a terminal becomes active within the period, -1 when it is revoked before the period ends
        $events = [];
        foreach ($devices as $device) {
            $start = max($device->created_at?->getTimestamp() ?? $from->getTimestamp(), $from->getTimestamp());
            $end = $device->revoked_at !== null && $device->revoked_at->lessThan($to) ? $device->revoked_at->getTimestamp() : null;

            if ($end !== null && $end <= $start) {
                continue;
            }

            $events[] = [$start, 1];
            if ($end !== null) {
                $events[] = [$end, -1];
            }
        }

        // At the same moment a revocation goes first, so a replaced terminal is not counted twice
        usort($events, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $current = 0;
        $max = 0;
        foreach ($events as [, $change]) {
            $current += $change;
            $max = max($max, $current);
        }

        return $max;
    }

    /**
     * Terminals paired and not revoked right now.
     */
    /**
     * @param  list<DeviceType>|null  $types
     */
    public function activeNow(int $storeId, ?array $types = null): int
    {
        return Device::query()
            ->where('store_id', $storeId)
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, $types ?? DeviceType::terminals()))
            ->whereNull('revoked_at')
            ->count();
    }
}
