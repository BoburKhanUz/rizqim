<?php

namespace App\Services\Payment;

use App\Models\PaymentProvider;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Owner decision 1 (payment decisions): a store may not save the platform's own provider credentials as its own. A
 * store whose own-account Payme key, Click service id or Paylov login is RIZQIM's would have its customers' money paid
 * into RIZQIM's merchant account, and its POS QR could never be routed to it (a call signed with the platform's key is
 * never a store's: PaymePosQrWebhook, docs/OPERATIONS.md 29-bo'lim).
 *
 * The rule lives here and runs from the Store model's `saving` hook (Store::booted), so every Eloquent write of
 * stores.payment_settings is checked whoever makes it: POST /owner/settings, an admin tool, provisioning, a seeder or
 * an import. A credential is refused when the value SAVED NOW (a new or changed one, after the model's merge: a blank
 * or masked secret keeps the stored one and is not a new value) equals the platform's same credential — the value in
 * Tizim → To'lov tizimlari (payment_providers.credentials), else config/services.php (.env), exactly as checkout and
 * the webhooks resolve it (PaymentProvider::platformCredential). Compared trimmed, in constant time (hash_equals);
 * neither value is ever logged, echoed or put in an exception message.
 *
 * A value a store already holds is never changed and never blocks a save that does not submit it again (an owner
 * toggling a switch, a ledger write): `payments:own-credentials-check` reports such stores (count and ids only).
 */
final class PlatformCredentialGuard
{
    /**
     * The own-account credentials a store can hold, per provider (Store::OWN_MERCHANT_CREDENTIALS, plus Click's Merchant
     * API user, which ClickMerchantApi reads from the store's settings), each with the platform's credentials it must not
     * equal. Uzum's store merchant id is its platform terminal id's counterpart (the platform account names it so).
     *
     * @var array<string, array<string, list<string>>>
     */
    public const FIELDS = [
        'payme' => ['merchant_id' => ['merchant_id'], 'secret_key' => ['secret_key']],
        'click' => ['service_id' => ['service_id'], 'merchant_id' => ['merchant_id'], 'secret_key' => ['secret_key'], 'merchant_user_id' => ['merchant_user_id']],
        'uzum' => ['merchant_id' => ['merchant_id', 'terminal_id'], 'secret_key' => ['secret_key']],
        'paynet' => ['merchant_id' => ['merchant_id'], 'secret_key' => ['secret_key']],
        'paylov' => [
            'merchant_id' => ['merchant_id'],
            'consumer_key' => ['consumer_key'],
            'consumer_secret' => ['consumer_secret'],
            'username' => ['username'],
            'password' => ['password'],
            'callback_login' => ['callback_login'],
            'callback_password' => ['callback_password'],
        ],
    ];

    /**
     * The settings fields ("payment_settings.<provider>.<field>") whose value in $after is new (not the same in $before)
     * and equals the platform's credential. Both arrays are payment_settings in section form.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public static function violations(array $before, array $after): array
    {
        $fields = [];
        $platform = null;
        foreach (self::FIELDS as $provider => $credentials) {
            $section = is_array($after[$provider] ?? null) ? $after[$provider] : [];
            $was = is_array($before[$provider] ?? null) ? $before[$provider] : [];
            foreach ($credentials as $field => $platformKeys) {
                $value = self::value($section[$field] ?? null);
                $old = self::value($was[$field] ?? null);
                if ($value === null || ($old !== null && hash_equals($old, $value))) {
                    continue; // nothing saved, or the value the store already had (not judged here)
                }
                $platform ??= self::platform();
                foreach ($platformKeys as $key) {
                    $theirs = $platform[$provider][$key] ?? null;
                    if ($theirs !== null && hash_equals($theirs, $value)) {
                        $fields[] = "payment_settings.{$provider}.{$field}";
                        break;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     *
     * @throws PlatformCredentialException
     */
    public static function assertNone(array $before, array $after): void
    {
        $fields = self::violations($before, $after);
        if ($fields !== []) {
            throw new PlatformCredentialException($fields);
        }
    }

    /**
     * Every store that holds a platform credential as its own now (written before the rule, or before the platform's
     * value changed): store id => the fields ("payme.secret_key"...), never a value. A store whose settings this install
     * cannot decrypt is counted in $unreadable.
     *
     * @param-out int $unreadable
     * @return array<int, list<string>>
     */
    public static function existing(?int &$unreadable = null): array
    {
        $unreadable = 0;
        $found = [];
        $platform = self::platform();
        DB::table('stores')->select(['id', 'payment_settings'])->whereNotNull('payment_settings')->orderBy('id')
            ->chunkById(200, function ($stores) use (&$found, &$unreadable, $platform) {
                foreach ($stores as $store) {
                    $settings = self::decode($store->payment_settings);
                    if ($settings === null) {
                        $unreadable++;

                        continue;
                    }
                    $fields = [];
                    foreach (self::FIELDS as $provider => $credentials) {
                        $section = is_array($settings[$provider] ?? null) ? $settings[$provider] : [];
                        foreach ($credentials as $field => $platformKeys) {
                            $value = self::value($section[$field] ?? null);
                            if ($value === null) {
                                continue;
                            }
                            foreach ($platformKeys as $key) {
                                $theirs = $platform[$provider][$key] ?? null;
                                if ($theirs !== null && hash_equals($theirs, $value)) {
                                    $fields[] = "{$provider}.{$field}";
                                    break;
                                }
                            }
                        }
                    }
                    if ($fields !== []) {
                        $found[(int) $store->id] = $fields;
                    }
                }
            });

        return $found;
    }

    /**
     * The platform's credentials per provider of FIELDS: each provider row read once, a blank value falling back to
     * config (PaymentProvider::platformCredential's resolution).
     *
     * @return array<string, array<string, string>>
     */
    private static function platform(): array
    {
        $rows = PaymentProvider::query()->whereIn('slug', array_keys(self::FIELDS))->get(['slug', 'credentials'])->keyBy('slug');
        $platform = [];
        foreach (self::FIELDS as $provider => $credentials) {
            $stored = $rows->get($provider)?->credentials;
            foreach (array_unique(array_merge(...array_values($credentials))) as $key) {
                $value = is_array($stored) ? ($stored[$key] ?? null) : null;
                if (blank($value)) {
                    $value = config("services.{$provider}.{$key}");
                }
                if (($value = self::value($value)) !== null) {
                    $platform[$provider][$key] = $value;
                }
            }
        }

        return $platform;
    }

    private static function value(mixed $value): ?string
    {
        if (!is_scalar($value) || is_bool($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * stores.payment_settings as stored (encrypted, or plain JSON on an install that never encrypted it), or null.
     *
     * @return array<string, mixed>|null
     */
    private static function decode(mixed $stored): ?array
    {
        if (!is_string($stored) || $stored === '') {
            return null;
        }
        try {
            $plain = \Illuminate\Support\Facades\Crypt::decryptString($stored);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            $plain = $stored;
        }
        $settings = json_decode($plain, true);

        return is_array($settings) ? Store::normalizePaymentSettings($settings) : null;
    }
}
