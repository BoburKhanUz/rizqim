<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Services\Payment\PaymentProviderSwitch;

class PaymentLinkService
{
    public function __construct(
        private readonly PaymentProviderSwitch $providers,
    ) {}

    /**
     * The Click/Payme checkout URL for a new online order: null for other payment methods, '' when the store has
     * switched the provider off.
     */
    public function forOrder(Order $order): ?string
    {
        if (!in_array($order->payment_method, ['click', 'payme'])) {
            return null;
        }

        $amount = (float) $order->total_price;
        // P4-32: back to the Mini App (it used to be the old store panel, /admin)
        $returnUrl = PaymentProviderSwitch::orderReturnUrl();

        $store = $order->store;
        $settings = $store->payment_settings ?? [];

        // Store merchant credentials only for a STORE-owned order of a store that can collect this provider itself;
        // everything else is paid to the platform merchant whose key the webhooks verify.
        $useStore = $order->payment_owner !== 'PLATFORM' && (bool) $store?->collectsOwnPayments((string) $order->payment_method);

        if ($order->payment_method === 'click') {
            $clickConfig = is_array($settings['click'] ?? null) ? $settings['click'] : [];
            if (isset($clickConfig['enabled']) && !$clickConfig['enabled']) {
                return ''; // Disabled by store merchant
            }

            $serviceId = $useStore ? $clickConfig['service_id'] : PaymentProvider::platformCredential('click', 'service_id');
            $merchantId = $useStore ? $clickConfig['merchant_id'] : PaymentProvider::platformCredential('click', 'merchant_id');

            return $this->providers->clickUrl($serviceId, $merchantId, (string) $amount, (string) $order->uuid, $returnUrl);
        }

        $paymeConfig = is_array($settings['payme'] ?? null) ? $settings['payme'] : [];
        if (isset($paymeConfig['enabled']) && !$paymeConfig['enabled']) {
            return ''; // Disabled by store merchant
        }

        $merchantId = $useStore ? $paymeConfig['merchant_id'] : PaymentProvider::platformCredential('payme', 'merchant_id');
        // Payme expects amount in tiyin (1 UZS = 100 tiyin); P4-32: the platform account's mode picks the sandbox host
        $tiyinAmount = (int) round($amount * 100);

        return $this->providers->paymeUrl($merchantId, $tiyinAmount, (string) $order->uuid, $returnUrl, !$useStore);
    }
}
