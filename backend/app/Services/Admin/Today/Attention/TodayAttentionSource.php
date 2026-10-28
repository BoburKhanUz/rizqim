<?php

namespace App\Services\Admin\Today\Attention;

use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Attention\DescribesAttention;
use App\Services\Admin\Today\PlatformStats;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * P4-13: shared parts of the Bugun section's own "Diqqat talab qiladi" sources (registered in
 * App\Providers\AdminTodayServiceProvider). Each one counts and lists with a single indexed query and at most
 * $limit rows. Row links (`href`) open the admin app section that owns the record: an order card, or the section
 * page with the record's id in the query string (`/finance?payout=12`) until that section has its own card.
 */
abstract class TodayAttentionSource implements AttentionSource, DescribesAttention
{
    protected function money(Money|string|int|float|null $amount): string
    {
        return number_format(Money::of($amount)->toFloat(), 0, '.', ' ') . " so'm";
    }

    protected function iso(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(PlatformStats::TIMEZONE)->toIso8601String();
    }

    protected function time(?CarbonInterface $at): string
    {
        return $at ? $at->copy()->setTimezone(PlatformStats::TIMEZONE)->format('d.m H:i') : '—';
    }

    protected function short(?string $text, int $length = 120): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $line = trim(strtok($text, "\n") ?: $text);

        return mb_strlen($line) > $length ? mb_substr($line, 0, $length - 1) . '…' : $line;
    }

    /**
     * @param  list<string|null>  $parts
     */
    protected function join(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, fn (?string $part) => $part !== null && $part !== ''));

        return $parts !== [] ? implode(' · ', $parts) : null;
    }
}
