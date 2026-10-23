<?php

namespace App\Domain\Pos\Sync;

/**
 * What became of an offline operation: applied; applied but a manager should look (conflict); not applied (rejected,
 * "Sinxronlash xatolari").
 */
enum PosSyncStatus: string
{
    case APPLIED = 'applied';
    case CONFLICT = 'conflict';
    case REJECTED = 'rejected';
}
