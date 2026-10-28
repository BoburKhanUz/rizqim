<?php

namespace App\Services\Finance\Adjustments;

use RuntimeException;

/**
 * P4-23: an adjustment the rules refuse: `reason` is the admin API's machine code, `status` the HTTP status.
 */
final class AdjustmentException extends RuntimeException
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
