<?php

namespace App\Domain\Realtime\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\LogBroadcaster;

/** BROADCAST_CONNECTION=log, with the same `rev` stamp as Reverb (what a client would receive) */
class RevisionLogBroadcaster extends LogBroadcaster
{
    public function broadcast(array $channels, $event, array $payload = [])
    {
        parent::broadcast($channels, $event, RevisionStamp::apply($channels, $payload));
    }
}
