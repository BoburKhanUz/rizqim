<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** P6-13: the status this event announces (the order is read again when a queued listener handles it). */
    public ?string $status;

    /**
     * Review P6 C#L5: the order's status-history row of this move (its latest when the event is made, inside the
     * move's transaction): SendCustomerStatusAlert skips the alert only when the same transaction moved it again.
     */
    public ?int $historyId;

    /**
     * @param  bool  $quiet  P2-33: a kitchen correction (a line recalled from a ready order, and the order becoming
     *                       ready again after it): the customer is not told again (SendCustomerStatusAlert)
     */
    public function __construct(
        public \App\Models\Order $order,
        public bool $quiet = false,
    ) {
        $this->status = $order->status->value;
        $id = $order->getKey() !== null
            ? \App\Models\OrderStatusHistory::where('order_id', $order->getKey())->max('id')
            : null;
        $this->historyId = $id !== null ? (int) $id : null;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
