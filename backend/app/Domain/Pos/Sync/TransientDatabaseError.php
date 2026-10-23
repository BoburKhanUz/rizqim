<?php

namespace App\Domain\Pos\Sync;

use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * Review P3-35: whether a failure is the database's for the moment (a deadlock, a serialization failure, a lock that
 * timed out, a lost connection) — the offline operation stays in the till's queue and comes again — rather than the
 * operation's own content, which would fail the same way every time.
 */
final class TransientDatabaseError
{
    use DetectsConcurrencyErrors;
    use DetectsLostConnections;

    /** SQLSTATE classes and codes that pass: connection exceptions, lock not available, query cancelled, admin shutdown */
    private const STATES = ['08', '40', '55P03', '57014', '57P01', '57P02', '57P03', 'HYT00'];

    public static function is(Throwable $e): bool
    {
        return (new self())->check($e);
    }

    private function check(Throwable $e): bool
    {
        if (!$e instanceof QueryException && !$e instanceof PDOException) {
            return false;
        }
        if ($this->causedByLostConnection($e) || $this->causedByConcurrencyError($e)) {
            return true;
        }

        $state = (string) ($e->errorInfo[0] ?? $e->getCode());
        foreach (self::STATES as $prefix) {
            if (str_starts_with($state, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
