<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Store;
use App\Services\Payment\PaymentProviderSwitch;
use App\Services\Payment\PaymentProviderUnconfiguredException;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

class PaymentLinkService
{
    /** The platform credentials a checkout link names (the secret key only signs the webhooks) */
    public const LINK_CREDENTIALS = [
        'click' => ['service_id', 'merchant_id'],
        'payme' => ['merchant_id'],
    ];

    public function __construct(
        private readonly PaymentProviderSwitch $providers,
    ) {}

    /**
     * The platform's link credentials of $provider that are not set (PaymentProvider::platformCredential: the
     * control center's value, else .env), as names.
     *
     * @return list<string>
     */
    public static function missingPlatformIds(string $provider): array
    {
        return array_values(array_filter(
            self::LINK_CREDENTIALS[$provider] ?? [],
            fn (string $key) => PaymentProvider::platformCredential($provider, $key) === null,
        ));
    }

    /**
     * Go-live review M2: refuse (422 payment_provider_unconfigured, before any row) a payment through the platform's
     * own merchant account while its ids are missing: the link would open nothing. Logged for the team, no values.
     *
     * @param  array<string, mixed>  $context
     */
    public static function assertPlatformConfigured(string $provider, string $field = 'payment_method', array $context = []): void
    {
        $missing = self::missingPlatformIds($provider);
        if ($missing === []) {
            return;
        }

        Log::error("Payment provider {$provider} is not configured: the platform merchant ids are missing, payment refused", [
            'provider' => $provider,
            'missing' => $missing,
        ] + $context);

        throw new PaymentProviderUnconfiguredException($provider, $missing, $field);
    }

    /**
     * Checkout: an online order the platform would collect (CreateOrderAction's payment_owner PLATFORM rule) needs the
     * platform ids; a store collecting into its own account has its own (Store::collectsOwnPayments).
     */
    public static function assertCheckoutConfigured(Store $store, string $method): void
    {
        if (!isset(self::LINK_CREDENTIALS[$method]) || $store->collectsOwnPayments($method)) {
            return;
        }

        self::assertPlatformConfigured($method, 'payment_method', ['store_id' => $store->id, 'flow' => 'order']);
    }

    /**
     * The Click/Payme checkout URL for a new online order: null for other payment methods, '' when the store has
     * switched the provider off.
     */
    public function forOrder(Order $order): ?string
    {
        if (!in_array($order->payment_method, ['click', 'payme'])) {
            return null;
        }

        // Exact decimal money (brick/math), never a float
        $amount = Money::of($order->total_price);
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

            return $this->providers->clickUrl($serviceId, $merchantId, $amount, (string) $order->uuid, $returnUrl);
        }

        $paymeConfig = is_array($settings['payme'] ?? null) ? $settings['payme'] : [];
        if (isset($paymeConfig['enabled']) && !$paymeConfig['enabled']) {
            return ''; // Disabled by store merchant
        }

        $merchantId = $useStore ? $paymeConfig['merchant_id'] : PaymentProvider::platformCredential('payme', 'merchant_id');
        // Payme expects amount in tiyin (1 UZS = 100 tiyin); P4-32: the platform account's mode picks the sandbox host
        $tiyinAmount = $amount->toTiyin();

        return $this->providers->paymeUrl($merchantId, $tiyinAmount, (string) $order->uuid, $returnUrl, !$useStore);
    }
}
