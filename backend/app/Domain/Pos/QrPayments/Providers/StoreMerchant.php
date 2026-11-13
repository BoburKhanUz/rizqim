<?php

namespace App\Domain\Pos\QrPayments\Providers;

use App\Models\Store;

/**
 * P6-41: a POS QR is always STORE-owned: the money goes to the store's own Click/Payme merchant account
 * (payment_settings.<provider>, payment_account_type own_account), never the platform's, and its webhooks are signed
 * with the store's own key (OrderMerchantAccount's STORE rule).
 */
final class StoreMerchant
{
    /**
     * The store collects this provider into its own merchant account and has not switched it off.
     */
    public static function enabled(Store $store, string $provider): bool
    {
        $config = $store->payment_settings[$provider] ?? null;

        return $store->collectsOwnPayments($provider) && is_array($config) && ($config['enabled'] ?? true) !== false;
    }

    public static function credential(Store $store, string $provider, string $key): ?string
    {
        $config = $store->payment_settings[$provider] ?? null;
        $value = is_array($config) ? ($config[$key] ?? null) : null;

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public static function returnUrl(): string
    {
        $url = trim((string) config('qr_payments.return_url'));

        return $url !== '' ? $url : url('/');
    }
}
