<?php

namespace App\Services\Push;

final class PushOutcome
{
    public function __construct(
        public readonly PushStatus $status,
        public readonly ?string $detail = null,
    ) {}

    public function sent(): bool
    {
        return $this->status === PushStatus::SENT;
    }
}
