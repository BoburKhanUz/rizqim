<?php

namespace App\Services\Admin\Export;

/**
 * P4-31: a finished export: the temporary file (the controller sends it and deletes it), its download name and what it holds.
 */
final class ExportResult
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly int $rows,
        public readonly bool $truncated,
    ) {}
}
