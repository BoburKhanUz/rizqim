<?php

namespace App\Services\Admin\Orders;

use App\Domain\Pos\Booking\UzPhone;
use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * P4-14: marketplace orders of every store as the Boshqaruv markazi reads them: the channels customers order through
 * RIZQIM (the Telegram bot and Mini App, the mobile app), as Bugun counts them; POS hall and takeaway orders are the
 * stores' own and aggregator orders (Yandex, Uzum: phase 5) the aggregators', so both stay out. Status
 * groups are the superadmin panel's fixed tabs; SLA filters are SQL over the status history (OrderSla's rules), so the
 * list, the counts, the attention rows and the SLA watcher agree.
 */
final class AdminOrderQuery
{
    public const TIMEZONE = 'Asia/Tashkent';

    /** @var array<string, list<string>> group => statuses; `active` is every status outside done and canceled */
    public const GROUPS = [
        'active' => ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivery'],
        'new' => ['new'],
        'kitchen' => ['accepted', 'preparing', 'ready'],
        'en_route' => ['courier_assigned', 'on_the_way', 'delivery'],
        'done' => ['delivered', 'refunded'],
        'canceled' => ['canceled', 'rejected'],
    ];

    /** Groups whose count is limited to today when no from/to is given (they grow without end). */
    public const FINAL_GROUPS = ['done', 'canceled'];

    private const ACCEPTED_AT = "(select min(h.created_at) from order_status_histories h where h.order_id = orders.id and h.status = 'accepted')";

    private const EN_ROUTE_AT = "(select min(h.created_at) from order_status_histories h where h.order_id = orders.id and h.status in ('on_the_way', 'delivery'))";

    private const READY_AT = "(select max(h.created_at) from order_status_histories h where h.order_id = orders.id and h.status = 'ready')";

    /**
     * @return list<string>
     */
    public static function channels(): array
    {
        return [OrderChannel::TELEGRAM->value, OrderChannel::MOBILE->value];
    }

    public static function isMarketplace(Order $order): bool
    {
        return in_array($order->channel->value, self::channels(), true);
    }

    public static function groupOf(string $status): string
    {
        foreach (['new', 'kitchen', 'en_route', 'done', 'canceled'] as $group) {
            if (in_array($status, self::GROUPS[$group], true)) {
                return $group;
            }
        }

        return 'active';
    }

    /**
     * @return Builder<Order>
     */
    public function base(): Builder
    {
        return Order::query()->whereIn('orders.channel', self::channels());
    }

    /**
     * The first `accepted` and the first on_the_way / delivery time of each row (OrderSla::current reads them).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function withStageTimes(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('orders.*');
        }

        return $query
            ->selectRaw(self::ACCEPTED_AT . ' as sla_accepted_at')
            ->selectRaw(self::EN_ROUTE_AT . ' as sla_en_route_at');
    }

    /**
     * The row's stage times as read by withStageTimes().
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public static function stageTimes(Order $order): array
    {
        $read = function (mixed $value): ?Carbon {
            return $value !== null && $value !== '' ? Carbon::parse((string) $value) : null;
        };

        return [$read($order->getAttribute('sla_accepted_at')), $read($order->getAttribute('sla_en_route_at'))];
    }

    /**
     * Filters of GET /admin/orders (already validated): status (group), channel, region_id, store_id, sla, from, to, q.
     * `$phoneFragments`: whether `q` may match a part of the customer's phone (a member with admin.pii.view); without
     * it only the whole number matches, so a masked phone cannot be guessed digit by digit.
     *
     * @param  Builder<Order>  $query
     * @param  array<string, mixed>  $filters
     * @param  array{accept: int, prepare: int, deliver: int}  $limits
     * @return Builder<Order>
     */
    public function filter(Builder $query, array $filters, array $limits, bool $withGroup = true, bool $phoneFragments = false): Builder
    {
        if ($withGroup && !empty($filters['status'])) {
            $query->whereIn('orders.status', self::GROUPS[(string) $filters['status']]);
        }
        if (!empty($filters['channel'])) {
            $query->where('orders.channel', (string) $filters['channel']);
        }
        if (!empty($filters['store_id'])) {
            $query->where('orders.store_id', (int) $filters['store_id']);
        }
        if (!empty($filters['region_id'])) {
            $query->whereIn('orders.branch_id', Branch::query()->select('id')->where('region_id', (int) $filters['region_id']));
        }
        if (!empty($filters['from'])) {
            $query->where('orders.created_at', '>=', self::dayStart((string) $filters['from']));
        }
        if (!empty($filters['to'])) {
            $query->where('orders.created_at', '<', self::dayStart((string) $filters['to'])->addDay());
        }
        if (!empty($filters['sla'])) {
            $this->whereSla($query, (string) $filters['sla'], $limits);
        }
        if (!empty($filters['q'])) {
            $this->search($query, (string) $filters['q'], $phoneFragments);
        }

        return $query;
    }

