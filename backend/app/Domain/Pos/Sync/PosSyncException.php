<?php

namespace App\Domain\Pos\Sync;

use RuntimeException;

/**
 * An offline operation that cannot be applied: a stable `reason` for the till and an Uzbek message for the manager.
 */
final class PosSyncException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
