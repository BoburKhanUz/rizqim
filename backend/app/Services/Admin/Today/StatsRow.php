<?php

namespace App\Services\Admin\Today;

use App\Support\Money;

/**
 * P4-13: one aggregate cell: a local hour (or a whole day: hour null) of one region and channel, as the stats tables
 * store it and as the live query returns it.
 */
final class StatsRow
{
    public function __construct(
        public readonly string $date,
        public readonly ?int $hour,
        public readonly int $regionId,
        public readonly string $channel,
        public readonly int $orders,
        public readonly Money $gmv,
        public readonly Money $commission,
        public readonly int $canceled,
        public readonly int $refunded,
        public readonly int $invoicesPaid,
        public readonly Money $subscription,
    ) {}

    /**
     * A row of platform_hourly_stats / platform_daily_stats (or the live query, same column names).
     */
    public static function fromRecord(object $record): self
    {
        return new self(
            date: substr((string) $record->date, 0, 10),
            hour: isset($record->hour) ? (int) $record->hour : null,
            regionId: (int) $record->region_id,
            channel: (string) $record->channel,
            orders: (int) $record->orders_count,
            gmv: Money::of((string) $record->gmv),
            commission: Money::of((string) $record->commission),
            canceled: (int) $record->canceled_count,
            refunded: (int) $record->refunded_count,
            invoicesPaid: (int) $record->invoices_paid_count,
            subscription: Money::of((string) $record->subscription_revenue),
        );
    }

    public function plus(self $other, ?int $hour): self
    {
        return new self(
            date: $this->date,
            hour: $hour,
            regionId: $this->regionId,
            channel: $this->channel,
            orders: $this->orders + $other->orders,
            gmv: $this->gmv->plus($other->gmv),
            commission: $this->commission->plus($other->commission),
            canceled: $this->canceled + $other->canceled,
            refunded: $this->refunded + $other->refunded,
            invoicesPaid: $this->invoicesPaid + $other->invoicesPaid,
            subscription: $this->subscription->plus($other->subscription),
        );
    }

    public function withHour(?int $hour): self
    {
        return new self($this->date, $hour, $this->regionId, $this->channel, $this->orders, $this->gmv, $this->commission, $this->canceled, $this->refunded, $this->invoicesPaid, $this->subscription);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toRecord(string $computedAt): array
    {
        $record = [
            'date' => $this->date,
            'region_id' => $this->regionId,
            'channel' => $this->channel,
            'orders_count' => $this->orders,
            'gmv' => $this->gmv->toString(),
            'commission' => $this->commission->toString(),
            'canceled_count' => $this->canceled,
            'refunded_count' => $this->refunded,
            'invoices_paid_count' => $this->invoicesPaid,
            'subscription_revenue' => $this->subscription->toString(),
            'computed_at' => $computedAt,
        ];

        if ($this->hour !== null) {
            $record['hour'] = $this->hour;
        }

        return $record;
    }
}
