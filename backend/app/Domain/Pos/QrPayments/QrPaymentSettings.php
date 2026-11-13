<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\PosQrSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * P6-41: a branch's QR payment settings (pos_qr_settings) read with the defaults — every provider offered, Click
 * first, a QR valid for 5 minutes (1–30). Changing them is the owner's (settings.manage, the `qr_payments` module,
 * PUT /owner/pos/qr-payments) and audited (settings.qr_payments_changed). The till offers a provider when it is in
 * `providers` AND the store's merchant account (or UzQR gateway) for it is set up (QrProviders::present).
 */
final class QrPaymentSettings
{
    public const VIEW = 'array{branch_id: int, enabled: bool, providers: list<string>, default_provider: string|null, expiry_minutes: int, updated_at: string|null}';

    public function __construct(
        private readonly PosModules $modules,
        private readonly QrProviders $providers,
        private readonly AuditLogger $audit,
    ) {}

    public static function minExpiry(): int
    {
        return (int) config('qr_payments.expiry.min', 1);
    }

    public static function maxExpiry(): int
    {
        return (int) config('qr_payments.expiry.max', 30);
    }

    public function row(Branch $branch): ?PosQrSetting
    {
        return PosQrSetting::where('branch_id', $branch->id)->first();
    }

    /**
     * @return array{branch_id: int, enabled: bool, providers: list<string>, default_provider: string|null, expiry_minutes: int, updated_at: string|null}
     */
    public function present(Branch $branch, ?PosQrSetting $row = null): array
    {
        $row ??= $this->row($branch);
        $providers = $this->providerList($row);
        $default = $row?->default_provider;

        $expiry = $row?->expiry_minutes;

        return [
            'branch_id' => (int) $branch->id,
            'enabled' => $this->modules->enabled($branch, PosModule::QR_PAYMENTS),
            'providers' => $providers,
            'default_provider' => is_string($default) && in_array($default, $providers, true) ? $default : ($providers[0] ?? null),
            'expiry_minutes' => is_int($expiry) && $expiry >= self::minExpiry() && $expiry <= self::maxExpiry() ? $expiry : (int) config('qr_payments.expiry.default', 5),
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * What the till may offer now: the enabled providers whose merchant account (or gateway) is set up, the default
     * first when it is one of them.
     *
     * @return list<string>
     */
    public function available(Branch $branch, Store $store): array
    {
        $settings = $this->present($branch);
        $configured = array_column(array_filter($this->providers->present($store), fn (array $row) => $row['configured']), 'provider');
        $available = array_values(array_intersect($settings['providers'], $configured));
        usort($available, fn (string $a, string $b) => ($b === $settings['default_provider']) <=> ($a === $settings['default_provider']));

        return $available;
    }

    /**
     * Saves the settings (only the keys given change) and audits the change.
     *
     * @param  array{providers?: list<string>, default_provider?: string|null, expiry_minutes?: int}  $data
     */
    public function update(Branch $branch, array $data, User $actor): PosQrSetting
    {
        $row = $this->row($branch) ?? new PosQrSetting(['store_id' => $branch->store_id, 'branch_id' => $branch->id]);
        $before = $row->exists ? $this->present($branch, $row) : [];

        if (array_key_exists('providers', $data)) {
            $row->providers = array_values(array_intersect(QrProvider::values(), $data['providers']));
        } else {
            $row->providers ??= $this->providerList($row);
        }
        if (array_key_exists('default_provider', $data)) {
            $row->default_provider = $data['default_provider'];
        }
        if (isset($data['expiry_minutes'])) {
            $row->expiry_minutes = (int) $data['expiry_minutes'];
        }
        $row->expiry_minutes ??= (int) config('qr_payments.expiry.default', 5);
        if ($row->default_provider !== null && !in_array($row->default_provider, (array) $row->providers, true)) {
            $row->default_provider = ((array) $row->providers)[0] ?? null;
        }
        $row->updated_by = $actor->id;
        $row->save();

        $this->audit->record('settings.qr_payments_changed', $row, $before, $this->present($branch, $row), [], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        return $row;
    }

    /**
     * @return list<string>
     */
    private function providerList(?PosQrSetting $row): array
    {
        if ($row === null || !is_array($row->providers)) {
            return QrProvider::values();
        }

        return array_values(array_intersect(QrProvider::values(), $row->providers));
    }
}
