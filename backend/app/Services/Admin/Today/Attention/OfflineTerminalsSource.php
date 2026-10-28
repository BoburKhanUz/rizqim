<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Models\Device;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Monitoring\TerminalBusinessHours;
use Carbon\CarbonImmutable;

/**
 * Paired POS and KDS terminals of active stores that have not reached the server for OFFLINE_MINUTES while their
 * branch is open (TerminalBusinessHours, the same answer as the POS offline alert: the branch's hours, else the store's; a
 * store switched to "closed" is skipped). A terminal reports at least once a minute while it works
 * (DeviceTokenGuard), so silence means it is off, without internet or in offline mode. Terminals silent for more than
 * WITHIN_DAYS are left to the POS section's device list (most likely put away, not revoked).
 */
class OfflineTerminalsSource extends TodayAttentionSource
{
    public const OFFLINE_MINUTES = 10;

    public const WITHIN_DAYS = 3;

    // Opening hours are checked in PHP; candidates are bounded (partial index on last_seen_at)
    private const CANDIDATES = 500;

    public function __construct(
        private readonly TerminalBusinessHours $hours,
    ) {}

    public function permission(): AdminPermission
    {
        return AdminPermission::DEVICES_VIEW;
    }

    public function kind(): string
    {
        return 'device_offline';
    }

    public function label(): string
    {
        return 'Ish vaqtida oflayn kassalar';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $now = CarbonImmutable::now();

        $offline = $this->hours->constrain(Device::query())
            ->where('last_seen_at', '<', $now->subMinutes(self::OFFLINE_MINUTES))
            ->where('last_seen_at', '>=', $now->subDays(self::WITHIN_DAYS))
            ->orderBy('last_seen_at')
            ->orderBy('id')
            ->limit(self::CANDIDATES)
            ->get(['id', 'uuid', 'store_id', 'branch_id', 'type', 'name', 'last_seen_at'])
            ->filter(fn (Device $device) => $this->hours->isOpen($device, $now))
            ->values();

        $items = $offline->take($limit)->map(fn (Device $device) => new AttentionItem(
            kind: $this->kind(),
            severity: 'warning',
            title: $this->join([$device->name, $device->store?->name]) ?? (string) $device->name,
            subtitle: $this->join([
                $device->type->label(),
                $device->branch?->name,
                'oxirgi aloqa ' . $this->time($device->last_seen_at),
            ]),
            href: '/pos?device=' . $device->uuid,
            since: $this->iso($device->last_seen_at),
            storeId: (int) $device->store_id,
        ))->values()->all();

        return ['count' => $offline->count(), 'items' => $items];
    }
}
