<?php

namespace App\Services\Finance;

use RuntimeException;
use Throwable;

/**
 * Review H1: a delivered order's ledger booking after its late payment failed and not even the retry marker
 * (settlement_failed_at) could be written (SettlementRecoveryService::bookAfterPayment). Thrown out of the listener
 * so the failure is never logged-and-forgotten there; the booking's own error is the previous exception.
 */
class SettlementNotMarked extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Ledger booking failed and could not be marked for a retry: ' . $previous->getMessage(), 0, $previous);
    }
}
