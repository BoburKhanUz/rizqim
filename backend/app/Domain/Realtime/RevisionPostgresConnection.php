<?php

namespace App\Domain\Realtime;

use App\Support\Database\OwnerConnection;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRls;
use Closure;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * The application's Postgres connection (RealtimeServiceProvider registers it for the pgsql driver).
 *
 * Wave M2: its query builder is RevisionQueryBuilder, which touches the change tokens of every row written.
 *
 * Wave M4 stage 3 (docs/scale/M4_DESIGN.md 4.3): row-level security for tenant requests. While TENANT_RLS is on and a
 * store is bound (TenantContext), the first transaction opened takes the role `rizqim_tenant` and the store in the
 * setting `app.store_id`, in one message right after BEGIN, again on every attempt of DB::transaction(..., n); a single
 * insert, update or delete of a table under the policy outside a transaction is wrapped in one ("writes mode": reads
 * outside transactions, and writes of other tables, stay as they were). Both are SET LOCAL, so they end with the
 * transaction and nothing reaches the next client of a pooled server connection (PgBouncer, transaction mode).
 * Transactions nested in a tenant transaction (savepoints) change nothing. Unbound (console, queue workers, scheduler,
 * webhooks, admin, marketplace), with TENANT_RLS off and on the owner connection nothing is switched at all.
 *
 * "The first transaction opened" is level 1 in production. It is a savepoint when the bound code starts inside a
 * transaction somebody else opened: the test suite's wrapping transaction (RefreshDatabase), or unbound code. A
 * rollback to that savepoint undoes role and setting like everything else, but nothing ends when it is released, so
 * there, and only there, both are put back by hand before the level is committed: after-commit callbacks and whatever
 * follows run as the login role in tests exactly as they do after a real COMMIT.
 *
 * A statement that must reach another store's or a platform row inside a tenant transaction: TenantRls::asApp().
 */
class RevisionPostgresConnection extends PostgresConnection
{
    /** Transactions that took the tenant role in this process (tests assert that row-level security was exercised) */
    public static int $tenantTransactions = 0;

    /** The transaction level whose start took the tenant role; null = the login role */
    private ?int $tenantLevel = null;

    /** The store that level was entered for (what a refusal is reported with) */
    private ?int $tenantStore = null;

    /** @var array<string, true>|null TenantRls::TABLES as a set */
    private static ?array $tenantTables = null;

    /** Open TenantRls::asApp() sections (and queued jobs run inline by the sync queue): the login role inside them */
    private int $appRoleDepth = 0;

    /** The switch failed once on this connection (no such role, no membership): not tried again, see enterTenantRole() */
    private bool $tenantRoleUnavailable = false;

