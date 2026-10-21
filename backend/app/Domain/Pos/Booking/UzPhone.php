<?php

namespace App\Domain\Pos\Booking;

/**
 * Uzbek phone numbers as the cashier types them ("90 123 45 67", "+998 (90) 123-45-67", "998901234567") in the one
 * stored form, +998XXXXXXXXX; null when it is not one.
 */
final class UzPhone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        }

        return preg_match('/^998\d{9}$/', $digits) ? '+' . $digits : null;
    }

    /**
     * The digits to look a number up by (at least four, the end of a number is enough), or null.
     */
    public static function searchDigits(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        return strlen($digits) >= 4 ? substr($digits, -12) : null;
    }
}
