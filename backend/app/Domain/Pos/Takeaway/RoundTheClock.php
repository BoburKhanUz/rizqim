<?php

namespace App\Domain\Pos\Takeaway;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PosShift;
use App\Models\Store;
use App\Support\Money;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * P6-34 (module `round_the_clock`, docs/POS_SPEC.md "24/7"): a branch that never closes. The rules are the business
 * day's (POS_SPEC 2.3), the same for every branch; 24/7 decides where the day starts and shows it:
 *
 *  - the business day starts at the branch's `business_day_starts_at` (05:00 by default, set per branch on the POS
 *    settings): 23:59, 00:00 and 04:59 belong to the day before, 05:00 starts the new one;
 *  - a shift belongs to the business day it was opened on, and so does its Z (a shift opened at 20:00 and closed at
 *    08:00 is the evening's): the shift is the cashier's drawer, not the calendar;
 *  - an order belongs to the business day it was opened on (OrderStamp), whatever shift takes it: queue numbers restart
 *    from 1 at the day start even in the middle of a shift (decision: per business day, not per shift: the talon
 *    never repeats a number within a business day, and two shifts of one day never share one); a till holding the old
 *    day's block gets the new day's at the next number (QueueNumbers checks a number against the order's own day);
 *  - reports by business day (report()): the orders, money and packaging of each day, and the shifts that worked it.
 *
 * The till reads `business_day` from GET /pos/bootstrap (`takeaway`): the date, when it started and when the next one
 * starts, so it rolls its queue blocks over at the day start without a new bootstrap. With the module on the pickup
 * times ignore the opening hours (ScheduledPickup::isOpenAt).
 */
final class RoundTheClock
{
    public const BUSINESS_DAY = 'array{business_date: string, starts_at: string, started_at: string, next_starts_at: string, round_the_clock: bool}';

    public const REPORT_ROW = 'array{business_date: string, started_at: string, ends_at: string, orders: int, pos_orders: int, online_orders: int, revenue: string, packaging_amount: string, after_midnight_orders: int, first_order_at: string|null, last_order_at: string|null, shifts: list<array{uuid: string, business_date: string, z_number: int|null, opened_at: string|null, closed_at: string|null, status: string}>}';

    public const MAX_DAYS = 31;

    public function __construct(
        private readonly PosModules $modules,
    ) {}

    /**
     * The business day of $at for the branch: its date, start time, when it started and when the next one starts (UTC).
     *
     * @return array{business_date: string, starts_at: string, started_at: string, next_starts_at: string, round_the_clock: bool}
     */
    public function businessDay(Branch $branch, ?CarbonInterface $at = null): array
    {
        $at = CarbonImmutable::instance($at ?? now());
        $date = BusinessDay::of($branch, $at);
        $start = $this->startOf($branch, $date);

        return [
            'business_date' => $date,
            'starts_at' => PosSettings::timeOf($branch->getAttribute('business_day_starts_at')),
            'started_at' => $start->utc()->toJSON(),
            'next_starts_at' => $start->addDay()->utc()->toJSON(),
            'round_the_clock' => $this->modules->enabled($branch, PosModule::ROUND_THE_CLOCK),
        ];
    }

    /**
     * When the business day $date (Y-m-d) of the branch starts, in the store's time zone.
     */
    public function startOf(Branch $branch, string $date): CarbonImmutable
    {
        $timezone = new DateTimeZone(StoreTimezone::of(Store::find($branch->store_id)));
        [$hour, $minute] = array_map('intval', explode(':', PosSettings::timeOf($branch->getAttribute('business_day_starts_at'))));

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone)->setTime($hour, $minute);
    }

    /**
     * Each business day from $from to $to (Y-m-d, at most MAX_DAYS): its orders (POS by their business day, online by
     * when they were placed), the money of those not cancelled (total_price), the packaging, how many were opened after
     * midnight (their calendar date is the next day), the first and last order, and the shifts opened on it. Ranges
     * are compared in UTC from the day starts.
     *
     * @return list<array<string, mixed>> REPORT_ROW
     */
    public function report(Branch $branch, string $from, string $to): array
    {
        $timezone = new DateTimeZone(StoreTimezone::of(Store::find($branch->store_id)));
        $rangeStart = $this->startOf($branch, $from);
        $rangeEnd = $this->startOf($branch, $to)->addDay();

        $rows = [];
        for ($day = CarbonImmutable::createFromFormat('!Y-m-d', $from); $day->format('Y-m-d') <= $to; $day = $day->addDay()) {
            $date = $day->format('Y-m-d');
            $start = $this->startOf($branch, $date);
            $rows[$date] = [
                'business_date' => $date,
                'started_at' => $start->utc()->toJSON(),
                'ends_at' => $start->addDay()->utc()->toJSON(),
                'orders' => 0,
                'pos_orders' => 0,
                'online_orders' => 0,
                'revenue' => Money::zero(),
                'packaging_amount' => Money::zero(),
                'after_midnight_orders' => 0,
                'first_order_at' => null,
                'last_order_at' => null,
                'shifts' => [],
            ];
        }

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->where(fn ($query) => $query
                ->whereBetween('business_date', [$from, $to])
                ->orWhere(fn ($query) => $query->whereNull('business_date')->where('created_at', '>=', $rangeStart->utc())->where('created_at', '<', $rangeEnd->utc())))
            ->whereNotIn('status', [OrderStatus::CANCELED->value, OrderStatus::REJECTED->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'store_id', 'branch_id', 'channel', 'status', 'business_date', 'created_at', 'total_price', 'packaging_amount']);

        /** @var array<string, array{orders: int, pos: int, online: int, revenue: Money, packaging: Money, after: int, first: string|null, last: string|null}> $sums */
        $sums = [];
        foreach ($orders as $order) {
            $created = CarbonImmutable::instance($order->created_at ?? now());
            $date = $order->business_date?->toDateString() ?? BusinessDay::of($branch, $created);
            if (!isset($rows[$date])) {
                continue;
            }
            $sum = $sums[$date] ?? ['orders' => 0, 'pos' => 0, 'online' => 0, 'revenue' => Money::zero(), 'packaging' => Money::zero(), 'after' => 0, 'first' => null, 'last' => null];
            $sum['orders']++;
            $order->channel->isPos() ? $sum['pos']++ : $sum['online']++;
            if ($order->status !== OrderStatus::REFUNDED) {
                $sum['revenue'] = $sum['revenue']->plus($order->total_price);
                $sum['packaging'] = $sum['packaging']->plus($order->getAttribute('packaging_amount') ?? '0');
            }
            if ($created->setTimezone($timezone)->format('Y-m-d') !== $date) {
                $sum['after']++;
            }
            $sum['first'] ??= $created->utc()->toJSON();
            $sum['last'] = $created->utc()->toJSON();
            $sums[$date] = $sum;
        }
        foreach ($sums as $date => $sum) {
            $rows[$date] = [
                ...$rows[$date],
                'orders' => $sum['orders'],
                'pos_orders' => $sum['pos'],
                'online_orders' => $sum['online'],
                'revenue' => $sum['revenue'],
                'packaging_amount' => $sum['packaging'],
                'after_midnight_orders' => $sum['after'],
                'first_order_at' => $sum['first'],
                'last_order_at' => $sum['last'],
            ];
        }

        $shifts = PosShift::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('business_date', [$from, $to])
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();
        foreach ($shifts as $shift) {
            $date = $shift->business_date->toDateString();
            if (isset($rows[$date])) {
                $rows[$date]['shifts'][] = [
                    'uuid' => (string) $shift->uuid,
                    'business_date' => $date,
                    'z_number' => $shift->getAttribute('z_number') !== null ? (int) $shift->getAttribute('z_number') : null,
                    'opened_at' => $shift->opened_at->toJSON(),
                    'closed_at' => $shift->closed_at?->toJSON(),
                    'status' => $shift->status->value,
                ];
            }
        }

        return array_values(array_map(fn (array $row) => [
            ...$row,
            'revenue' => $row['revenue']->toString(),
            'packaging_amount' => $row['packaging_amount']->toString(),
        ], $rows));
    }
}
