<?php

namespace App\Domain\Fiscal\Gateways;

/**
 * What a provider says about a smart kassa (SmartKassaGateway::kassaStatus).
 */
final readonly class SmartKassaStatus
{
    public function __construct(
        public bool $registered,
        public ?string $kassaId,
        public ?string $message = null,
    ) {}

    /**
     * @return array{registered: bool, kassa_id: string|null, message: string|null}
     */
    public function toArray(): array
    {
        return ['registered' => $this->registered, 'kassa_id' => $this->kassaId, 'message' => $this->message];
    }
}
