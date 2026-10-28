<?php

namespace App\Services\Admin\Integrations\Attention;

use App\Domain\Integrations\IntegrationEvent;
use App\Enums\AdminPermission;
use App\Models\Order;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Integrations\StuckIntegrationOrders;
use App\Services\Admin\Today\Attention\TodayAttentionSource;

/**
 * P4-31: "Bugun → Diqqat talab qiladi" for orders an outside POS has not confirmed (StuckIntegrationOrders). Zero rows
 * until phase 5 adapters write the integration journal; the count is one indexed query.
 */
class StuckIntegrationOrdersSource extends TodayAttentionSource
{
    public function __construct(
        private readonly StuckIntegrationOrders $stuck,
    ) {}

    public function permission(): AdminPermission
    {
        return AdminPermission::INTEGRATIONS_VIEW;
    }

    public function kind(): string
    {
        return 'integration_order_stuck';
    }

    public function label(): string
    {
        return 'Tashqi POS qabul qilmagan buyurtmalar';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function collect(int $limit): array
    {
        $query = $this->stuck->query();
        $count = (clone $query)->count();
        if ($count === 0) {
            return ['count' => 0, 'items' => []];
        }

        $events = (clone $query)->with(['connection.store:id,name'])->orderBy('integration_events.created_at')->orderBy('integration_events.id')->limit($limit)->get();
        $orders = Order::query()->whereIn('id', $events->pluck('subject_id')->filter()->all())->get(['id', 'uuid', 'short_code'])->keyBy('id');

        $items = $events->map(function (IntegrationEvent $event) use ($orders) {
            $order = $orders->get((int) $event->subject_id);
            $connection = $event->connection;

            return new AttentionItem(
                kind: $this->kind(),
                severity: 'critical',
                title: $order?->short_code ? "Buyurtma {$order->short_code}" : 'Buyurtma',
                subtitle: $this->join([$connection?->provider->label(), $connection?->store?->name, $event->status->label(), $this->time($event->created_at)]),
                href: '/integrations?tab=stuck',
                since: $this->iso($event->created_at),
                storeId: $connection?->store_id,
            );
        })->values()->all();

        return ['count' => $count, 'items' => $items];
    }
}
