<?php

namespace App\Domain\Fiscal\Gateways;

use App\Domain\Fiscal\FiscalSettings;
use App\Models\Store;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the driver a store's receipts go to (FiscalSettings::driver(): the configured one in fiscal mode, else
 * `null`) from config('fiscal.drivers').
 */
class FiscalGatewayManager
{
    public function __construct(private readonly Container $container) {}

    public function driver(string $name): FiscalGateway
    {
        $class = config("fiscal.drivers.{$name}");
        if (!is_string($class) || $class === '') {
            throw new InvalidArgumentException("Unknown fiscal driver [{$name}].");
        }

        $gateway = $this->container->make($class);
        if (!$gateway instanceof FiscalGateway) {
            throw new InvalidArgumentException("Fiscal driver [{$name}] does not implement FiscalGateway.");
        }

        return $gateway;
    }

    public function driverNameFor(Store $store): string
    {
        return FiscalSettings::of($store)->driver();
    }
}
