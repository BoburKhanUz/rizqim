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
     * @param  bool  $quiet  P2-33: a kitchen correction (a line recalled from a ready order, and the order becoming
     *                       ready again after it): the customer is not told again (SendCustomerStatusAlert)
     */
    public function __construct(
        public \App\Models\Order $order,
        public bool $quiet = false,
    ) {
        $this->status = $order->status->value;
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
