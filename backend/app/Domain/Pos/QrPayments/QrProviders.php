<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\QrPayments\Providers\ClickQrProvider;
use App\Domain\Pos\QrPayments\Providers\PaymeQrProvider;
use App\Domain\Pos\QrPayments\Providers\SandboxQrProvider;
use App\Domain\Pos\QrPayments\Providers\UzQrProvider;
use App\Domain\Pos\QrPayments\UzQr\UzQrGateway;
use App\Models\PosQrPayment;
use App\Models\Store;

/**
 * P6-41/P6-42: the driver of each QR provider for a store: Click and Payme through the store's own merchant account
 * (the sandbox on a demo stand, config qr_payments.sandbox), UzQR through the gateway of
 * stores.fiscal_settings.uzqr.driver (the sandbox gateway on a demo stand when none is chosen). A request keeps the
 * driver it was made with (pos_qr_payments.driver), so its refund goes where its money went.
 */
final class QrProviders
{
    public const VIEW = 'array{provider: string, label: string, configured: bool, driver: string}';

    public function for(Store $store, QrProvider $provider): QrPaymentProvider
    {
        if ($provider === QrProvider::UZQR) {
            return $this->uzqr($this->uzqrDriver($store));
        }

        if ((bool) config('qr_payments.sandbox')) {
            return new SandboxQrProvider($provider);
        }

        return $provider === QrProvider::CLICK ? app(ClickQrProvider::class) : app(PaymeQrProvider::class);
    }

    /**
     * The driver a request was made with.
     */
    public function of(PosQrPayment $qr): QrPaymentProvider
    {
        return match (true) {
            $qr->driver === 'sandbox' => new SandboxQrProvider($qr->provider),
            $qr->driver === 'click' => app(ClickQrProvider::class),
            $qr->driver === 'payme' => app(PaymeQrProvider::class),
            str_starts_with($qr->driver, 'uzqr:') => $this->uzqr(substr($qr->driver, 5)),
            default => throw new \LogicException("Unknown QR driver {$qr->driver}"),
        };
    }

    /**
     * The UzQR gateway a store chose (a known driver), the sandbox on a demo stand, else null.
     */
    public function uzqrDriver(Store $store): ?string
    {
        return \App\Domain\Fiscal\FiscalSettings::of($store)->uzqrDriver() ?? ((bool) config('qr_payments.sandbox') ? 'sandbox' : null);
    }

    /**
     * The UzQR gateways a store may pick (review P6W4 S3: the sandbox one only on a sandbox stand, or with "uzqr" in
     * qr_payments.simulate_drivers — never on a real-money stand, where its QR is unpayable).
     *
     * @return array<string, class-string<UzQrGateway>>
     */
    public static function uzqrDrivers(): array
    {
        $drivers = (array) config('qr_payments.uzqr.drivers', []);
        if (!self::sandboxed(QrProvider::UZQR)) {
            unset($drivers['sandbox']);
        }

        return $drivers;
    }

    /**
     * Review P6W4 S3: whether this provider's sandbox may be used here (config qr_payments.sandbox, or the provider in
     * qr_payments.simulate_drivers).
     */
    public static function sandboxed(QrProvider $provider): bool
    {
        return (bool) config('qr_payments.sandbox') || in_array($provider->value, (array) config('qr_payments.simulate_drivers', []), true);
    }

    /**
     * Whether POST /pos/qr/{uuid}/simulate-paid may confirm this request: simulation on, a sandbox driver, and the
     * sandbox allowed for its provider (not only a non-production APP_ENV).
     */
    public function simulates(PosQrPayment $qr): bool
    {
        try {
            return (bool) config('qr_payments.simulate') && self::sandboxed($qr->provider) && $this->of($qr)->simulated();
        } catch (\LogicException) {
            return false;
        }
    }

    /**
     * Each provider as the owner and the till see it: is the store's merchant account (or gateway) set up.
     *
     * @return list<array{provider: string, label: string, configured: bool, driver: string}>
     */
    public function present(Store $store): array
    {
        return array_map(function (QrProvider $provider) use ($store) {
            $driver = $this->for($store, $provider);

            return [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'configured' => $driver->configured($store),
                'driver' => $driver->driver(),
            ];
        }, QrProvider::cases());
    }

    private function uzqr(?string $name): UzQrProvider
    {
        $class = $name !== null ? (self::uzqrDrivers()[$name] ?? null) : null;

        return new UzQrProvider($class !== null ? $name : null, $class !== null ? app($class) : null);
    }
}
