<?php

namespace Tests\Feature;

use App\Support\Database\OwnerConnection;
use Illuminate\Support\Facades\DB;

/**
 * P4-11: audit_logs is append-only (a PostgreSQL trigger refuses UPDATE and DELETE). Tests that commit rows outside a
 * transaction (the pcntl concurrency tests) remove their own entries with the trigger switched off for that moment.
 */
trait AuditLogCleanup
{
    protected function withoutAuditLogGuard(callable $callback): void
    {
        $this->asSchemaOwner('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_append_only');

        try {
            $callback();
        } finally {
            $this->asSchemaOwner('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_append_only');
        }
    }

    /**
     * M4: one DDL statement of these committed-fixture tests (no wrapping transaction), on the owner's own session when
     * the roles are split, so the test itself and its forked children keep running as the application role.
     */
    protected function asSchemaOwner(string $sql): void
    {
        if (OwnerConnection::prepare() && DB::getDefaultConnection() !== OwnerConnection::NAME) {
            DB::connection(OwnerConnection::NAME)->unprepared($sql);
            DB::disconnect(OwnerConnection::NAME);

            return;
        }
        DB::unprepared($sql);
    }
}
