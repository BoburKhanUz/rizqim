<?php

namespace App\Domain\Pos\Contracts;

/**
 * How a POS bill is settled (payments.provider for POS payments). `prepayment` applies a booking deposit already
 * receipted as an advance; `account` closes the bill on credit (nasiya) to a customer account. P2P to a personal card
 * is deliberately not a tender; `voucher` (P6-43)
 * redeems a gift certificate sold earlier as an advance, so like `prepayment` it is the prepayment part of the sale receipt (docs/research: tax risk).
 */
enum PosTender: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case QR = 'qr';
    case PREPAYMENT = 'prepayment';
    case ACCOUNT = 'account';
    // P6-43: a gift certificate's balance (receipted as an advance when it was sold; the prepayment part of the sale)
    case VOUCHER = 'voucher';

    /**
     * Money that physically lands in the till during this shift.
     */
    public function isCashDrawer(): bool
    {
        return $this === self::CASH;
    }
}
