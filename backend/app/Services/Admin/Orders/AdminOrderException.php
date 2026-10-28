<?php

namespace App\Services\Admin\Orders;

use RuntimeException;

/**
 * P4-14: an order intervention or refund request the rules refuse: `reason` is the machine code the admin API answers
 * with (code and data.reason), `status` the HTTP status, `data` extra fields (e.g. the refundable amount).
 */
final class AdminOrderException extends RuntimeException
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