    public function query()
    {
        return new RevisionQueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    public function beginTransaction()
    {
        $this->forgetLeftTenantLevel();

        parent::beginTransaction();

        if ($this->tenantLevel === null && $this->appRoleDepth === 0 && ($storeId = $this->storeToEnter()) !== null) {
            $this->enterTenantRole($storeId);
        }
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        // parent::transaction() commits inline (not through commit()): the role is left as the callback's last step
        return parent::transaction(function ($connection) use ($callback) {
            $result = $callback($connection);
            $this->leaveTenantRole();

            return $result;
        }, $attempts);
    }

    public function commit()
    {
        $this->leaveTenantRole();

        parent::commit();
    }

    public function rollBack($toLevel = null)
    {
        $before = $this->transactions;

        try {
            parent::rollBack($toLevel);
        } finally {
            if ($this->tenantLevel !== null && $this->transactions < $this->tenantLevel) {
                $this->tenantLevel = null; // undone by the rollback with everything else of that level
            }
        }

        // A rollback to a savepoint inside the tenant transaction puts back the role the session had when that
        // savepoint was taken, which need not be the one this class believes in: a transaction opened inside an
        // asApp() section (or by an inline queued job) and rolled back after it would leave the session as the login
        // role, unprotected; one opened before a section and rolled back inside it, the reverse. Said again, so the
        // session's role is always the bookkeeping's
        if ($this->transactions < $before && $this->inTenantRole() && ($role = TenantRls::role()) !== null) {
            $this->getPdo()->exec('SET LOCAL ROLE ' . ($this->appRoleDepth > 0 ? 'NONE' : "\"{$role}\""));
        }
    }

    /**
     * Review H1: Laravel answers a deadlock or serialization failure inside a nested transaction by lowering its
     * counter and throwing a DeadlockException, without ROLLBACK TO SAVEPOINT (MySQL rolls the whole transaction back
     * on a deadlock). PostgreSQL does not: it leaves the caller's transaction aborted, so a caller that catches the
     * exception and goes on (SettlementRecoveryService::book) failed at its next statement with 25P02, or, with
     * nothing more to say, "committed" a transaction PostgreSQL had rolled back (pdo_pgsql reports success). Here the
     * level is rolled back to its savepoint first, exactly as for any other exception, and the same DeadlockException
     * is thrown: the caller's transaction stays usable and keeps what it did outside the savepoint
     * (tests/Feature/NestedTransactionDeadlockTest).
     */
    protected function handleTransactionException(Throwable $e, $currentAttempt, $maxAttempts)
    {
        try {
            if ($this->transactions > 1 && $this->causedByConcurrencyError($e)) {
                $this->rollBack();

                throw new DeadlockException($e->getMessage(), is_int($e->getCode()) ? $e->getCode() : 0, $e);
            }

            parent::handleTransactionException($e, $currentAttempt, $maxAttempts);
        } finally {
            $this->forgetLeftTenantLevel();
        }
    }

    public function setPdo($pdo)
    {
        $this->tenantLevel = null; // another session (reconnect, disconnect): Laravel resets its transaction level here too

        return parent::setPdo($pdo);
    }

    public function statement($query, $bindings = [])
    {
        return $this->asTenantWrite($query, fn () => parent::statement($query, $bindings));
    }

    public function affectingStatement($query, $bindings = [])
    {
        return $this->asTenantWrite($query, fn () => parent::affectingStatement($query, $bindings));
    }

    public function selectFromWriteConnection($query, $bindings = [])
    {
        // Eloquent's `insert ... returning id` comes this way (PostgresProcessor); the schema builder's reads do too
        return $this->asTenantWrite($query, fn () => parent::selectFromWriteConnection($query, $bindings));
    }

    /**
     * PostgreSQL refused a statement for lack of privilege (SQLSTATE 42501) under row-level security: a row of another
     * store sent by a tenant transaction. Either the protection worked or a legitimate write was refused: both must be
     * seen at once (TenantRls::refused: a critical log line and the monitoring alert). The exception goes on as it was.
     */
    protected function handleQueryException(QueryException $e, $query, $bindings, Closure $callback)
    {
        if ($e->getCode() === '42501' && ($this->inTenantRole() || str_contains($e->getMessage(), 'row-level security'))) {
            TenantRls::refused($e, $this->inTenantRole() ? $this->tenantStore : null);
        }

        return parent::handleQueryException($e, $query, $bindings, $callback);
    }

    /**
     * The login role from here to resumeTenantRole(), also inside a tenant transaction (TenantRls::asApp(), queued jobs
     * the sync queue runs inline). A transaction opened in between does not take the tenant role. The setting
     * app.store_id stays: the login role does not read it.
     */
    public function suspendTenantRole(): void
    {
        if ($this->appRoleDepth === 0 && $this->inTenantRole()) {
            $this->getPdo()->exec('SET LOCAL ROLE NONE');
        }
        $this->appRoleDepth++;
    }

    public function resumeTenantRole(): void
    {
        if ($this->appRoleDepth === 0) {
            return;
        }
        if (--$this->appRoleDepth === 0 && $this->inTenantRole() && ($role = TenantRls::role()) !== null) {
            try {
                $this->getPdo()->exec("SET LOCAL ROLE \"{$role}\"");
            } catch (Throwable) {
                // A statement failed inside the section and the transaction is aborted: the rollback that has to
                // follow puts the role back as it was at its savepoint (taken before the section), or ends it
            }
        }
    }

    /** True while this connection's open transaction runs as the tenant role (suspended sections included) */
    public function inTenantRole(): bool
    {
        return $this->tenantLevel !== null && $this->transactions >= $this->tenantLevel;
    }

    /**
     * A single insert, update or delete of a table under the policy, outside a tenant transaction while a store is
     * bound: in a transaction of its own, so it runs under the policy too. Everything else as it is: inside a tenant
     * transaction, unbound, switched off, and statements that write another table or do not start with the write (a
     * CTE): those run as the login role, as before stage 3.
     */
    private function asTenantWrite(mixed $query, Closure $write): mixed
    {
        $this->forgetLeftTenantLevel();

        if ($this->tenantLevel !== null || $this->appRoleDepth > 0 || $this->pretending
            || $this->storeToEnter() === null || !self::writesTenantTable((string) $query)) {
            return $write();
        }

        return $this->transaction($write);
    }

    private static function writesTenantTable(string $query): bool
    {
        if (preg_match('/^\s*(?:insert\s+into|update|delete\s+from)\s+(?:only\s+)?(?:"?public"?\.)?"?(\w+)/i', $query, $match) !== 1) {
            return false;
        }
        self::$tenantTables ??= array_fill_keys(TenantRls::TABLES, true);

        return isset(self::$tenantTables[strtolower($match[1])]);
    }

    /** The store to take the tenant role for: null when unbound, switched off, or on the owner's connection */
    private function storeToEnter(): ?int
    {
        if ($this->tenantRoleUnavailable || $this->getName() === OwnerConnection::NAME || !TenantRls::enabled()) {
            return null;
        }

        return app(TenantContext::class)->storeId();
    }

    /**
     * Role and store in one message (simple protocol, no parameters: the store is an int, the role a checked name).
     *
     * A till write must never fail because the switch cannot be made (the role is missing or the login role is not its
     * member: `app:check-production` refuses such a setup at start, this is the request that meets it anyway): the
     * transaction is started again without it and runs as the login role, as with TENANT_RLS off, said once per
     * connection (TenantRls::unavailable: a critical log line and the monitoring alert). A lost connection is not
     * that: it fails the transaction as it would at its first query.
     */
    private function enterTenantRole(int $storeId): void
    {
        $role = TenantRls::role();

        try {
            if ($role === null) {
                throw new \RuntimeException('database.tenant_rls.role is not a plain role name');
            }
            $this->getPdo()->exec("SET LOCAL ROLE \"{$role}\"; SET LOCAL app.store_id = '{$storeId}'");
        } catch (Throwable $e) {
            if ($this->causedByLostConnection($e)) {
                try {
                    $this->rollBack();
                } catch (Throwable) {
                    // the level is reset by the failed rollback (handleRollBackException)
                }

                throw $e;
            }

            $level = $this->transactions - 1;
            $this->performRollBack($level); // the failed statement aborted the transaction (or the savepoint)
            if ($level === 0) {
                $this->getPdo()->beginTransaction();
            }
            $this->tenantRoleUnavailable = true;
            TenantRls::unavailable($e, $storeId);

            return;
        }

        $this->tenantLevel = $this->transactions;
        $this->tenantStore = $storeId;
        self::$tenantTransactions++;
    }

    /** Right before the level that took the role is committed */
    private function leaveTenantRole(): void
    {
        if ($this->tenantLevel === null || $this->transactions !== $this->tenantLevel) {
            return;
        }
        if ($this->tenantLevel > 1) {
            $this->resetRole(); // taken inside a savepoint: no COMMIT ends it (see the class comment)
        }
        $this->tenantLevel = null;
    }

    /**
     * The level that took the role is gone without a commit or rollback through this class: Laravel answers a
     * deadlock in a nested transaction by lowering its counter only (DeadlockException, no ROLLBACK TO SAVEPOINT).
     * handleTransactionException() now rolls such a level back itself; this stays as the safety net.
     */
    private function forgetLeftTenantLevel(): void
    {
        if ($this->tenantLevel === null || $this->transactions >= $this->tenantLevel) {
            return;
        }
        if ($this->transactions > 0) {
            try {
                $this->resetRole();
            } catch (Throwable) {
                // aborted: only a rollback is possible now, and it undoes the role too
            }
        }
        $this->tenantLevel = null;
    }

    private function resetRole(): void
    {
        $this->getPdo()->exec("SET LOCAL ROLE NONE; SET LOCAL app.store_id = ''");
    }
}
