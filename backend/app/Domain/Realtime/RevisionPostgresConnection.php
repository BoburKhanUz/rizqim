<?php

namespace App\Domain\Realtime;

use Illuminate\Database\PostgresConnection;

/**
 * Wave M2: the application's Postgres connection, unchanged except that its query builder is RevisionQueryBuilder,
 * which touches the change tokens of every row written (RealtimeServiceProvider registers it for the pgsql driver).
 */
class RevisionPostgresConnection extends PostgresConnection
{
    public function query()
    {
        return new RevisionQueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }
}
