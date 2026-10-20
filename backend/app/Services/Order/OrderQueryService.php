<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OrderQueryService
{
    /** Orders that are finished for the dashboards: they only show up in the paginated history. */
    public const TERMINAL_STATUSES = [
        OrderStatus::DELIVERED,
        OrderStatus::CANCELED,
        OrderStatus::REJECTED,
        OrderStatus::REFUNDED,
    ];

    /** Most active orders one response carries; beyond that only the newest are sent (meta.truncated). */
    public const ACTIVE_CAP = 200;

    public const HISTORY_DEFAULT_PER_PAGE = 20;
    public const HISTORY_MAX_PER_PAGE = 50;

    /**
     * The orders a user's app lists, newest first: a courier's own deliveries, the store's orders for its owner,
     * the branch's (or store's) for a manager, and anyone else's own orders. Unbounded: kept for app versions
     * that do not send `scope` yet.
     *
     * @return Collection<int, Order>
     */
    public function visibleTo(User $user): Collection
    {
        return $this->scopedTo($user)->latest()->get();
    }

    /**
     * The user's orders that are still in progress (any non-terminal status), newest first, at most ACTIVE_CAP.
     *
     * @return array{orders: Collection<int, Order>, truncated: bool}
     */
    public function activeFor(User $user, int $cap = self::ACTIVE_CAP): array
    {
        // Listed as IN (non-terminal statuses), not NOT IN (terminal), so the (…, status, created_at) indexes apply
        $active = array_filter(
            OrderStatus::cases(),
            fn (OrderStatus $status) => !in_array($status, self::TERMINAL_STATUSES, true),
        );

        $orders = $this->scopedTo($user)
            ->whereIn('orders.status', array_map(fn (OrderStatus $status) => $status->value, $active))
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->limit($cap + 1)
            ->get();

        return [
            'orders' => $orders->take($cap)->values(),
            'truncated' => $orders->count() > $cap,
        ];
    }

    /**
     * One page of the user's finished orders, keyset-paginated by (created_at desc, id desc): `$after` is the
     * decoded cursor of the previous page's last order, and next_cursor is null on the last page.
     *
     * @param  array{created_at: string, id: int}|null  $after
     * @return array{orders: Collection<int, Order>, next_cursor: string|null}
     */
    public function historyFor(User $user, int $perPage, ?array $after = null): array
    {
        $query = $this->scopedTo($user)
            ->whereIn('orders.status', array_map(fn (OrderStatus $status) => $status->value, self::TERMINAL_STATUSES));

        if ($after !== null) {
            // A row comparison, so PostgreSQL seeks the (…, created_at, id) index instead of filtering newer rows
            $query->whereRaw('(orders.created_at, orders.id) < (?, ?)', [$after['created_at'], $after['id']]);
        }

        $orders = $query
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->limit($perPage + 1)
            ->get();

        $page = $orders->take($perPage)->values();
        $last = $page->last();

        return [
            'orders' => $page,
            'next_cursor' => $orders->count() > $perPage && $last ? $this->encodeCursor($last) : null,
        ];
    }

    /**
     * The opaque history cursor pointing just past this order.
     */
    public function encodeCursor(Order $order): string
    {
        $payload = json_encode([
            // The stored value as is, so no timezone or precision conversion can shift the boundary
            't' => (string) $order->getRawOriginal('created_at'),
            'i' => (int) $order->getKey(),
        ]);

        return rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
    }

    /**
     * A cursor made by encodeCursor(), or null when the string is not one.
     *
     * @return array{created_at: string, id: int}|null
     */
    public function decodeCursor(string $cursor): ?array
    {
        if ($cursor === '' || strlen($cursor) > 200 || !preg_match('/^[A-Za-z0-9_-]+$/', $cursor)) {
            return null;
        }

        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data) || !is_string($data['t'] ?? null) || !is_int($data['i'] ?? null) || $data['i'] < 1) {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i:s.u'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $data['t']);
            // Rejects overflowing dates (2026-13-45) that PHP would roll over but PostgreSQL refuses, and years no order
            // can have (PostgreSQL refuses year 0000)
            $year = $date !== false ? (int) $date->format('Y') : 0;
            if ($date !== false && $date->format($format) === $data['t'] && $year >= 1970 && $year <= 9999) {
                return ['created_at' => $data['t'], 'id' => $data['i']];
            }
        }

        return null;
    }

    /**
     * The order behind a uuid, or null when it doesn't exist or this user may not see it (OrderPolicy::view),
     * so other stores'/branches'/couriers' orders look the same as missing ones.
     */
    public function findVisible(User $user, string $uuid): ?Order
    {
        if (!Str::isUuid($uuid)) {
            return null;
        }

        $order = Order::where('uuid', $uuid)->first();

        return $order && Gate::forUser($user)->allows('view', $order) ? $order : null;
    }

    /**
     * The orders this user may list, with the relations their app shows (eager loaded: a fixed number of queries
     * however many orders there are). Every scope shares it, so items have the same shape everywhere.
     *
     * @return Builder<Order>
     */
    private function scopedTo(User $user): Builder
    {
        return match ($user->role) {
            // Unassigned orders carry customer contact details and couriers can't claim them
            UserRole::COURIER => Order::where('store_id', $user->store_id)
                ->where('courier_id', $user->id)
                ->with(['items.variant.product', 'user', 'branch']),
            UserRole::OWNER => Order::where('store_id', $user->store_id)
                ->with(['items.variant.product.category', 'user', 'courier', 'manager', 'branch', 'statusHistories']),
            UserRole::MANAGER => Order::where('store_id', $user->store_id)
                ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->with(['items.variant.product.category', 'user', 'courier', 'manager', 'branch', 'statusHistories']),
            default => $user->orders()->getQuery()
                ->with(['items.variant.product']),
        };
    }
}
