<?php

namespace App\Services\Payment;

use App\Models\PaymentProvider;
use App\Models\Store;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owner decision 1 (payment decisions): a store may not save the platform's own provider credentials as its own. A
 * store whose own-account Payme key, Click service id or Paylov merchant id is RIZQIM's would have its customers' money
 * paid into RIZQIM's merchant account, and its POS QR could never be routed to it (a call signed with the platform's key
 * is never a store's: PaymePosQrWebhook, docs/OPERATIONS.md 29-bo'lim).
 *
 * The rule lives here and runs from the Store model's `saving` hook (Store::booted), so every Eloquent write of
 * stores.payment_settings is checked whoever makes it: POST /owner/settings, an admin tool, provisioning, a seeder or
 * an import. A credential is refused when the value SAVED NOW (a new or changed one, after the model's merge: a blank
 * or masked secret keeps the stored one and is not a new value) equals the platform's same credential — the value in
 * Tizim → To'lov tizimlari (payment_providers.credentials), else config/services.php (.env), exactly as checkout and
 * the webhooks resolve it (PaymentProvider::platformCredential). Neither value is ever logged, echoed or put in an
 * exception message; a refusal is logged with the store id and the field names only.
 *
 * Review: compared in constant time (hash_equals) on a canonical form. An identifier (IDENTIFIERS) loses every Unicode
 * space, zero-width and control character, is compared case-insensitively, and an all-digit one without its leading
 * zeros (Click's numeric ids), so the platform's id in another case or padded with an invisible character is still the
 * platform's. A secret is compared exactly, only trimmed. The low-entropy logins (Paylov `username` and
 * `callback_login`, Click's `merchant_user_id`) are never grounds for a refusal by themselves: a refusal naming one
 * would let an owner test guesses of a non-public value (POST /owner/settings also limits refusals per store).
 *
 * A provider row this install cannot decrypt (APP_KEY rotated without APP_PREVIOUS_KEYS) is logged (its slug only) and
 * read as empty: config is used for it, and `payments:own-credentials-check` reports it.
 *
 * A value a store already holds is never changed and never blocks a save that does not submit it again (an owner
 * toggling a switch, a ledger write): `payments:own-credentials-check` reports such stores (count and ids only).
 */
final class PlatformCredentialGuard
{
    /**
     * The own-account credentials judged, per provider (Store::OWN_MERCHANT_CREDENTIALS without the low-entropy logins),
     * each with the platform's credentials it must not equal. Uzum's store merchant id is its platform terminal id's
     * counterpart (the platform account names it so).
     *
     * @var array<string, array<string, list<string>>>
     */
    public const FIELDS = [
        'payme' => ['merchant_id' => ['merchant_id'], 'secret_key' => ['secret_key']],
        'click' => ['service_id' => ['service_id'], 'merchant_id' => ['merchant_id'], 'secret_key' => ['secret_key']],
        'uzum' => ['merchant_id' => ['merchant_id', 'terminal_id'], 'secret_key' => ['secret_key']],
        'paynet' => ['merchant_id' => ['merchant_id'], 'secret_key' => ['secret_key']],
        'paylov' => [
            'merchant_id' => ['merchant_id'],
            'consumer_key' => ['consumer_key'],
            'consumer_secret' => ['consumer_secret'],
            'password' => ['password'],
            'callback_password' => ['callback_password'],
        ],
    ];

    /** Public identifiers: compared canonically (case, invisible characters, leading zeros of a number). */
    public const IDENTIFIERS = ['merchant_id', 'service_id', 'terminal_id', 'consumer_key'];

    /**
     * Every own-account credential a store can hold (judged or not), per provider: a save that changes one of them is
     * a credential-changing save (POST /owner/settings' refusal limit).
     *
     * @var array<string, list<string>>
     */
    public const CREDENTIALS = [
        'payme' => ['merchant_id', 'secret_key'],
        'click' => ['service_id', 'merchant_id', 'secret_key', 'merchant_user_id'],
        'uzum' => ['merchant_id', 'secret_key'],
        'paynet' => ['merchant_id', 'secret_key'],
        'paylov' => ['merchant_id', 'consumer_key', 'consumer_secret', 'username', 'password', 'callback_login', 'callback_password'],
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
                $value = self::value($section[$field] ?? null, $field);
                $old = self::value($was[$field] ?? null, $field);
                if ($value === null || ($old !== null && hash_equals($old, $value))) {
                    continue; // nothing saved, or the value the store already had (not judged here)
                }
                $platform ??= self::platform();
                if (self::matches($platform, $provider, $platformKeys, $value)) {
                    $fields[] = "payment_settings.{$provider}.{$field}";
                }
            }
        }

        return $fields;
    }

    /**
     * The credential fields ("payment_settings.<provider>.<field>") whose value in $after is not the one in $before
     * (compared as saved, trimmed; a blank or masked one in $after changes nothing: the model keeps a stored secret for it).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public static function changed(array $before, array $after): array
    {
        $fields = [];
        foreach (self::CREDENTIALS as $provider => $credentials) {
            $section = is_array($after[$provider] ?? null) ? $after[$provider] : [];
            $was = is_array($before[$provider] ?? null) ? $before[$provider] : [];
            foreach ($credentials as $field) {
                $value = self::trimmed($section[$field] ?? null);
                // A masked secret keeps the stored one (Store::mergeSettings), as a blank one does
                if ($value !== null && preg_match('/^[\s*•]*$/u', $value) !== 1 && $value !== self::trimmed($was[$field] ?? null)) {
                    $fields[] = "payment_settings.{$provider}.{$field}";
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
    public static function assertNone(array $before, array $after, ?int $storeId = null): void
    {
        $fields = self::violations($before, $after);
        if ($fields !== []) {
            // Field names and the store only, never a value
            Log::warning("A store's own-account credential equal to the platform's was refused", ['store_id' => $storeId, 'fields' => $fields]);

            throw new PlatformCredentialException($fields);
        }
    }

    /**
     * Every store that holds a platform credential as its own now (written before the rule, or before the platform's
     * value changed): store id => the fields ("payme.secret_key"...), never a value. A store whose settings this install
     * cannot decrypt is counted in $unreadable; a provider row it cannot decrypt is named in $unreadableProviders (its
     * config values are used instead).
     *
     * @param-out int $unreadable
     * @param-out list<string> $unreadableProviders
     * @return array<int, list<string>>
     */
    public static function existing(?int &$unreadable = null, ?array &$unreadableProviders = null): array
    {
        $unreadable = 0;
        $found = [];
        $platform = self::platform($unreadableProviders);
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
                            $value = self::value($section[$field] ?? null, $field);
                            if ($value !== null && self::matches($platform, $provider, $platformKeys, $value)) {
                                $fields[] = "{$provider}.{$field}";
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
     * @param  array<string, array<string, string>>  $platform  canonical values (platform())
     * @param  list<string>  $platformKeys
     */
    private static function matches(array $platform, string $provider, array $platformKeys, string $value): bool
    {
        foreach ($platformKeys as $key) {
            $theirs = $platform[$provider][$key] ?? null;
            if ($theirs !== null && hash_equals($theirs, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The platform's credentials per provider of FIELDS, canonical: each provider row read once, a blank value (or a
     * row this install cannot decrypt: logged with its slug, named in $unreadable) falling back to config
     * (PaymentProvider::platformCredential's resolution).
     *
     * @param-out list<string> $unreadable
     * @return array<string, array<string, string>>
     */
    private static function platform(?array &$unreadable = null): array
    {
        $unreadable = [];
        $rows = PaymentProvider::query()->whereIn('slug', array_keys(self::FIELDS))->get(['slug', 'credentials'])->keyBy('slug');
        $platform = [];
        foreach (self::FIELDS as $provider => $credentials) {
            $stored = null;
            try {
                $stored = $rows->get($provider)?->credentials;
            } catch (DecryptException) {
                // Never the stored text or the error's message: the slug only
                Log::warning('Payment provider credentials cannot be decrypted (APP_KEY / APP_PREVIOUS_KEYS): the platform credential check uses config for them', ['provider' => $provider]);
                $unreadable[] = $provider;
            }
            foreach (array_unique(array_merge(...array_values($credentials))) as $key) {
                $value = is_array($stored) ? ($stored[$key] ?? null) : null;
                if (blank($value)) {
                    $value = config("services.{$provider}.{$key}");
                }
                if (($value = self::value($value, $key)) !== null) {
                    $platform[$provider][$key] = $value;
                }
            }
        }

        return $platform;
    }

    /**
     * The canonical form a credential is compared in, or null when there is none: an identifier without any Unicode
     * space, zero-width or control character, lower-cased, an all-digit one without its leading zeros; a secret only
     * trimmed (exact otherwise).
     */
    private static function value(mixed $value, string $field): ?string
    {
        $value = self::trimmed($value);
        if ($value === null || !in_array($field, self::IDENTIFIERS, true)) {
            return $value;
        }

        $value = preg_replace('/[\p{Z}\p{C}\s]+/u', '', $value);
        if (!is_string($value) || $value === '') {
            return null;
        }
        $value = mb_strtolower($value);
        if (ctype_digit($value)) {
            $value = ltrim($value, '0');
            $value = $value === '' ? '0' : $value;
        }

        return $value;
    }

    private static function trimmed(mixed $value): ?string
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
        } catch (DecryptException) {
            $plain = $stored;
        }
        $settings = json_decode($plain, true);

        return is_array($settings) ? Store::normalizePaymentSettings($settings) : null;
    }
}
