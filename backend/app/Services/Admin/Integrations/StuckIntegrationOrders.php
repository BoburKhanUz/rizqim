<?php

namespace App\Services\Admin\Integrations;

use App\Domain\Integrations\IntegrationEvent;
use App\Domain\Integrations\IntegrationEventDirection;
use App\Domain\Integrations\IntegrationEventStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * P4-31 Integratsiyalar → Qotib qolganlar (docs/PHASE5_PLAN.md 4.4): an order RIZQIM pushed to an outside POS that the
 * other side has not confirmed for STUCK_AFTER_MINUTES, while the order is still open. Read from the journal: the
 * newest `order.push` of an order on a connection, outbound, not acknowledged. Empty until phase 5 adapters write the
 * journal; the "Bugun" attention source (StuckIntegrationOrdersSource) counts the same rows.
 */
final class StuckIntegrationOrders
{
    public const STUCK_AFTER_MINUTES = 2;

    public const LIMIT = 100;

    public const ROW = 'array{event_uuid: string, connection: array{uuid: string, provider: string, provider_label: string, store: array{id: int, name: string}|null}|null, order: array{uuid: string, code: string|null, status: string}|null, status: string, status_label: string, attempts: int, error: string|null, since: string|null, waiting_minutes: int}';

    private const CLOSED = [OrderStatus::DELIVERED, OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED];

    /**
     * @return Builder<IntegrationEvent>
     */
    public function query(): Builder
    {
        return IntegrationEvent::query()
            ->where('integration_events.type', IntegrationEvent::TYPE_ORDER_PUSH)
            ->where('integration_events.direction', IntegrationEventDirection::OUTBOUND->value)
            ->where('integration_events.subject_type', 'order')
            ->whereIn('integration_events.status', array_map(fn (IntegrationEventStatus $status) => $status->value, IntegrationEventStatus::unconfirmed()))
            ->where('integration_events.created_at', '<=', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            // Only the newest push of the order on that connection counts (a retry that went through is not stuck)
            ->whereNotExists(fn (QueryBuilder $newer) => $newer->from('integration_events as newer')
                ->whereColumn('newer.integration_connection_id', 'integration_events.integration_connection_id')
                ->whereColumn('newer.subject_id', 'integration_events.subject_id')
                ->where('newer.subject_type', 'order')
                ->where('newer.type', IntegrationEvent::TYPE_ORDER_PUSH)
                ->whereColumn('newer.id', '>', 'integration_events.id'))
            ->whereExists(fn (QueryBuilder $open) => $open->from('orders')
                ->whereColumn('orders.id', 'integration_events.subject_id')
                ->whereNotIn('orders.status', array_map(fn (OrderStatus $status) => $status->value, self::CLOSED)));
    }

    /**
     * The oldest first, at most LIMIT rows.
     *
     * @return array{count: int, data: list<array<string, mixed>>}
     */
    public function list(?int $limit = null): array
    {
        $query = $this->query();
        $events = (clone $query)->with(['connection.store:id,name'])->orderBy('integration_events.created_at')->orderBy('integration_events.id')
            ->limit($limit ?? self::LIMIT)->get();
        $orders = Order::query()->whereIn('id', $events->pluck('subject_id')->filter()->all())->get(['id', 'uuid', 'short_code', 'status'])->keyBy('id');

        $rows = $events->map(function (IntegrationEvent $event) use ($orders) {
            $connection = $event->connection;
            $order = $orders->get((int) $event->subject_id);

            return [
                'event_uuid' => $event->uuid,
                'connection' => $connection ? [
                    'uuid' => $connection->uuid,
                    'provider' => $connection->provider->value,
                    'provider_label' => $connection->provider->label(),
                    'store' => $connection->store ? ['id' => (int) $connection->store->id, 'name' => (string) $connection->store->name] : null,
                ] : null,
                'order' => $order ? ['uuid' => (string) $order->uuid, 'code' => $order->short_code, 'status' => $order->status->value] : null,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'attempts' => (int) $event->attempts,
                'error' => IntegrationConnections::redactText($event->error),
                'since' => $event->created_at?->toIso8601String(),
                'waiting_minutes' => $event->created_at ? (int) floor($event->created_at->diffInMinutes(now(), true)) : 0,
            ];
        })->values()->all();

        return ['count' => $query->count(), 'data' => $rows];
    }
}
