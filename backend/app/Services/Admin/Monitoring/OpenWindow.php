<?php

namespace App\Services\Admin\Monitoring;

use Carbon\CarbonImmutable;

/**
 * "Open now" for a terminal (TerminalBusinessHours): `since` is when the current business period began, or null for a
 * round-the-clock branch or store (no period to measure from: callers use the terminal's own last sign of life).
 */
final class OpenWindow
{
    public function __construct(
        public readonly ?CarbonImmutable $since,
    ) {}
}
