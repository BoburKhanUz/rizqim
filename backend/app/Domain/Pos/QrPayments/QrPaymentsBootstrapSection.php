<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Store;
use App\Models\User;

/**
 * P6-41: the `qr_payments` section of GET /pos/bootstrap: whether the payment screen offers a dynamic QR (the
 * branch's `qr_payments` module), the providers it may offer now (enabled by the owner AND the store's merchant
 * account or UzQR gateway set up; the default first) and how long a QR stays valid. Offline the till offers the
 * static QR confirmed by hand instead (a dynamic QR needs the server).
 */
final class QrPaymentsBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = 'array{enabled: bool, providers: list<string>, default_provider: string|null, expiry_minutes: int}';

    public function __construct(private readonly QrPaymentSettings $settings) {}

    /**
     * @return array{qr_payments: array{enabled: bool, providers: list<string>, default_provider: string|null, expiry_minutes: int}}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        $view = $this->settings->present($branch);
        $available = $view['enabled'] ? $this->settings->available($branch, Store::findOrFail((int) $branch->store_id)) : [];

        return ['qr_payments' => [
            'enabled' => $view['enabled'] && $available !== [],
            'providers' => $available,
            'default_provider' => $available[0] ?? null,
            'expiry_minutes' => $view['expiry_minutes'],
        ]];
    }
}
