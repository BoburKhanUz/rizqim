<?php

namespace App\Domain\Pos\Releases;

use App\Enums\DeviceApp;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\PosAppRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Wave M5 S5-a (docs/scale/M5_DESIGN.md section 6): what the release registry (pos_app_releases) says about a terminal.
 *
 * "Latest" = the highest non-withdrawn version of an app on a platform; "minimum" = the highest non-withdrawn row
 * with `is_minimum`. A terminal below the minimum is `update_available` until the row's `minimum_from` (14 days after
 * the declaration unless staff chose another day, owner answer 8) and `update_required` after it. NOTHING here stops
 * a sale: this class is read by GET /terminal (a notice), POST /devices/pair (pairing is setup) and the owner notice
 * (PosAppOutdatedNotice), never by a money, shift or sync route (tests/Feature/PosReleases/PosReleaseRouteInventoryTest).
 * An empty registry (for the app and platform) tells the terminal nothing: no `app_release`.
 */
class PosAppReleases
{
    /** The public manifest is kept this long (and dropped at once when staff change the registry). */
    public const MANIFEST_TTL = 300;

    public const MANIFEST_CACHE_KEY = 'pos:releases:manifest';

    /** Days from declaring a minimum to the red notice (owner answer 8). */
    public static function graceDays(): int
    {
        return max(0, (int) config('pos.releases.grace_days', 14));
    }

    /**
     * The app a terminal runs: what it recorded (or, for an old record, what the request says), else the default app
     * of its terminal type.
     */
    public function appOf(Device $device, ?string $claimed = null): ?DeviceApp
    {
        $recorded = $device->app;
        $app = $recorded instanceof DeviceApp ? $recorded : DeviceApp::tryFrom((string) $recorded);
        if ($app === null) {
            $claimed = DeviceApp::tryFrom((string) $claimed);
            $app = $claimed !== null && $claimed->deviceType() === $device->type ? $claimed : null;
        }

        return $app ?? match ($device->type) {
            DeviceType::POS => DeviceApp::CASHIER,
            DeviceType::KDS => DeviceApp::KDS,
            DeviceType::DISPLAY => DeviceApp::CUSTOMER_DISPLAY,
            default => null,
        };
    }

    public static function platformOf(?string $platform): ?string
    {
        $platform = strtolower(trim((string) $platform));

        return in_array($platform, PosAppRelease::PLATFORMS, true) ? $platform : null;
    }

    /**
     * @return array{latest: PosAppRelease|null, minimum: PosAppRelease|null}
     */
    public function rows(DeviceApp|string $app, string $platform): array
    {
        $app = $app instanceof DeviceApp ? $app->value : $app;
        $releases = PosAppRelease::query()->active()->where('app', $app)->where('platform', $platform)->get();

        $latest = null;
        $minimum = null;
        foreach ($releases as $release) {
            if ($latest === null || $this->cmp($release, $latest) > 0) {
                $latest = $release;
            }
            if ($release->is_minimum && ($minimum === null || $this->cmp($release, $minimum) > 0)) {
                $minimum = $release;
            }
        }

        return ['latest' => $latest, 'minimum' => $minimum];
    }

    /**
     * The `app_release` of GET /terminal: null when the registry knows nothing for this app and platform (or the till
     * did not say its platform): the app then shows no notice.
     *
     * Review 3 M2: `$reported` is the version the request itself carries (X-Rizqim-App-Version). It wins over the
     * stored one, which the guard refreshes at most once a minute: an app updated within that minute must not be told
     * it is still the old build. Unreadable or missing: the stored version, as before.
     *
     * @return array{current: string|null, latest: string, minimum: string|null, state: string, url: string, notes: string|null, minimum_from: string|null}|null
     */
    public function forTerminal(Device $device, ?string $claimedApp = null, ?Carbon $now = null, ?string $reported = null): ?array
    {
        $app = $this->appOf($device, $claimedApp);
        $platform = self::platformOf($device->platform);
        if ($app === null || $platform === null) {
            return null;
        }

        ['latest' => $latest, 'minimum' => $minimum] = $this->rows($app, $platform);
        if ($latest === null) {
            return null;
        }

        $current = AppVersion::parse($reported) ?? AppVersion::parse($device->app_version);

        return [
            'current' => $current === null ? null : $current['version'] . '+' . $current['build'],
            'latest' => $latest->label(),
            'minimum' => $minimum?->label(),
            'state' => $this->state($current, $latest, $minimum, $now ?? now())->value,
            'url' => (string) $latest->file_url,
            'notes' => $latest->notes,
            'minimum_from' => $minimum?->minimum_from?->toIso8601String(),
        ];
    }

