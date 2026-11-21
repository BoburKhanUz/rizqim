<?php

namespace App\Domain\Pos\Sync;

use RuntimeException;

/**
 * Wave M5 S4 (docs/scale/M5_DESIGN.md §5): a rescue file refused whole, before anything of it was applied — it is not
 * such a file, its version is unknown, its MAC is wrong, or its till is not the caller's. `reason` for the client,
 * the message in Uzbek for the person.
 */
final class PosSyncImportException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
