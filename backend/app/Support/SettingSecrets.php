<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Review 17 M1: the control centre's secret settings (SystemSettings::isSecret: the Eskiz password, the Onlinegroup
 * API key) are stored in `settings.value` encrypted with APP_KEY (Crypt::encryptString). SystemSettings::write()
 * encrypts, setting() and SystemSettings::resolve() decrypt; the cache only ever holds the ciphertext.
 *
 * A row that is not a Laravel encryption payload is plain text written before the encryption migration (or by a
 * seeder / test with Setting::create) and is answered as it is. A payload that no longer decrypts (APP_KEY changed
 * without APP_PREVIOUS_KEYS) answers null: the secret counts as not configured (the drivers report it) instead of
 * the ciphertext going to the provider.
 */
final class SettingSecrets
{
    public static function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '' || !self::looksEncrypted($stored)) {
            return $stored;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            Log::error('A secret setting no longer decrypts with APP_KEY: it counts as not configured until it is replaced.');

            return null;
        }
    }

    /**
     * Whether the value decrypts with APP_KEY (the migration leaves such a value alone).
     */
    public static function isEncrypted(string $stored): bool
    {
        if (!self::looksEncrypted($stored)) {
            return false;
        }
        try {
            Crypt::decryptString($stored);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * The shape of a Crypt payload: base64 of a JSON object with iv, value and mac.
     */
    private static function looksEncrypted(string $value): bool
    {
        $json = base64_decode($value, true);
        if ($json === false) {
            return false;
        }
        $payload = json_decode($json, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
