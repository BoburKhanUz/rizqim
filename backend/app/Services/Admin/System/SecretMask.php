<?php

namespace App\Services\Admin\System;

/**
 * P4-31: how the control center shows a secret (a merchant key, a bot token, an SMS password): never the value, only
 * whether it is set and its last 4 characters. A value shorter than 8 characters shows no characters at all (its last 4
 * would be most of it).
 */
final class SecretMask
{
    public const MIN_LENGTH_FOR_HINT = 8;

    /**
     * @return array{configured: bool, last4: string|null}
     */
    public static function describe(mixed $value): array
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text === '') {
            return ['configured' => false, 'last4' => null];
        }

        return ['configured' => true, 'last4' => mb_strlen($text) >= self::MIN_LENGTH_FOR_HINT ? mb_substr($text, -4) : null];
    }
}
