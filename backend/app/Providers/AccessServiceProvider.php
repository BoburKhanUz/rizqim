<?php

namespace App\Providers;

use App\Auth\DeviceTokenGuard;
use App\Enums\Permission;
use App\Models\Device;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\RequestGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * P1-12 access layer: the `device` guard for paired terminals, device-bound token checks, the permission Gate, the
 * audit observers and the rate limits of the auth/device endpoints (routes/api_access.php).
 */
class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    public function boot(): void
    {
        $this->configureGuards();
        $this->configureGate();
        $this->configureAudit();
        $this->configureRateLimiting();

        $this->app['router']->aliasMiddleware('permission', \App\Http\Middleware\EnsureUserHasPermission::class);
        $this->app['router']->aliasMiddleware('not-staff-session', \App\Http\Middleware\RejectStaffSession::class);
    }

    protected function configureGuards(): void
    {
        // auth:device — a terminal's own token; never the global SANCTUM_TOKEN_EXPIRATION, never a session user
        Auth::resolved(function ($auth) {
            $auth->extend('device-token', function ($app, $name, array $config) use ($auth) {
                $guard = new RequestGuard(
                    new DeviceTokenGuard($auth, null, $config['provider'] ?? null),
                    $app['request'],
                    $auth->createUserProvider($config['provider'] ?? null),
                );
                $app->refresh('request', $guard, 'setRequest');

                return $guard;
            });
        });

        // A token bound to a device (staff PIN session, refreshable app session, terminal) dies with the device
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid) {
            $deviceId = $accessToken->getAttribute('device_id');

            return $isValid && ($deviceId === null || Device::whereKey($deviceId)->whereNull('revoked_at')->exists());
        });
    }

    protected function configureGate(): void
    {
        // `$user->can('orders.cancel', $order)`, `@can`, the `can:` middleware: abilities named like a Permission go to
        // PermissionService; everything else (policies) is untouched
        Gate::before(function ($user, string $ability, array $arguments = []) {
            $permission = Permission::tryFrom($ability);
            if (!$permission || !$user instanceof User) {
                return null;
            }

            return app(PermissionService::class)->allows($user, $permission, $arguments[0] ?? null);
        });
    }

    protected function configureAudit(): void
    {
        // Role changes from anywhere: the API, the store panel, the invite flow
        User::updated(function (User $user) {
            if (!$user->wasChanged('role') && !($user->wasChanged('branch_id') && $user->role->isStoreStaff())) {
                return;
            }

            $original = $user->getOriginal();
            app(AuditLogger::class)->record(
                $user->wasChanged('role') ? 'staff.role_changed' : 'staff.branch_changed',
                $user,
                ['role' => $original['role'] ?? null, 'branch_id' => $original['branch_id'] ?? null],
                ['role' => $user->role->value, 'branch_id' => $user->branch_id],
                ['staff_name' => $user->name],
            );
        });

        // Price changes of the menu (variants carry the price)
        ProductVariant::updated(function (ProductVariant $variant) {
            if (!$variant->wasChanged('price')) {
                return;
            }

            $variant->loadMissing('product');
            app(AuditLogger::class)->record(
                'product.price_changed',
                $variant,
                ['price' => $variant->getOriginal('price')],
                ['price' => $variant->price],
                ['product' => $variant->product?->name, 'variant' => $variant->name],
                ['store_id' => $variant->product?->store_id, 'branch_id' => null],
            );
        });
    }

    protected function configureRateLimiting(): void
    {
        $byUser = fn (Request $request): string => $request->user() ? 'user:' . $request->user()->getAuthIdentifier() : 'ip:' . $request->ip();

        // POST /auth/refresh: no user yet (the access token has expired)
        RateLimiter::for('auth-refresh', fn (Request $request) => Limit::perMinute(30)->by('ip:' . $request->ip()));

        // POST /devices/pair: per address and per subnet (/24, IPv6 /64), never one platform-wide bucket that anybody
        // could fill to stop every terminal pairing. An unusual total is logged (TerminalController::watchPairingVolume)
        RateLimiter::for('device-pair', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:' . $request->ip()),
            Limit::perMinute((int) config('auth.pairing.subnet_attempts_per_minute', 30))
                ->by('net:' . IpUtils::anonymize((string) $request->ip(), 1, 8)),
        ]);

        RateLimiter::for('pairing-code', fn (Request $request) => [
            Limit::perMinute(5)->by('minute:' . $byUser($request)),
            Limit::perHour(30)->by('hour:' . $byUser($request)),
        ]);

        // A terminal's own calls (device guard): by device
        RateLimiter::for('terminal', fn (Request $request) => Limit::perMinute(120)->by(
            ($device = $request->user('device')) instanceof Device ? 'device:' . $device->id : 'ip:' . $request->ip()
        ));

        // PIN sign-ins per terminal (the per person lockout is StaffPinService's)
        RateLimiter::for('staff-session', fn (Request $request) => [
            Limit::perMinute(10)->by(($device = $request->user('device')) instanceof Device ? 'device:' . $device->id : 'ip:' . $request->ip()),
            Limit::perMinute(60)->by('ip:' . $request->ip()),
        ]);

        RateLimiter::for('devices', fn (Request $request) => Limit::perMinute(60)->by($byUser($request)));

        // PINs, roles, permission overrides
        RateLimiter::for('staff-admin', fn (Request $request) => Limit::perMinute(30)->by($byUser($request)));

        RateLimiter::for('audit-logs', fn (Request $request) => Limit::perMinute(60)->by($byUser($request)));

        RateLimiter::for('auth-logout', fn (Request $request) => Limit::perMinute(30)->by($byUser($request)));
    }
}
