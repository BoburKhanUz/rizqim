<?php

namespace Tests\Concerns;

/**
 * M4: a test that does DDL itself (ALTER TABLE, CREATE/DROP, triggers on or off, partitions) runs as the schema owner
 * when DB_OWNER_USERNAME is set (CI job tests-app-role): its default connection, and with RefreshDatabase its
 * transaction, is `pgsql_owner`. Without DB_OWNER_USERNAME nothing changes (one role). Applied by tests/TestCase.php.
 */
trait UsesOwnerConnection {}
