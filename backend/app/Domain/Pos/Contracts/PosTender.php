<?php

namespace App\Domain\Pos\Contracts;

/**
 * How a POS bill is settled (payments.provider for POS payments). `prepayment` applies a booking deposit already
 * receipted as an advance; `account` closes the bill on credit (nasiya) to a customer account. P2P to a personal card
 * is deliberately not a tender (docs/research: tax risk).
 */
enum PosTender: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case QR = 'qr';
    case PREPAYMENT = 'prepayment';
    case ACCOUNT = 'account';

    /**
     * Money that physically lands in the till during this shift.
     */
    public function isCashDrawer(): bool
    {
        return $this === self::CASH;
    }
}
