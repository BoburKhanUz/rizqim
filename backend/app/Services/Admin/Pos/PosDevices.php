<?php

namespace App\Services\Admin\Pos;

use App\Domain\Pos\Sync\PosSyncService;
use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P4-24 Qurilmalar: the devices of every store (the terminals and the staff's app installs) with their online state
 * and app version. Online = seen in the last 10 minutes (the same limit the Bugun "offline" source uses). There is no
 * release registry, so "outdated" means older than the newest version seen on an active device of the same type.
 */
class PosDevices
{
    public const ONLINE_MINUTES = 10;

    public const ROW = 'array{uuid: string, name: string, type: string, type_label: string, terminal: bool, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, platform: string|null, app_version: string|null, latest_version: string|null, outdated: bool, state: string, last_seen_at: string|null, paired_at: string|null, revoked_at: string|null, sync: array{pending_ops: int, oldest_op_at: string|null, reported_at: string|null, clock_skew_seconds: int|null, clock_warning: bool}}';

    /**
     * @return Builder<Device>
     */
    private function base(): Builder
    {
        // Devices of a store: a person's own installs without a store belong to the customer sections
        return Device::query()->whereNotNull('devices.store_id');
    }

    /**
     * @param  array<string, mixed>  $filters  q, type, store_id, state (online|offline|never|revoked|all; default: not revoked), outdated, terminal
     * @return Builder<Device>
     */
    public function query(array $filters, ?Carbon $now = null): Builder
    {
        $now ??= now();
        $query = $this->base();
        $state = (string) ($filters['state'] ?? '');

        if ($state === 'revoked') {
            $query->whereNotNull('revoked_at');
        } elseif ($state !== 'all') {
            $query->whereNull('revoked_at');
        }
        $threshold = $now->copy()->subMinutes(self::ONLINE_MINUTES);
        match ($state) {
            'online' => $query->where('last_seen_at', '>=', $threshold),
            'offline' => $query->where('last_seen_at', '<', $threshold),
            'never' => $query->whereNull('last_seen_at'),
            default => null,
        };

        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (filter_var($filters['terminal'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('type', array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()));
        }
        if (filled($filters['store_id'] ?? null)) {
            $query->where('store_id', (int) $filters['store_id']);
        }
        if (filled($filters['q'] ?? null)) {
            $q = trim((string) $filters['q']);
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(fn (Builder $inner) => $inner
                ->where('devices.name', 'ilike', $like)
                ->orWhere('devices.uuid', 'like', addcslashes(strtolower($q), '%_\\') . '%')
                ->orWhereIn('devices.store_id', \App\Models\Store::query()->where('name', 'ilike', $like)->select('id')));
        }
        if (filter_var($filters['outdated'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->onlyOutdated($query);
        }

        return $query;
    }

    /**
     * The newest app version per device type among the active devices.
     *
     * @return array<string, string>
     */
    public function latestVersions(): array
    {
        $latest = [];
        foreach ($this->versionsSeen() as $type => $versions) {
            usort($versions, fn (string $a, string $b) => version_compare($b, $a));
            $latest[$type] = $versions[0];
        }
        ksort($latest);

        return $latest;
    }

    /**
     * @param  Builder<Device>  $query
     */
    private function onlyOutdated(Builder $query): void
    {
        $outdated = [];
        foreach ($this->versionsSeen() as $type => $versions) {
            usort($versions, fn (string $a, string $b) => version_compare($b, $a));
            $newest = $versions[0];
            $old = array_values(array_filter($versions, fn (string $version) => version_compare($version, $newest) < 0));
            if ($old !== []) {
                $outdated[$type] = $old;
            }
        }

        $query->where(function (Builder $inner) use ($outdated) {
            $inner->whereRaw('1 = 0');
            foreach ($outdated as $type => $versions) {
                $inner->orWhere(fn (Builder $one) => $one->where('devices.type', $type)->whereIn('devices.app_version', $versions));
            }
        });
    }

    /**
     * @return array<string, list<string>> type => the distinct versions on active devices
     */
    private function versionsSeen(): array
    {
        $seen = [];
        $rows = $this->base()->whereNull('revoked_at')->whereNotNull('app_version')->where('app_version', '!=', '')
            ->distinct()->get(['type', 'app_version']);
        foreach ($rows as $row) {
            $seen[$row->type->value][] = (string) $row->app_version;
        }

        return $seen;
    }

    /**
     * @return array{total: int, online: int, offline: int, never: int, revoked: int, outdated: int, by_type: array<string, int>}
     */
    public function counts(?Carbon $now = null): array
    {
        $now ??= now();
        $active = fn () => $this->base()->whereNull('revoked_at');
        $threshold = $now->copy()->subMinutes(self::ONLINE_MINUTES);

        return [
            'total' => $active()->count(),
            'online' => $active()->where('last_seen_at', '>=', $threshold)->count(),
            'offline' => $active()->where('last_seen_at', '<', $threshold)->count(),
            'never' => $active()->whereNull('last_seen_at')->count(),
            'revoked' => $this->base()->whereNotNull('revoked_at')->count(),
            'outdated' => $this->query(['outdated' => true], $now)->count(),
            'by_type' => $active()->selectRaw('type, COUNT(*) AS n')->groupBy('type')->pluck('n', 'type')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /**
     * @param  Builder<Device>  $query
     * @return Builder<Device>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'branch:id,name']);
    }

    /**
     * @param  iterable<Device>  $devices
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $devices, ?Carbon $now = null): array
    {
        $latest = $this->latestVersions();
        $now ??= now();

        return Collection::make($devices)->map(fn (Device $device) => $this->row($device, $now, $latest))->values()->all();
    }

    /**
     * @param  array<string, string>  $latest
     * @return array<string, mixed>
     */
    public function row(Device $device, Carbon $now, array $latest): array
    {
        $newest = $latest[$device->type->value] ?? null;
        $version = filled($device->app_version) ? (string) $device->app_version : null;

        return [
            'uuid' => (string) $device->uuid,
            'name' => (string) $device->name,
            'type' => $device->type->value,
            'type_label' => $device->type->label(),
            'terminal' => $device->type->isTerminal(),
            'store' => $device->store ? ['id' => (int) $device->store->id, 'name' => (string) $device->store->name] : null,
            'branch' => $device->branch ? ['id' => (int) $device->branch->id, 'name' => (string) $device->branch->name] : null,
            'platform' => $device->platform,
            'app_version' => $version,
            'latest_version' => $newest,
            'outdated' => $version !== null && $newest !== null && version_compare($version, $newest) < 0,
            'state' => $this->state($device, $now),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'paired_at' => $device->created_at?->toIso8601String(),
            'revoked_at' => $device->revoked_at?->toIso8601String(),
            'sync' => [
                'pending_ops' => (int) $device->sync_pending_ops,
                'oldest_op_at' => $device->sync_oldest_op_at?->toIso8601String(),
                'reported_at' => $device->sync_reported_at?->toIso8601String(),
                'clock_skew_seconds' => $device->sync_clock_skew_seconds,
                'clock_warning' => PosSyncService::clockWarning($device->sync_clock_skew_seconds),
            ],
        ];
    }

    private function state(Device $device, Carbon $now): string
    {
        if ($device->revoked_at !== null) {
            return 'revoked';
        }
        if ($device->last_seen_at === null) {
            return 'never';
        }

        return $device->last_seen_at->greaterThanOrEqualTo($now->copy()->subMinutes(self::ONLINE_MINUTES)) ? 'online' : 'offline';
    }
}
