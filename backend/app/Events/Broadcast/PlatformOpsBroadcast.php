<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * P4-11: an event for the RIZQIM team's live channel `platform.ops` (routes/channels.php: team members with
 * admin.today.view, signed with the admin token). Stage B sends new orders, problems and alerts through subclasses.
 * Like the order broadcasts: after the transaction commits, on the `broadcasts` queue, tried once, a failed push
 * reported and skipped. Payloads carry ids and statuses, not personal data: the admin app refetches through the API.
 */
abstract class PlatformOpsBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public const CHANNEL = 'platform.ops';

    public int $tries = 1;

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    abstract public function broadcastAs(): string;

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::CHANNEL)];
    }
}
