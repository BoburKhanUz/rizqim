<?php

namespace App\Services\Audit;

/**
 * Makes audit data safe to keep and show to store owners: secrets (PINs, passwords, tokens, codes, card data,
 * Telegram init data) are dropped to a marker and phone numbers are masked, by key and inside free text.
 */
final class AuditRedactor
{
    public const REDACTED = '[redacted]';

    // Matched against a key's parts (split on _ - . and camelCase): pin_hash, card_number, initData, code, ...
    private const SECRET_PARTS = ['pin', 'password', 'passwd', 'secret', 'token', 'otp', 'pan', 'cvv', 'cvc', 'hash',
        'card', 'remember', 'credential', 'credentials', 'initdata', 'init', 'apikey', 'key', 'code', 'signature',
        // P4-11: 2FA (app_authentication_recovery_codes, totp...), challenge and invite tokens
        'codes', 'recovery', 'totp', 'challenge'];

    // ... except these, which only name or number a record (an order's short code, a terminal staff code)
    private const NOT_SECRET = ['short_code', 'staff_code'];

    private const PHONE_PARTS = ['phone', 'mobile', 'tel', 'telephone'];

    // +998 90 123 45 67 in any spacing, or a bare 9-12 digit run (a local number)
    // Not inside a longer token: a UUID or hex id ("…-b8e989345737") holds digit runs that are no phone numbers
    private const PHONE_TEXT = '/(?<![\w\-])(?:\+?998[\s\-]?\d{2}[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}|\d{9,12})(?![\w\-])/';

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function clean(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $clean[$key] = self::value(is_string($key) ? $key : null, $value);
        }

        return $clean;
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '***';
        }

        return (str_starts_with(trim($phone), '+') ? '+' : '') . substr($digits, 0, min(5, strlen($digits) - 2))
            . str_repeat('*', max(0, strlen($digits) - min(5, strlen($digits) - 2) - 2)) . substr($digits, -2);
    }

    private static function value(?string $key, mixed $value): mixed
    {
        $parts = $key !== null ? self::parts($key) : [];

        if ($key !== null && !in_array(strtolower($key), self::NOT_SECRET, true) && array_intersect($parts, self::SECRET_PARTS)) {
            return $value === null ? null : self::REDACTED;
        }

        if (is_array($value)) {
            return self::clean($value);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? self::text((string) $value) : null;
        }

        if (is_string($value) && array_intersect($parts, self::PHONE_PARTS)) {
            return self::maskPhone($value);
        }

        // Numeric strings are amounts and ids ("120000000.00"), not phone numbers
        return is_string($value) && !is_numeric($value) ? self::text($value) : $value;
    }

    /**
     * @return list<string>
     */
    private static function parts(string $key): array
    {
        $snake = strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key));

        return array_values(array_filter(preg_split('/[_\-.\s]+/', $snake) ?: [], fn (string $part) => $part !== ''));
    }

    private static function text(string $value): string
    {
        return (string) preg_replace_callback(self::PHONE_TEXT, fn (array $match) => (string) self::maskPhone($match[0]), $value);
    }
}
