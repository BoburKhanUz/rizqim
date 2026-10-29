<?php

namespace App\Services\Admin\System;

use RuntimeException;

/**
 * P4-31: a Tizim action the rules refuse (deleting a region that branches point at, a secret sent to the plain settings
 * form, an order that is not the full list...): `reason` is the machine code the admin API answers with (code and
 * data.reason), `status` the HTTP status, `data` extra fields.
 */
final class SystemAdminException extends RuntimeException
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
