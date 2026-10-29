<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentProvider;

/**
 * Review C#2 (wave 3): the merchant account an online order is paid into is fixed when it is placed — `payment_owner`
 * (CreateOrderAction): STORE, the store's own merchant account (its keys in payment_settings.<provider>), or PLATFORM,
 * the platform's (PaymentProvider::platformCredential). Every later call about that money (Click Prepare/Complete and
 * its fiscal data, every Payme method including CancelTransaction) uses that account's keys, never the store's CURRENT
 * `payment_account_type`: an account change (Ovqatxonalar → To'lov hisobi) must not orphan a payment on its way or a
 * paid one that may still be refunded. A top-up (no order) is the platform's.
 */
final class OrderMerchantAccount
{
    /**
     * Whether the order's money went to the store's own merchant account.
     */
    public static function isStore(?Order $order): bool
    {
        return $order !== null && $order->payment_owner !== 'PLATFORM';
    }

    /**
     * A credential (secret_key, service_id, merchant_id, merchant_user_id) of the account the order was paid into; null
     * when that account has none. A STORE-owned order never falls back to the platform's keys, nor a PLATFORM one to
     * the store's.
     */
    public static function credential(string $provider, ?Order $order, string $key): ?string
    {
        if (!self::isStore($order)) {
            return PaymentProvider::platformCredential($provider, $key);
        }

        $config = $order->store?->payment_settings[$provider] ?? null;
        $value = is_array($config) ? ($config[$key] ?? null) : null;

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public static function secretKey(string $provider, ?Order $order): ?string
    {
        return self::credential($provider, $order, 'secret_key');
    }
}
