<?php

namespace App\Domain\Integrations;

/**
 * P4-31: a connection's state. `paused` is set by the team (Integratsiyalar, with a reason); `disconnected` by the
 * health check when the outside system stops answering (phase 5).
 */
enum IntegrationConnectionStatus: string
{
    case CONFIGURING = 'configuring';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case DISCONNECTED = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::CONFIGURING => 'Sozlanmoqda',
            self::ACTIVE => 'Faol',
            self::PAUSED => "To'xtatilgan",
            self::DISCONNECTED => 'Uzilgan',
        };
    }
}
