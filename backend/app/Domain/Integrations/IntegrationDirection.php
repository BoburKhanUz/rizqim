<?php

namespace App\Domain\Integrations;

/**
 * P4-31: which way a connection carries orders (docs/PHASE5_PLAN.md 3): A — a RIZQIM marketplace order goes to the
 * venue's own POS; B — an aggregator's order (Yandex Eats, Uzum Tezkor) comes to Rizqim POS.
 */
enum IntegrationDirection: string
{
    case RIZQIM_TO_POS = 'rizqim_to_pos';
    case AGGREGATOR_TO_RIZQIM = 'aggregator_to_rizqim';

    public function label(): string
    {
        return match ($this) {
            self::RIZQIM_TO_POS => "RIZQIM → joyning POS'i",
            self::AGGREGATOR_TO_RIZQIM => 'Agregator → Rizqim POS',
        };
    }
}
