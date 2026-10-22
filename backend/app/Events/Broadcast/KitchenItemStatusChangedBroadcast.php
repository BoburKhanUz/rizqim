<?php

namespace App\Events\Broadcast;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * kitchen.item_status_changed (P2-11): a cook bumped an order line "ready" on the kitchen display, or recalled it.
 * Goes to the order's staff channels (store, branch, pos-branch). Like every OrderBroadcast: queued on `broadcasts`,
 * after commit, tried once, rescued; ids and kitchen state only (no names, phones, addresses or prices).
 */
class KitchenItemStatusChangedBroadcast extends OrderBroadcast
{
    public int $itemId;

    public string $kitchenStatus;

    public ?string $itemReadyAt;

    public int $itemsTotal;

    public int $itemsReady;

    public function __construct(Order $order, OrderItem $item)
    {
        parent::__construct($order);

        $this->itemId = (int) $item->id;
        $this->kitchenStatus = $item->kitchen_status->value;
        $this->itemReadyAt = $item->ready_at?->toJSON();

        // P3-12: a voided line is not counted; a served one counts as ready
        $counts = $order->items()->toBase()
            ->selectRaw("count(*) filter (where kitchen_status <> 'void') as total")
            ->selectRaw("count(*) filter (where kitchen_status in ('ready', 'served')) as ready")
            ->first();
        $this->itemsTotal = (int) ($counts->total ?? 0);
        $this->itemsReady = (int) ($counts->ready ?? 0);
    }

    public function broadcastAs(): string
    {
        return 'kitchen.item_status_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->staffChannels();
    }

    /**
     * The order part is OrderBroadcast's; plus the line and the order's kitchen progress.
     *
     * @return array{order: array{uuid: string, status: string, store_id: int, branch_id: int|null, courier_id: int|null, updated_at: string|null}, item: array{id: int, kitchen_status: string, ready_at: string|null}, items_total: int, items_ready: int}
     */
    public function broadcastWith(): array
    {
        return parent::broadcastWith() + [
            'item' => [
                'id' => $this->itemId,
                'kitchen_status' => $this->kitchenStatus,
                'ready_at' => $this->itemReadyAt,
            ],
            'items_total' => $this->itemsTotal,
            'items_ready' => $this->itemsReady,
        ];
    }
}
