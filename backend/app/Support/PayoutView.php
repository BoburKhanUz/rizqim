<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * P2-30: the one shape a payout request leaves the API in (GET /owner/payouts, GET /owner/finance, POST /owner/payouts),
 * with the card number masked ("8600 **** **** 9012": enough to recognise the card, not to use it) and any card
 * number typed into a note masked too. No finance response serializes a PayoutRequest model (it holds the full number).
 */
final class PayoutView
{
    /** The documented shape (ApiDocResponse type strings). */
    public const SHAPE = "array{id: int, amount: string, card: string, status: 'pending'|'completed'|'rejected', notes: string|null, resolution_notes: string|null, processed_at: string|null, created_at: string}";

    /**
     * @param  object  $row  a payout_requests row or a PayoutRequest model
     * @return array{id: int, amount: string, card: string, status: string, notes: string|null, resolution_notes: string|null, processed_at: string|null, created_at: string}
     */
    public static function present(object $row): array
    {
        $get = fn (string $key): mixed => $row instanceof \Illuminate\Database\Eloquent\Model ? $row->getAttribute($key) : ($row->{$key} ?? null);

        return [
            'id' => (int) $get('id'),
            'amount' => Money::of($get('amount'))->toString(),
            'card' => self::maskCard((string) $get('card_number')),
            'status' => (string) $get('status'),
            'notes' => self::maskCardsIn($get('notes')),
            'resolution_notes' => self::maskCardsIn($get('resolution_notes')),
            'processed_at' => self::iso($get('processed_at')),
            'created_at' => (string) self::iso($get('created_at')),
        ];
    }

    /** present() plus the keys the model used to serialize, for clients written against it; card_number is masked too. */
    public const LEGACY_SHAPE = "array{id: int, amount: string, card: string, status: 'pending'|'completed'|'rejected', notes: string|null, resolution_notes: string|null, processed_at: string|null, created_at: string, store_id: int, card_number: string, processed_by: int|null, updated_at: string|null}";

    /**
     * GET /owner/finance and POST /owner/payouts: present() plus `store_id`, `processed_by`, `updated_at` and
     * `card_number` (the masked card, never the number: the Mini App written against the model reads it).
     *
     * @return array<string, mixed>
     */
    public static function presentLegacy(object $row): array
    {
        $view = self::present($row);
        $get = fn (string $key): mixed => $row instanceof \Illuminate\Database\Eloquent\Model ? $row->getAttribute($key) : ($row->{$key} ?? null);

        return $view + [
            'store_id' => (int) $get('store_id'),
            'card_number' => $view['card'],
            'processed_by' => $get('processed_by') !== null ? (int) $get('processed_by') : null,
            'updated_at' => self::iso($get('updated_at')),
        ];
    }

    /**
     * "8600 **** **** 9012"; "****" for anything too short to be a card.
     */
    public static function maskCard(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return strlen($digits) >= 8 ? substr($digits, 0, 4) . ' **** **** ' . substr($digits, -4) : '****';
    }

    /**
     * Every card-like number (13–19 digits) in free text, masked. P2-33: the digits may be grouped by up to three
     * separators each: spaces (also several, tabs, no-break spaces), dots, dashes, underscores or slashes
     * ("8600.1234.5678.9012", "8600  1234  5678  9012"). Not across a line break.
     */
    public static function maskCardsIn(mixed $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $masked = preg_replace_callback(
            '/(?<!\d)\d(?:[\h.\-_\/]{0,3}\d){12,18}(?!\d)/u',
            fn (array $match) => self::maskCard($match[0]),
            (string) $text,
        );

        // Not valid UTF-8 (preg fails): mask every digit rather than return a number
        return $masked ?? (string) preg_replace('/\d/', '*', (string) $text);
    }

    private static function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance($value)->utc()->toIso8601String()
            : CarbonImmutable::parse((string) $value, 'UTC')->toIso8601String();
    }
}
