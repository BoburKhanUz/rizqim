<?php

namespace App\Services\Admin\Marketing;

use RuntimeException;

/**
 * P4-31: a Marketing action the rules refuse (a code that was used cannot change its value, a category cannot be its
 * own parent...): `reason` is the machine code the admin API answers with (code and data.reason), `status` the HTTP
 * status, `data` extra fields.
 */
final class MarketingException extends RuntimeException
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
