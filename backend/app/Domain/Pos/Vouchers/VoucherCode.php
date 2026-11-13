<?php

namespace App\Domain\Pos\Vouchers;

/**
 * P6-43: the voucher's human code, three groups of four ("K7QM-4XPA-9HTR"; the printed QR carries the same text).
 * The alphabet leaves out the look-alikes (0/O, 1/I/L, 5/S, 2/Z, 8/B), so a code read aloud or typed from paper is
 * found: whatever the cashier types (spaces, dashes, lower case) is normalized before the lookup.
 */
final class VoucherCode
{
    public const ALPHABET = 'ACDEFGHJKMNPQRTUVWXY3469';

    public static function generate(): string
    {
        $chars = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 12; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return self::format($chars);
    }

    /**
     * The stored form of what was typed or scanned, or null when it cannot be a code.
     */
    public static function normalize(?string $input): ?string
    {
        $chars = strtoupper(preg_replace('/[\s\-_]+/', '', (string) $input) ?? '');
        if (strlen($chars) !== 12 || strspn($chars, self::ALPHABET) !== 12) {
            return null;
        }

        return self::format($chars);
    }

    /**
     * The code with its middle group hidden, for the audit log and the owner's alerts.
     */
    public static function mask(string $code): string
    {
        return substr($code, 0, 4) . '-****-' . substr($code, -4);
    }

    private static function format(string $chars): string
    {
        return implode('-', str_split($chars, 4));
    }
}