    /**
     * Orders whose running stage is late (`late`), at 80–100% of its limit (`warning`), or either (`attention`).
     *
     * @param  Builder<Order>  $query
     * @param  array{accept: int, prepare: int, deliver: int}  $limits
     * @param  list<string>|null  $stages  only these stages
     * @return Builder<Order>
     */
    public function whereSla(Builder $query, string $state, array $limits, ?CarbonInterface $now = null, ?array $stages = null): Builder
    {
        $now = Carbon::instance($now ?? now());
        $stages ??= [OrderSlaSettings::ACCEPT, OrderSlaSettings::PREPARE, OrderSlaSettings::DELIVER];

        // Stage start bounds: late = started at or before now - limit; warning = after that, at or before now - 80%
        $bounds = function (string $stage) use ($limits, $now, $state): array {
            $late = $now->copy()->subMinutes($limits[$stage]);
            $warning = $now->copy()->subSeconds((int) round($limits[$stage] * 60 * OrderSlaSettings::WARNING_RATIO));

            return match ($state) {
                OrderSla::LATE => [null, $late],
                OrderSla::WARNING => [$late, $warning],
                default => [null, $warning],
            };
        };
        $between = function (Builder $query, string $expression, array $bounds): void {
            [$after, $atOrBefore] = $bounds;
            $query->whereRaw("{$expression} <= ?", [$atOrBefore]);
            if ($after !== null) {
                $query->whereRaw("{$expression} > ?", [$after]);
            }
        };

        return $query->where(function (Builder $query) use ($stages, $bounds, $between) {
            if (in_array(OrderSlaSettings::ACCEPT, $stages, true)) {
                $query->orWhere(function (Builder $query) use ($bounds, $between) {
                    $this->whereAwaitsStore($query);
                    $between($query, 'orders.created_at', $bounds(OrderSlaSettings::ACCEPT));
                });
            }
            if (in_array(OrderSlaSettings::PREPARE, $stages, true)) {
                $query->orWhere(function (Builder $query) use ($bounds, $between) {
                    $query->whereIn('orders.status', ['accepted', 'preparing']);
                    $between($query, 'coalesce(' . self::ACCEPTED_AT . ', orders.created_at)', $bounds(OrderSlaSettings::PREPARE));
                });
            }
            if (in_array(OrderSlaSettings::DELIVER, $stages, true)) {
                $query->orWhere(function (Builder $query) use ($bounds, $between) {
                    $query->whereIn('orders.status', ['on_the_way', 'delivery'])->where('orders.order_type', 'delivery');
                    $between($query, 'coalesce(' . self::EN_ROUTE_AT . ', orders.updated_at)', $bounds(OrderSlaSettings::DELIVER));
                });
            }
        });
    }

    /**
     * New orders the store has to accept (OrderSla::awaitsStore).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function whereAwaitsStore(Builder $query): Builder
    {
        return $query->where('orders.status', 'new')
            ->where(fn (Builder $query) => $query
                ->whereNotIn('orders.payment_method', OrderSla::onlineMethods())
                ->orWhere('orders.payment_status', 'paid'));
    }

    /**
     * Delivery orders ready in the kitchen with no courier yet (and when they became ready, as `sla_ready_at`).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function whereReadyWithoutCourier(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('orders.*');
        }

        return $query->where('orders.status', 'ready')
            ->where('orders.order_type', 'delivery')
            ->whereNull('orders.courier_id')
            ->selectRaw('coalesce(' . self::READY_AT . ', orders.updated_at) as sla_ready_at');
    }

    /**
     * `q`: the short code (with or without #), the start of the uuid, or the customer's phone: with $phoneFragments any
     * 4+ of its digits, otherwise only the whole number (+998 and nine digits, or the nine digits; spaces and dashes
     * ignored).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function search(Builder $query, string $q, bool $phoneFragments = false): Builder
    {
        $code = ltrim(trim($q), '#');
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($code));
        $uuidPrefix = preg_match('/^[0-9a-fA-F-]{4,36}$/', $code) === 1 ? strtolower($code) : null;
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        $phone = $phoneFragments ? null : UzPhone::normalize($code);

        return $query->where(function (Builder $query) use ($like, $uuidPrefix, $digits, $phoneFragments, $phone) {
            $query->whereRaw('lower(orders.short_code) like ?', [$like . '%']);
            if ($uuidPrefix !== null) {
                $query->orWhereRaw('cast(orders.uuid as text) like ?', [$uuidPrefix . '%']);
            }
            if ($phoneFragments && strlen($digits) >= 4) {
                $query->orWhereIn('orders.user_id', User::query()->select('id')->where('phone', 'like', '%' . $digits . '%'));
            } elseif ($phone !== null) {
                $query->orWhereIn('orders.user_id', User::query()->select('id')->whereIn('phone', [$phone, ltrim($phone, '+')]));
            }
        });
    }

    /**
     * Orders per status group under the same filters (except the group). Done and canceled count today only
     * (Asia/Tashkent) when no from/to is given.
     *
     * @param  array<string, mixed>  $filters
     * @param  array{accept: int, prepare: int, deliver: int}  $limits
     * @return array<string, int>
     */
    public function counts(array $filters, array $limits, bool $phoneFragments = false): array
    {
        $today = self::dayStart(now(self::TIMEZONE)->toDateString());
        $bounded = !empty($filters['from']) || !empty($filters['to']);

        $selects = [];
        $bindings = [];
        foreach (self::GROUPS as $group => $statuses) {
            $in = implode(', ', array_fill(0, count($statuses), '?'));
            $condition = "orders.status in ({$in})";
            $bindings = [...$bindings, ...$statuses];
            if (in_array($group, self::FINAL_GROUPS, true) && !$bounded) {
                $condition .= ' and orders.created_at >= ?';
                $bindings[] = $today;
            }
            $selects[] = "count(*) filter (where {$condition}) as \"{$group}\"";
        }

        $query = $this->filter($this->base(), $filters, $limits, false, $phoneFragments);
        $row = $query->toBase()->selectRaw(implode(', ', $selects), $bindings)->first();

        $counts = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $counts[$group] = (int) ($row->{$group} ?? 0);
        }

        return $counts;
    }

    /**
     * The start of a day in Asia/Tashkent, in the application's time zone (how the timestamps are stored).
     */
    public static function dayStart(string $date): Carbon
    {
        return Carbon::parse($date, self::TIMEZONE)->startOfDay()->setTimezone(config('app.timezone', 'UTC'));
    }
}
