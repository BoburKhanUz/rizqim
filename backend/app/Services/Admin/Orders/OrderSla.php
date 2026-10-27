<?php

namespace App\Services\Admin\Orders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P4-14: a marketplace order's SLA, computed on the server from its status, its status history and the limits
 * (OrderSlaSettings). One stage runs at a time:
 *  - accept: a new order the store has to accept (cash or keshbek, or an online order already paid; an unpaid online
 *    order waits for the customer, not the store), from checkout;
 *  - prepare: accepted or preparing, from the first `accepted` in the history;
 *  - deliver: a delivery order on its way (on_the_way, delivery), from the first of those in the history.
 * Ready, courier_assigned and final orders run no stage (a ready delivery order without a courier is its own attention
 * row). State: `ok`, `warning` from 80% of the limit, `late` from the limit (`late_by_minutes` whole minutes past it).
 */
final class OrderSla
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const LATE = 'late';

    /** Statuses that end the prepare stage (the first one after acceptance). */
    private const PREPARE_ENDS = ['ready', 'courier_assigned', 'on_the_way', 'delivery', 'delivered', 'canceled', 'rejected', 'refunded'];

    public function __construct(
        private readonly OrderSlaSettings $settings,
    ) {}

    /**
     * @return list<string>
     */
    public static function onlineMethods(): array
    {
        return array_values(array_map('strval', (array) config('orders.online_payment_methods', [])));
    }

    /**
     * A new order that is the store's to accept now (not one waiting for the customer's online payment).
     */
    public static function awaitsStore(Order $order): bool
    {
        return $order->status === OrderStatus::NEW
            && (!in_array((string) $order->payment_method, self::onlineMethods(), true) || $order->payment_status === PaymentStatus::PAID);
    }

    public static function runningStage(Order $order): ?string
    {
        return match (true) {
            $order->status === OrderStatus::NEW => self::awaitsStore($order) ? OrderSlaSettings::ACCEPT : null,
            in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING], true) => OrderSlaSettings::PREPARE,
            in_array($order->status, [OrderStatus::ON_THE_WAY, OrderStatus::DELIVERY], true) && $order->order_type === OrderType::DELIVERY => OrderSlaSettings::DELIVER,
            default => null,
        };
    }

    /**
     * The running stage's SLA, or null when none runs.
     *
     * @param  CarbonInterface|null  $acceptedAt  first `accepted` in the history
     * @param  CarbonInterface|null  $enRouteAt  first on_the_way / delivery in the history
     * @param  array{accept: int, prepare: int, deliver: int}|null  $limits
     * @return array{stage: string, stage_label: string, state: string, limit_minutes: int, started_at: string, warning_at: string, due_at: string, late_by_minutes: int, minutes_left: int}|null
     */
    public function current(Order $order, ?CarbonInterface $acceptedAt, ?CarbonInterface $enRouteAt, ?array $limits = null, ?CarbonInterface $now = null): ?array
    {
        $stage = self::runningStage($order);
        if ($stage === null) {
            return null;
        }

        $limits ??= $this->settings->limits();
        $start = match ($stage) {
            OrderSlaSettings::ACCEPT => $order->created_at,
            OrderSlaSettings::PREPARE => $acceptedAt ?? $order->created_at,
            default => $enRouteAt ?? $order->updated_at,
        };
        if ($start === null) {
            return null;
        }

        return self::evaluate($stage, $start, $limits[$stage], $now ?? now());
    }

    /**
     * @return array{stage: string, stage_label: string, state: string, limit_minutes: int, started_at: string, warning_at: string, due_at: string, late_by_minutes: int, minutes_left: int}
     */
    public static function evaluate(string $stage, CarbonInterface $start, int $limitMinutes, CarbonInterface $now): array
    {
        $start = Carbon::instance($start);
        $due = $start->copy()->addMinutes($limitMinutes);
        $warning = $start->copy()->addSeconds((int) round($limitMinutes * 60 * OrderSlaSettings::WARNING_RATIO));
        $elapsed = $start->diffInSeconds($now, false);
        $limitSeconds = $limitMinutes * 60;

        $state = match (true) {
            $elapsed >= $limitSeconds => self::LATE,
            $now->greaterThanOrEqualTo($warning) => self::WARNING,
            default => self::OK,
        };

        return [
            'stage' => $stage,
            'stage_label' => OrderSlaSettings::label($stage),
            'state' => $state,
            'limit_minutes' => $limitMinutes,
            'started_at' => $start->toIso8601String(),
            'warning_at' => $warning->toIso8601String(),
            'due_at' => $due->toIso8601String(),
            'late_by_minutes' => $state === self::LATE ? intdiv((int) ($elapsed - $limitSeconds), 60) : 0,
            'minutes_left' => $state === self::LATE ? 0 : (int) ceil(($limitSeconds - max(0, $elapsed)) / 60),
        ];
    }

    /**
     * Every stage the order went through (or is in), for the order card: when it started and ended, the limit, how long
     * it took and whether it was late. The accept stage is left out for online orders (accepted by the payment itself).
     *
     * @param  Collection<int, OrderStatusHistory>  $histories
     * @param  array{accept: int, prepare: int, deliver: int}|null  $limits
     * @return list<array{stage: string, stage_label: string, limit_minutes: int, started_at: string, ended_at: string|null, took_minutes: int|null, running: bool, state: string, late_by_minutes: int}>
     */
    public function timeline(Order $order, Collection $histories, ?array $limits = null, ?CarbonInterface $now = null): array
    {
        $limits ??= $this->settings->limits();
        $now ??= now();
        $rows = $histories->sortBy([['created_at', 'asc'], ['id', 'asc']])->values();
        $running = self::runningStage($order);

        $first = function (array $statuses, ?CarbonInterface $after = null) use ($rows): ?CarbonInterface {
            foreach ($rows as $row) {
                if (in_array($row->status->value, $statuses, true) && $row->created_at !== null
                    && ($after === null || $row->created_at->greaterThanOrEqualTo($after))) {
                    return $row->created_at;
                }
            }

            return null;
        };

        $stages = [];
        if (!in_array((string) $order->payment_method, self::onlineMethods(), true) && $order->created_at !== null) {
            $stages[] = [OrderSlaSettings::ACCEPT, $order->created_at, $first(['accepted', 'rejected', 'canceled'])];
        }
        if ($accepted = $first(['accepted'])) {
            $stages[] = [OrderSlaSettings::PREPARE, $accepted, $first(self::PREPARE_ENDS, $accepted)];
        }
        if ($order->order_type === OrderType::DELIVERY && ($enRoute = $first(['on_the_way', 'delivery']))) {
            $stages[] = [OrderSlaSettings::DELIVER, $enRoute, $first(['delivered', 'canceled', 'refunded'], $enRoute)];
        }

        $timeline = [];
        foreach ($stages as [$stage, $start, $end]) {
            $isRunning = $end === null && $running === $stage;
            if ($end === null && !$isRunning) {
                continue; // left without a recorded end (an order changed outside the lifecycle): nothing to measure
            }
            $evaluated = self::evaluate($stage, $start, $limits[$stage], $end ?? $now);
            $timeline[] = [
                'stage' => $stage,
                'stage_label' => OrderSlaSettings::label($stage),
                'limit_minutes' => $limits[$stage],
                'started_at' => Carbon::instance($start)->toIso8601String(),
                'ended_at' => $end !== null ? Carbon::instance($end)->toIso8601String() : null,
                'took_minutes' => $end !== null ? intdiv((int) Carbon::instance($start)->diffInSeconds($end, true), 60) : null,
                'running' => $isRunning,
                'state' => $evaluated['state'],
                'late_by_minutes' => $evaluated['late_by_minutes'],
            ];
        }

        return $timeline;
    }
}
