<?php

namespace App\Events\Broadcast;

/**
 * ops.ping (P4-11): a test message on `platform.ops`, proving the admin app's live connection end to end.
 */
class PlatformOpsPing extends PlatformOpsBroadcast
{
    public string $sentAt;

    public function __construct(public string $message = 'ping')
    {
        $this->sentAt = now()->toIso8601String();
    }

    public function broadcastAs(): string
    {
        return 'ops.ping';
    }

    /**
     * @return array{message: string, sent_at: string}
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message, 'sent_at' => $this->sentAt];
    }
}
