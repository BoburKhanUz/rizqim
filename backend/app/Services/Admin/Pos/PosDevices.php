<?php

namespace App\Services\Admin\Pos;

use App\Domain\Pos\Releases\AppVersion;
use App\Domain\Pos\Releases\PosAppReleases;
use App\Domain\Pos\Sync\PosSyncService;
use App\Domain\Pos\Sync\SyncSignatureStatus;
use App\Enums\DeviceApp;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P4-24 Qurilmalar: the devices of every store (the terminals and the staff's app installs) with their online state
 * and app version. Online = seen in the last 10 minutes (the same limit the Bugun "offline" source uses).
 *
 * Wave M5 S5-a: "latest" comes from the release registry (pos_app_releases, App\Domain\Pos\Releases\PosAppReleases),
 * per app and platform (`latest_versions` is keyed "<app>/<platform>", e.g. `cashier/android`), no longer guessed from
 * the fleet. A device whose app and platform the registry knows nothing about has no `latest_version` and is never
 * "outdated" (an empty registry marks nothing).
 *
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4): `sync.signatures` — what the signatures of the till's offline operations
 * were worth in the last SIGNATURE_DAYS days (ok, missing: unsigned, bad, chain_gap: after a lost line), the number of
 * its queue signing key (`key_id`, null: none) and whether its store enforces signatures. The key itself is never read
 * here.
 */
class PosDevices
{
    public const ONLINE_MINUTES = 10;

    /** Wave M5 S3-d: `sync.signatures` counts the offline operations received in this many days. */
    public const SIGNATURE_DAYS = 7;

    public const ROW = 'array{uuid: string, name: string, type: string, type_label: string, terminal: bool, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, platform: string|null, app_version: string|null, latest_version: string|null, outdated: bool, state: string, last_seen_at: string|null, paired_at: string|null, revoked_at: string|null, sync: array{pending_ops: int, oldest_op_at: string|null, reported_at: string|null, clock_skew_seconds: int|null, clock_warning: bool, signatures: array{days: int, ok: int, missing: int, bad: int, chain_gap: int, key_id: int|null, enforce: bool}}}';

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
     * The latest registered version per app and platform ("cashier/android" => "1.4.0+7").
     *
     * @return array<string, string>
     */
    public function latestVersions(): array
    {
        $latest = [];
        foreach (app(PosAppReleases::class)->latestRows() as $key => $release) {
            $latest[$key] = $release->label();
        }
        ksort($latest);

        return $latest;
    }

    /**
     * Devices whose reported version is older than the registry's latest for their app and platform. The version
     * order is decided in PHP over the distinct versions seen (as before), then the query keeps those versions.
     *
     * @param  Builder<Device>  $query
     */
    private function onlyOutdated(Builder $query): void
    {
        $latest = app(PosAppReleases::class)->latestRows();
        $outdated = [];
        foreach ($this->versionsSeen() as $row) {
            $release = $latest[$row['app'] . '/' . $row['platform']] ?? null;
            $current = AppVersion::parse($row['version']);
            if ($release !== null && $current !== null && AppVersion::compare($current, ['version' => (string) $release->version, 'build' => (int) $release->build]) < 0) {
                $outdated[] = $row;
            }
        }

        $query->where(function (Builder $inner) use ($outdated) {
            $inner->whereRaw('1 = 0');
            foreach ($outdated as $row) {
                $app = DeviceApp::from($row['app']);
                $inner->orWhere(function (Builder $one) use ($app, $row) {
                    $one->where('devices.type', $app->deviceType()->value)
                        ->whereRaw('lower(devices.platform) = ?', [$row['platform']])
                        ->where('devices.app_version', $row['version'])
                        ->where(function (Builder $named) use ($app) {
                            $named->where('devices.app', $app->value);
                            // A device with no recorded app runs its type's default app
                            if ($app === self::defaultApp($app->deviceType())) {
                                $named->orWhereNull('devices.app');
                            }
                        });
                });
            }
        });
    }

    private static function defaultApp(DeviceType $type): ?DeviceApp
    {
        return match ($type) {
            DeviceType::POS => DeviceApp::CASHIER,
            DeviceType::KDS => DeviceApp::KDS,
            DeviceType::DISPLAY => DeviceApp::CUSTOMER_DISPLAY,
            default => null,
        };
    }

