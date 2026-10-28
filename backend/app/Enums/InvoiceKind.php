<?php

namespace App\Enums;

/**
 * P4-21: what a subscription invoice bills. monthly: a month that ended (base fee, extra devices, discount; for a
 * yearly subscription only its extra devices); yearly: a yearly term in advance; upgrade: the prorated difference of an
 * immediate upgrade of a yearly subscription.
 */
enum InvoiceKind: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case UPGRADE = 'upgrade';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Oylik',
            self::YEARLY => 'Yillik',
            self::UPGRADE => "Tarifni oshirish",
        };
    }
}
