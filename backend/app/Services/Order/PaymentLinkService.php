<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\PaymentProvider;

class PaymentLinkService
{
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
        $returnUrl = url('/admin'); // Redirect back to store after payment

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

            return "https://my.click.uz/services/pay" .
                   "?service_id={$serviceId}" .
                   "&merchant_id={$merchantId}" .
                   "&amount={$amount}" .
                   "&transaction_param={$order->uuid}" .
                   "&return_url=" . urlencode($returnUrl);
        }

        $paymeConfig = is_array($settings['payme'] ?? null) ? $settings['payme'] : [];
        if (isset($paymeConfig['enabled']) && !$paymeConfig['enabled']) {
            return ''; // Disabled by store merchant
        }

        $merchantId = $useStore ? $paymeConfig['merchant_id'] : PaymentProvider::platformCredential('payme', 'merchant_id');
        // Payme expects amount in tiyin (1 UZS = 100 tiyin)
        $tiyinAmount = (int) round($amount * 100);
        $params = "m={$merchantId};ac.order_id={$order->uuid};a={$tiyinAmount};c=" . urlencode($returnUrl);

        return "https://checkout.paycom.uz/" . base64_encode($params);
    }
}