    /**
     * @return list<array{app: string, platform: string, version: string}> the distinct (app, platform, version) on active devices the registry can judge
     */
    private function versionsSeen(): array
    {
        $releases = app(PosAppReleases::class);
        $seen = [];
        $rows = $this->base()->whereNull('revoked_at')->whereNotNull('app_version')->where('app_version', '!=', '')
            ->distinct()->get(['type', 'app', 'platform', 'app_version']);
        foreach ($rows as $row) {
            $app = $releases->appOf($row);
            $platform = PosAppReleases::platformOf($row->platform);
            if ($app === null || $platform === null) {
                continue;
            }
            $seen[$app->value . '/' . $platform . '/' . $row->app_version] = ['app' => $app->value, 'platform' => $platform, 'version' => (string) $row->app_version];
        }

        return array_values($seen);
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
            'by_type' => $active()->selectRaw('type, COUNT(*) AS n')->groupBy('type')->orderBy('type')->pluck('n', 'type')->map(fn ($n) => (int) $n)->all(),
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
        $devices = Collection::make($devices);
        $signatures = $this->signatures($devices, $now);

        return $devices->map(fn (Device $device) => $this->row($device, $now, $latest, $signatures[$device->id] ?? null))->values()->all();
    }

    /**
     * Wave M5 S3-d: per device, the last SIGNATURE_DAYS days of offline operations by what their signature was worth
     * (one grouped read over the (device_id, sig_status, received_at) index) and the store's mode.
     *
     * @param  Collection<int, Device>  $devices
     * @return array<int, array{days: int, ok: int, missing: int, bad: int, chain_gap: int, key_id: int|null, enforce: bool}>
     */
    private function signatures(Collection $devices, Carbon $now): array
    {
        if ($devices->isEmpty()) {
            return [];
        }
        $counts = [];
        $rows = DB::table('pos_sync_operations')
            ->whereIn('device_id', $devices->pluck('id'))
            // (the statuses by name, not "is not null": an index probe per status instead of a scan of the till's week)
            ->whereIn('sig_status', array_map(fn (SyncSignatureStatus $status) => $status->value, SyncSignatureStatus::cases()))
            ->where('received_at', '>=', $now->copy()->subDays(self::SIGNATURE_DAYS))
            ->selectRaw('device_id, sig_status, count(*) AS n')
            ->groupBy('device_id', 'sig_status')
            ->get();
        foreach ($rows as $row) {
            $counts[(int) $row->device_id][(string) $row->sig_status] = (int) $row->n;
        }
        $enforcing = Store::query()->withTrashed()->whereIn('id', $devices->pluck('store_id')->filter()->unique())
            ->where('pos_sync_enforce_signatures', true)->pluck('id')->flip();

        $out = [];
        foreach ($devices as $device) {
            $kid = (int) $device->getAttribute('sync_key_id');
            $out[$device->id] = ['days' => self::SIGNATURE_DAYS];
            foreach (SyncSignatureStatus::cases() as $status) {
                $out[$device->id][$status->value] = $counts[$device->id][$status->value] ?? 0;
            }
            $out[$device->id] += ['key_id' => $kid > 0 ? $kid : null, 'enforce' => isset($enforcing[$device->store_id])];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $latest
     * @param  array{days: int, ok: int, missing: int, bad: int, chain_gap: int, key_id: int|null, enforce: bool}|null  $signatures  rows() works them out for the whole page; without them (the export, one row at a time) `sync.signatures` is left out
     * @return array<string, mixed>
     */
    public function row(Device $device, Carbon $now, array $latest, ?array $signatures = null): array
    {

        $releases = app(PosAppReleases::class);
        $app = $releases->appOf($device);
        $platform = PosAppReleases::platformOf($device->platform);
        $newest = $app !== null && $platform !== null ? ($latest[$app->value . '/' . $platform] ?? null) : null;
        $version = filled($device->app_version) ? (string) $device->app_version : null;
        $current = AppVersion::parse($version);
        $newestParts = AppVersion::parse($newest);

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
            'outdated' => $current !== null && $newestParts !== null && AppVersion::compare($current, $newestParts) < 0,
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
            ] + ($signatures !== null ? ['signatures' => $signatures] : []),
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
