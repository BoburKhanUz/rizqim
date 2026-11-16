<?php

namespace App\Domain\Realtime\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;

/** The Reverb (Pusher protocol) broadcaster, with `rev` stamped on every payload (RevisionStamp) */
class RevisionPusherBroadcaster extends PusherBroadcaster
{
    public function broadcast(array $channels, $event, array $payload = [])
    {
        parent::broadcast($channels, $event, RevisionStamp::apply($channels, $payload));
    }
}
