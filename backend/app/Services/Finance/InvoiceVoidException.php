<?php

namespace App\Services\Finance;

use RuntimeException;

/**
 * Review W2: a void SubscriptionService::voidInvoice refuses: `reason` is the admin API's machine code (invoice_paid,
 * four_eyes), `status` the HTTP status.
 */
final class InvoiceVoidException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
