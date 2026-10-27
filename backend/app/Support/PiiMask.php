<?php

namespace App\Support;

/**
 * P4-11: how personal data looks to a team member without admin.pii.view (every admin resource uses it through
 * App\Services\Admin\AdminPii): an Uzbek phone as `+998 90 *** ** 67`, a card as `8600 **** **** 1234`.
 */
final class PiiMask
{
    public static function phone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return $phone;
        }

        $digits = (string) preg_replace('/\D/', '', $phone);

        // +998 XX XXX XX XX
        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            return '+998 ' . substr($digits, 3, 2) . ' *** ** ' . substr($digits, -2);
        }

        // A local 9-digit number: XX XXX XX XX
        if (strlen($digits) === 9) {
            return substr($digits, 0, 2) . ' *** ** ' . substr($digits, -2);
        }

        if (strlen($digits) < 4) {
            return '***';
        }

        return (str_starts_with(trim($phone), '+') ? '+' : '') . substr($digits, 0, 2)
            . str_repeat('*', strlen($digits) - 4) . substr($digits, -2);
    }

    /**
     * A phone in the usual spacing, unmasked: `+998 90 123 45 67`.
     */
    public static function formatPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $phone);

        return strlen($digits) === 12 && str_starts_with($digits, '998')
            ? '+998 ' . substr($digits, 3, 2) . ' ' . substr($digits, 5, 3) . ' ' . substr($digits, 8, 2) . ' ' . substr($digits, 10, 2)
            : $phone;
    }

    public static function card(?string $card): ?string
    {
        if ($card === null || trim($card) === '') {
            return $card;
        }

        $digits = (string) preg_replace('/\D/', '', $card);
        if (strlen($digits) < 8) {
            return '****';
        }

        return substr($digits, 0, 4) . ' **** **** ' . substr($digits, -4);
    }
}
