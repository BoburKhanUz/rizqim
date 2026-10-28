<?php

namespace App\Services\Admin\Today;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;

/**
 * P4-13: what the Bugun numbers count (docs/API.md "Bugun"), in one place for the aggregate job and the live part:
 *
 * - The day is the Asia/Tashkent calendar day; an order belongs to the day and hour it was created (`created_at`),
 *   a subscription invoice to the day it was paid (`paid_at`).
 * - Marketplace orders: channels `telegram` and `mobile` (the RIZQIM bot, Mini App and app). POS sales (`pos_hall`,
 *   `pos_takeaway`) and outside aggregators are kept per channel for the channel split, never in GMV.
 * - Counted order: not soft-deleted, not `canceled`, `rejected` or `refunded`, and not an online order still waiting
 *   for its payment (status `new`, payment method one of orders.online_payment_methods, payment status not `paid`:
 *   the customer has not paid and the store has not seen it; CancelUnpaidOnlineOrdersJob cancels it after its TTL).
 *   Orders in progress count; one paid, cancelled or refunded later moves in or out at the next refresh (the job
 *   recomputes the recent days; a finished hour is the last refresh's snapshot, so at most 5 minutes late).
 * - GMV: the counted orders' `total_price` (what the customer pays: food after discount + delivery + service
 *   charge and place fee). Commission: the same orders' `commission_amount`, the snapshot taken at checkout that the
 *   ledger books on delivery; so it is the commission these orders bring, not yet the money booked (the monthly
 *   Rizqim daromadi report books it on the delivery day).
 * - Subscription revenue: `amount` of subscription invoices with status `paid`, by `paid_at`.
 */
final class PlatformStats
{
    public const TIMEZONE = 'Asia/Tashkent';

    /** Channel key of the rows that hold paid subscription invoices (no region, no orders). */
    public const SUBSCRIPTIONS = 'subscriptions';

    /** Region key of orders whose branch has no region (or no branch). */
    public const NO_REGION = 0;

    /**
     * @return list<string>
     */
    public static function marketplaceChannels(): array
    {
        return [OrderChannel::TELEGRAM->value, OrderChannel::MOBILE->value];
    }

    public static function isMarketplace(string $channel): bool
    {
        return in_array($channel, self::marketplaceChannels(), true);
    }

    /**
     * Statuses that are not counted (orders, GMV, commission).
     *
     * @return list<string>
     */
    public static function uncountedStatuses(): array
    {
        return [OrderStatus::CANCELED->value, OrderStatus::REJECTED->value, OrderStatus::REFUNDED->value];
    }

    /**
     * Online payment methods (orders.online_payment_methods) whose `new` unpaid orders are not counted yet; only plain
     * method names, so they can stand in SQL as literals.
     *
     * @return list<string>
     */
    public static function onlineMethods(): array
    {
        return array_values(array_filter(
            array_map('strval', (array) config('orders.online_payment_methods', [])),
            fn (string $method) => preg_match('/^[a-z0-9_]+$/', $method) === 1,
        ));
    }

    /**
     * @return list<string>
     */
    public static function canceledStatuses(): array
    {
        return [OrderStatus::CANCELED->value, OrderStatus::REJECTED->value];
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    /**
     * Midnight of the local day that $at falls on, in Asia/Tashkent.
     */
    public static function dayStart(CarbonImmutable $at): CarbonImmutable
    {
        return $at->setTimezone(self::TIMEZONE)->startOfDay();
    }

    public static function dayStartOf(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE);
    }

    public static function floorHour(CarbonImmutable $at): CarbonImmutable
    {
        return $at->setTimezone(self::TIMEZONE)->startOfHour();
    }

    /**
     * An instant as the timestamp columns hold it (app timezone wall time, UTC).
     */
    public static function column(CarbonImmutable $at): string
    {
        return $at->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public static function fromColumn(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, (string) config('app.timezone'));
    }
}
