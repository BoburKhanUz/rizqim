<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * P4-11: audit_logs is append-only (a PostgreSQL trigger refuses UPDATE and DELETE). Tests that commit rows outside a
 * transaction (the pcntl concurrency tests) remove their own entries with the trigger switched off for that moment.
 */
trait AuditLogCleanup
{
    // M4: switching the trigger off is DDL: a test using this runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;

    protected function withoutAuditLogGuard(callable $callback): void
    {
        DB::unprepared('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_append_only');

        try {
            $callback();
        } finally {
            DB::unprepared('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_append_only');
        }
    }
}