    /**
     * @param  array{version: string, build: int}|null  $current  null: the till never reported a version, nothing to say
     */
    public function state(?array $current, PosAppRelease $latest, ?PosAppRelease $minimum, Carbon $now): AppReleaseState
    {
        if ($current === null) {
            return AppReleaseState::OK;
        }
        if ($minimum !== null && AppVersion::compare($current, $this->parts($minimum)) < 0) {
            return $minimum->minimum_from === null || $minimum->minimum_from->lte($now)
                ? AppReleaseState::UPDATE_REQUIRED
                : AppReleaseState::UPDATE_AVAILABLE;
        }

        return AppVersion::compare($current, $this->parts($latest)) < 0 ? AppReleaseState::UPDATE_AVAILABLE : AppReleaseState::OK;
    }

    /**
     * Pairing is setup, so an old build is refused there (426 app_outdated), before the code is spent. Only when the
     * request names its app, platform and a version we can read, and the registry has a minimum for them.
     *
     * Review 3 M3: only once the minimum applies (the state GET /terminal would give is update_required, i.e. past
     * `minimum_from`): inside the grace period a cut-off till pairs again. And NEVER when the till says it holds
     * queued operations (`$pendingOperations` > 0, its orphans included): those sales and payments must reach the
     * server; the red notice shows after pairing instead.
     *
     * @return array{reason: string, current: string, minimum: string, url: string}|null
     */
    public function pairingRefusal(?string $app, ?string $platform, ?string $version, int $pendingOperations = 0, ?Carbon $now = null): ?array
    {
        $app = DeviceApp::tryFrom((string) $app);
        $platform = self::platformOf($platform);
        $current = AppVersion::parse($version);
        if ($pendingOperations > 0 || $app === null || $platform === null || $current === null) {
            return null;
        }

        ['latest' => $latest, 'minimum' => $minimum] = $this->rows($app, $platform);
        if ($minimum === null || $this->state($current, $latest ?? $minimum, $minimum, $now ?? now()) !== AppReleaseState::UPDATE_REQUIRED) {
            return null;
        }

        return [
            'reason' => 'app_outdated',
            'current' => $current['version'] . '+' . $current['build'],
            'minimum' => $minimum->label(),
            'url' => (string) ($latest ?? $minimum)->file_url,
        ];
    }

    /**
     * The public manifest (GET /pos/releases): every non-withdrawn release, no internal ids, cached 5 minutes.
     *
     * @return list<array{app: string, platform: string, version: string, build: int, file_url: string, sha256: string, size_bytes: int, notes: string|null, released_at: string, is_minimum: bool, minimum_from: string|null, is_latest: bool}>
     */
    public function manifest(): array
    {
        return Cache::remember(self::MANIFEST_CACHE_KEY, self::MANIFEST_TTL, function () {
            $releases = PosAppRelease::query()->active()->orderBy('app')->orderBy('platform')->get()
                ->sort(fn (PosAppRelease $a, PosAppRelease $b) => [$a->app, $a->platform] <=> [$b->app, $b->platform] ?: $this->cmp($b, $a))
                ->values();

            $latest = [];
            foreach ($releases as $release) {
                $latest[$release->app . '/' . $release->platform] ??= $release->id;
            }

            return $releases->map(fn (PosAppRelease $release) => [
                'app' => (string) $release->app,
                'platform' => (string) $release->platform,
                'version' => (string) $release->version,
                'build' => (int) $release->build,
                'file_url' => (string) $release->file_url,
                'sha256' => (string) $release->sha256,
                'size_bytes' => (int) $release->size_bytes,
                'notes' => $release->notes,
                'released_at' => $release->released_at->toIso8601String(),
                'is_minimum' => (bool) $release->is_minimum,
                'minimum_from' => $release->minimum_from?->toIso8601String(),
                'is_latest' => $latest[$release->app . '/' . $release->platform] === $release->id,
            ])->all();
        });
    }

    /** Staff changed the registry: the next manifest read is fresh. */
    public static function forget(): void
    {
        Cache::forget(self::MANIFEST_CACHE_KEY);
    }

    /**
     * The latest version per app and platform (what PosDevices compares a device with), "app/platform" => "1.4.0+7".
     *
     * @return array<string, PosAppRelease>
     */
    public function latestRows(): array
    {
        $latest = [];
        foreach (PosAppRelease::query()->active()->get() as $release) {
            $key = $release->app . '/' . $release->platform;
            if (!isset($latest[$key]) || $this->cmp($release, $latest[$key]) > 0) {
                $latest[$key] = $release;
            }
        }

        return $latest;
    }

    private function cmp(PosAppRelease $a, PosAppRelease $b): int
    {
        return AppVersion::compare($this->parts($a), $this->parts($b));
    }

    /**
     * @return array{version: string, build: int}
     */
    private function parts(PosAppRelease $release): array
    {
        return ['version' => (string) $release->version, 'build' => (int) $release->build];
    }
}
