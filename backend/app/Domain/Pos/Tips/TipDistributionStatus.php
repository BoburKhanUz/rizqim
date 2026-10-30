<?php

namespace App\Domain\Pos\Tips;

/**
 * P6-22: a pool split is `open` (recomputed on request) until a manager closes it (`closed`: frozen).
 */
enum TipDistributionStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
