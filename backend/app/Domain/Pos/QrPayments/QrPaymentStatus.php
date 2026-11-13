<?php

namespace App\Domain\Pos\QrPayments;

/**
 * pos_qr_payments.status: `pending` while the customer may pay it; then `paid` (the provider confirmed it and the bill
 * took a `qr` payment), `cancelled` (the cashier, a newer QR, a bill change, or the provider's own cancel), `expired`
 * (its time ran out before anyone paid) or `refunded` (a refund reversed it in full through the provider).
 */
enum QrPaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';
}
