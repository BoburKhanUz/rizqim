<?php

namespace App\Services\Admin\Customers;

use RuntimeException;

/**
 * P4-31: a Mijozlar va yordam action the rules refuse (a platform account, already blocked, a resolved complaint, no
 * Telegram to reply to...): `reason` is the machine code the admin API answers with (code and data.reason), `status`
 * the HTTP status, `data` extra fields.
 */
final class CustomerAdminException extends RuntimeException
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
