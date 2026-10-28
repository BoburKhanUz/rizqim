<?php

namespace App\Services\Admin\Customers;

use App\Services\Admin\Today\PlatformStats;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * P4-31: the small shared formats of Mijozlar va yordam: times ISO 8601 in Asia/Tashkent, money as a decimal string
 * and in words for explanations ("450 000 so'm"), short texts.
 */
final class CustomerFormat
{
    public static function iso(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(PlatformStats::TIMEZONE)->toIso8601String();
    }

    public static function money(Money|string|int|float|null $amount): string
    {
        return Money::of($amount ?? 0)->toString();
    }

    public static function words(Money|string|int|float|null $amount): string
    {
        return number_format(Money::of($amount ?? 0)->toFloat(), 0, '.', ' ') . " so'm";
    }

    public static function short(?string $text, int $length = 200): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = trim($text);

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }

    /**
     * Escapes a LIKE pattern part.
     */
    public static function like(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($value));
    }
}
