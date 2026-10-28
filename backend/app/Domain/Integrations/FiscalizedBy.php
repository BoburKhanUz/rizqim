<?php

namespace App\Domain\Integrations;

/**
 * P4-31: orders.fiscalized_by — who issued an order's fiscal receipt (docs/PHASE5_PLAN.md 3.1, 7). NULL on the order
 * means "not decided, fiscal review needed". Nothing sets or reads it before phase 5.
 */
enum FiscalizedBy: string
{
    case VENUE_POS = 'venue_pos';
    case PAYMENT_PROVIDER = 'payment_provider';
    case AGGREGATOR = 'aggregator';
    // Reserved: RIZQIM as a registered marketplace issuing receipts itself
    case RIZQIM_MARKETPLACE = 'rizqim_marketplace';

    public function label(): string
    {
        return match ($this) {
            self::VENUE_POS => 'Joyning kassasi',
            self::PAYMENT_PROVIDER => "To'lov tizimi",
            self::AGGREGATOR => 'Agregator',
            self::RIZQIM_MARKETPLACE => 'RIZQIM marketpleysi',
        };
    }
}
