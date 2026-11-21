<?php

namespace App\Domain\Pos\Releases;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Models\Device;
use App\Models\PosAppRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave M5 S5-a (docs/scale/M5_DESIGN.md section 6, owner answer 8): when a till has run a version below the minimum
 * past the grace period (`minimum_from`, 14 days after the minimum was declared), the owner gets ONE message naming
 * the tills (the store's admin chat and every owner, through PosOwnerAlerts). Once per till per "period": a period is
 * one minimum declaration, remembered as devices.app_outdated_notified_release_id (the id of the minimum release the
 * till was told about); a later, higher minimum starts a new period. The till is never stopped, and nothing here is
 * read by any money route. Tills not seen for STALE_DAYS are not nagged about (they are not in use).
 */
class PosAppOutdatedNotice
{
    public const STALE_DAYS = 30;

    private const BATCH = 500;

    public function __construct(
        private readonly PosAppReleases $releases,
        private readonly PosOwnerAlerts $alerts,
    ) {}

    /**
     * @return int the number of owner messages sent (one per store)
     */
    public function run(?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;
        $byStore = [];

        foreach ($this->minimums() as $minimum) {
            if ($minimum->minimum_from !== null && $minimum->minimum_from->gt($now)) {
                continue;
            }
            $this->due($minimum, $now, function (Device $device) use (&$byStore, $minimum) {
                $byStore[(int) $device->store_id][] = ['device' => $device, 'minimum' => $minimum];
            });
        }

        // Review 3 LOW: one store's failure (its message could not be handed over) never stops the others; its tills
        // stay unmarked, so the next run tells that owner
        foreach ($byStore as $storeId => $entries) {
            try {
                $sent += $this->notify($storeId, $entries) ? 1 : 0;
            } catch (Throwable $e) {
                Log::error("The outdated POS app notice of store {$storeId} was not sent: {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /**
     * The minimum of every app and platform that has one.
     *
     * @return list<PosAppRelease>
     */
    private function minimums(): array
    {
        $minimums = [];
        foreach (PosAppRelease::query()->active()->where('is_minimum', true)->get() as $release) {
            $key = $release->app . '/' . $release->platform;
            if (!isset($minimums[$key]) || AppVersion::compare(['version' => $release->version, 'build' => $release->build], ['version' => $minimums[$key]->version, 'build' => $minimums[$key]->build]) > 0) {
                $minimums[$key] = $release;
            }
        }

        return array_values($minimums);
    }

    /**
     * @param  \Closure(Device): void  $each
     */
    private function due(PosAppRelease $minimum, Carbon $now, \Closure $each): void
    {
        $app = \App\Enums\DeviceApp::from($minimum->app);
        $floor = ['version' => (string) $minimum->version, 'build' => (int) $minimum->build];

        Device::query()
            ->whereNotNull('store_id')
            ->whereNull('revoked_at')
            ->where('type', $app->deviceType()->value)
            ->whereRaw('lower(platform) = ?', [$minimum->platform])
            ->whereNotNull('app_version')
            ->where('last_seen_at', '>=', $now->copy()->subDays(self::STALE_DAYS))
            ->where(fn ($query) => $query->whereNull('app_outdated_notified_release_id')->orWhere('app_outdated_notified_release_id', '!=', $minimum->id))
            // A device with no recorded app runs its type's default app
            ->where(function ($query) use ($app) {
                $query->where('app', $app->value);
                if (in_array($app, [\App\Enums\DeviceApp::CASHIER, \App\Enums\DeviceApp::KDS, \App\Enums\DeviceApp::CUSTOMER_DISPLAY], true)) {
                    $query->orWhereNull('app');
                }
            })
            ->with('branch:id,name')
            ->orderBy('id')
            ->chunkById(self::BATCH, function ($devices) use ($floor, $each) {
                foreach ($devices as $device) {
                    $current = AppVersion::parse($device->app_version);
                    if ($current !== null && AppVersion::compare($current, $floor) < 0) {
                        $each($device);
                    }
                }
            });
    }

    /**
     * @param  list<array{device: Device, minimum: PosAppRelease}>  $entries
     */
    private function notify(int $storeId, array $entries): bool
    {
        return DB::transaction(function () use ($storeId, $entries) {
            $lines = [];
            $links = [];
            foreach ($entries as ['device' => $device, 'minimum' => $minimum]) {
                // Claimed atomically: two runs (or a slow one overlapping the next) tell the owner once per till
                $claimed = Device::query()->whereKey($device->id)
                    ->where(fn ($query) => $query->whereNull('app_outdated_notified_release_id')->orWhere('app_outdated_notified_release_id', '!=', $minimum->id))
                    ->update(['app_outdated_notified_release_id' => $minimum->id]);
                if ($claimed === 0) {
                    continue;
                }
                $lines[] = '• ' . $device->name . ($device->branch ? ' (' . $device->branch->name . ')' : '')
                    . ': ' . $device->app_version . ' → ' . $minimum->label();
                $latest = $this->releases->rows($minimum->app, $minimum->platform)['latest'] ?? null;
                $links[(string) ($latest ?? $minimum)->file_url] = true;
            }
            if ($lines === []) {
                return false;
            }

            // Handed over inside the claim's transaction: a failed send throws and rolls the marks back (never marked
            // as told while nobody was told)
            $this->alerts->deliver($storeId, "⚠️ Kassa ilovasi eskirgan\n"
                . "Quyidagi qurilmalardagi ilova talab qilinadigan eng kam versiyadan past:\n" . implode("\n", $lines)
                . "\nIsh to'xtatilmaydi, lekin ilovani yangilang (navbatdagi amallar yuborilgandan keyin):\n" . implode("\n", array_keys($links)));

            return true;
        });
    }
}
