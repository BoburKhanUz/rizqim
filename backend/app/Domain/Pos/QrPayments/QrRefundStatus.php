<?php

namespace App\Domain\Pos\QrPayments;

/**
 * pos_qr_refund_attempts.status (review P6W4 C2): `requested` while the provider is asked (committed before the call),
 * then `refunded` (the provider reversed it: a PosRefund was recorded), `failed` (the provider refused: no refund) or
 * `unknown` (no answer, a timeout or a connection error: the money may have gone back; a retry or the minute job asks
 * the provider's status).
 */
enum QrRefundStatus: string
{
    case REQUESTED = 'requested';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
}
