<?php

namespace App\Domain\Fiscal;

/**
 * The receipt's payment form (VM 943 as amended from 22.10.2026): one form per receipt, `mixed` when a receipt is
 * paid by more than one form.
 */
enum FiscalPaymentForm: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case QR = 'qr';
    case MIXED = 'mixed';
}
