<?php

namespace App\Services\Admin\Orders\Attention;

use App\Enums\AdminPermission;
use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\OrderSlaSettings;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;

/**
 * P4-14: the shared part of the Buyurtmalar attention sources (Bugun → Diqqat talab qiladi): marketplace orders only,
 * admin.orders.view, the count and at most $limit rows over indexed columns (status, then the history by order id).
 */
abstract class OrderAttentionSource implements AttentionSource
{
    public function __construct(
        protected readonly AdminOrderQuery $orders,
        protected readonly OrderSlaSettings $settings,
    ) {}

    public function permission(): AdminPermission
    {
        return AdminPermission::ORDERS_VIEW;
    }

    /**
     * @return array{count: int, items: list<AttentionItem>}
     */
    public function collect(int $limit): array
    {
        $query = $this->query($this->settings->limits());
        $count = (clone $query)->count();
        if ($count === 0 || $limit < 1) {
            return ['count' => $count, 'items' => []];
        }

        $rows = $this->ordered($query)->with('store:id,name')->limit($limit)->get();

        return ['count' => $count, 'items' => $rows->map(fn (Order $order) => $this->item($order))->values()->all()];
    }

    /**
     * @param  array{accept: int, prepare: int, deliver: int}  $limits
     * @return Builder<Order>
     */
    abstract protected function query(array $limits): Builder;

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('orders.created_at')->orderBy('orders.id');
    }

    abstract protected function item(Order $order): AttentionItem;

    protected static function title(Order $order): string
    {
        return '#' . ($order->short_code ?? substr((string) $order->uuid, 0, 8)) . ($order->store ? ' · ' . $order->store->name : '');
    }

    protected static function minutesSince(?CarbonInterface $since): int
    {
        return $since !== null ? max(0, intdiv((int) $since->diffInSeconds(now(), false), 60)) : 0;
    }

    protected static function href(Order $order): string
    {
        return '/orders/' . $order->uuid;
    }
}
