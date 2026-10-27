<?php

namespace App\Services\Admin\Today\Attention;

use App\Domain\Delivery\ZoneResolver;
use App\Enums\AdminPermission;
use App\Enums\DeviceType;
use App\Enums\StoreStatus;
use App\Models\Device;
use App\Models\Store;
use App\Services\Admin\Attention\AttentionItem;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;

/**
 * Paired POS and KDS terminals of active stores that have not reached the server for OFFLINE_MINUTES while their
 * branch is open (its opening hours, ZoneResolver::isBranchOpen; a terminal without a branch: the store's hours; a
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
        private readonly ZoneResolver $zones,
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

        $offline = Device::query()
            ->whereNull('revoked_at')
            ->whereNotNull('store_id')
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()))
            ->where('last_seen_at', '<', $now->subMinutes(self::OFFLINE_MINUTES))
            ->where('last_seen_at', '>=', $now->subDays(self::WITHIN_DAYS))
            ->whereHas('store', fn ($store) => $store->where('status', StoreStatus::ACTIVE->value)->where('is_open', true))
            ->with([
                'store:id,name,timezone,is_open,open_time,close_time,status',
                'branch:id,store_id,name,status,is_active,open_time,close_time',
            ])
            ->orderBy('last_seen_at')
            ->orderBy('id')
            ->limit(self::CANDIDATES)
            ->get(['id', 'uuid', 'store_id', 'branch_id', 'type', 'name', 'last_seen_at'])
            ->filter(fn (Device $device) => $device->store !== null && $this->isOpen($device, $now))
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

    private function isOpen(Device $device, CarbonImmutable $now): bool
    {
        /** @var Store $store */
        $store = $device->store;

        if ($device->branch !== null) {
            return $this->zones->isBranchOpen($device->branch, $now, $store);
        }

        // No branch: the store's own hours, read like a branch's (no hours or equal ones: round the clock)
        $open = $this->clock($store->getRawOriginal('open_time'));
        $close = $this->clock($store->getRawOriginal('close_time'));
        if ($open === null || $close === null || $open === $close) {
            return true;
        }
        $local = $now->setTimezone(StoreTimezone::of($store))->format('H:i:s');

        return $open < $close ? ($local >= $open && $local < $close) : ($local >= $open || $local < $close);
    }

    private function clock(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;

        return strlen($value) === 5 ? $value . ':00' : substr($value, 0, 8);
    }
}
