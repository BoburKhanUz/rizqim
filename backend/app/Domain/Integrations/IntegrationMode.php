<?php

namespace App\Domain\Integrations;

/**
 * P4-31: a sandbox connection never touches a real kitchen or real money (docs/PHASE5_PLAN.md 4.6).
 */
enum IntegrationMode: string
{
    case SANDBOX = 'sandbox';
    case PRODUCTION = 'production';

    public function label(): string
    {
        return match ($this) {
            self::SANDBOX => 'Sinov (sandbox)',
            self::PRODUCTION => 'Ishchi',
        };
    }
}
