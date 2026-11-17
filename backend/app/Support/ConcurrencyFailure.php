<?php

namespace App\Support;

use Illuminate\Database\ConcurrencyErrorDetector;
use Throwable;

/**
 * Review M2 (LR-4): whether an exception is a deadlock or serialization failure (SQLSTATE 40P01 / 40001), looking
 * through its previous exceptions too: a failure in a nested transaction reaches the caller as Laravel's
 * DeadlockException, which drops the SQLSTATE code of the QueryException it wraps.
 */
final class ConcurrencyFailure
{
    public static function is(Throwable $e): bool
    {
        $detector = new ConcurrencyErrorDetector;
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($detector->causedByConcurrencyError($current) || in_array((string) $current->getCode(), ['40001', '40P01'], true)) {
                return true;
            }
        }

        return false;
    }
}
