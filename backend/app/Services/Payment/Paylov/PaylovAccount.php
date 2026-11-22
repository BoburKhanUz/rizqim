<?php

namespace App\Services\Payment\Paylov;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Services\Payment\OrderMerchantAccount;

/**
 * Wave P: one Paylov merchant account, the platform's or a store's own (payment_settings.paylov), as
 * OrderMerchantAccount picks it from the order's `payment_owner`: every call about an order's money (the webhook's
 * Basic Auth, the status read, the refund, the fiscal receipt) uses the account the order was paid into.
 *
 * Keys (developer.paylov.uz):
 *  - merchant_id: names the checkout link (merchants/creating-payment-link);
 *  - consumer_key, consumer_secret, username, password: the OAuth2 password grant that gives the API's bearer token
 *    (subscribe/authorization);
 *  - callback_login, callback_password: the Basic Auth Paylov sends with transaction.check / perform / cancel
 *    (merchants/paylov-payment-gateway "STEP 1", subscribe/payment/cancel-webhook). The gateway page calls them the
 *    "Merchant's API login / password"; whether Paylov means the API username/password themselves is not said, so they
 *    are their own keys (set them equal if Paylov says so).
 *
 * The values never leave this object in a log, an exception or an API answer.
 */
final class PaylovAccount
{
    public const KEYS = ['merchant_id', 'consumer_key', 'consumer_secret', 'username', 'password', 'callback_login', 'callback_password'];

    /**
     * @param  array<string, string|null>  $credentials
     */
    private function __construct(private readonly array $credentials, public readonly bool $isStore) {}

    public static function forOrder(?Order $order): self
    {
        if (!OrderMerchantAccount::isStore($order)) {
            // Review 2 (L2): the platform's provider row is read once for all seven keys (the webhook does this before
            // its Basic Auth check), with the same fallback to config as PaymentProvider::platformCredential
            $stored = PaymentProvider::where('slug', 'paylov')->first()?->credentials;
            $credentials = [];
            foreach (self::KEYS as $key) {
                $value = is_array($stored) ? ($stored[$key] ?? null) : null;
                if (blank($value)) {
                    $value = config("services.paylov.{$key}");
                }
                $credentials[$key] = filled($value) && is_scalar($value) ? trim((string) $value) : null;
            }

            return new self($credentials, false);
        }

        $credentials = [];
        foreach (self::KEYS as $key) {
            $credentials[$key] = OrderMerchantAccount::credential('paylov', $order, $key);
        }

        return new self($credentials, true);
    }

    public static function platform(): self
    {
        return self::forOrder(null);
    }

    public function get(string $key): ?string
    {
        return $this->credentials[$key] ?? null;
    }

    /**
     * The key names that are not set (never their values).
     *
     * @return list<string>
     */
    public function missing(): array
    {
        return array_values(array_filter(self::KEYS, fn (string $key) => $this->get($key) === null));
    }

    /**
     * Whether the API (token, status, refund, fiscal) can be called with this account.
     */
    public function hasApiCredentials(): bool
    {
        foreach (['consumer_key', 'consumer_secret', 'username', 'password'] as $key) {
            if ($this->get($key) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * The cache key of this account's access token: a hash, so no credential is readable in the cache key. Review 2
     * (M1): all four OAuth2 credentials are in it, so an account that shares another's consumer_key and username but
     * not its secret and password is never handed the other's token (it asks Paylov, which refuses it). Each part is
     * length-prefixed so no two different sets of values give the same string.
     */
    public function tokenCacheKey(string $baseUrl): string
    {
        $parts = [$baseUrl];
        foreach (['consumer_key', 'consumer_secret', 'username', 'password'] as $key) {
            $parts[] = (string) $this->get($key);
        }

        // Keyed with the app key: the cache never holds a plain hash a password could be guessed against
        return 'paylov:token:' . hash_hmac('sha256', implode('', array_map(fn (string $part) => strlen($part) . ':' . $part, $parts)), (string) config('app.key'));
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['isStore' => $this->isStore, 'missing' => $this->missing()];
    }
}
