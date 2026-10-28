<?php

namespace App\Services\Finance\Payouts;

use RuntimeException;

/**
 * P4-23: a payout decision the rules refuse: `reason` is the admin API's machine code (code and data.reason), `status`
 * the HTTP status, `data` extra fields.
 */
final class PayoutException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $status = 422,
        public readonly array $data = [],
    ) {
        parent::__construct($message);
    }
}
