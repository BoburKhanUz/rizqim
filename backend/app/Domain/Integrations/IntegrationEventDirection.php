<?php

namespace App\Domain\Integrations;

/**
 * P4-31: whether a journaled call came to RIZQIM (a webhook, an aggregator's request) or went out from it.
 */
enum IntegrationEventDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::INBOUND => 'Kiruvchi',
            self::OUTBOUND => 'Chiquvchi',
        };
    }
}
