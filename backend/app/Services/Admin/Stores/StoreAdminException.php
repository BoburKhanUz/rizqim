<?php

namespace App\Services\Admin\Stores;

use RuntimeException;

/**
 * P4-22: an Ovqatxonalar action the rules refuse (a stage move with an unfinished checklist, a phone already taken,
 * a status that cannot change from here...): `reason` is the machine code the admin API answers with (code and
 * data.reason), `status` the HTTP status, `data` extra fields.
 */
final class StoreAdminException extends RuntimeException
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
