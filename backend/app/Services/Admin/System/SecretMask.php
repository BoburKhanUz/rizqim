<?php

namespace App\Services\Admin\System;

/**
 * P4-31: how the control center shows a secret (a merchant key, a bot token, an SMS password): never the value, only
 * whether it is set and its last 4 characters. A value shorter than 16 characters shows no characters at all (review W3
 * S#6: with 4 of 8 shown, half of a short secret is out); `$hint = false` never shows them (a member without the right to
 * manage the thing).
 */
final class SecretMask
{
    public const MIN_LENGTH_FOR_HINT = 16;

    /**
     * @return array{configured: bool, last4: string|null}
     */
    public static function describe(mixed $value, bool $hint = true): array
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text === '') {
            return ['configured' => false, 'last4' => null];
        }

        return ['configured' => true, 'last4' => $hint && mb_strlen($text) >= self::MIN_LENGTH_FOR_HINT ? mb_substr($text, -4) : null];
    }
}
