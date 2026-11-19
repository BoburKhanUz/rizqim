<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Every table takes its lock in a short transaction of its own
    public $withinTransaction = false;

    /**
     * The same list as App\Support\Tenancy\TenantRls::TABLES when this migration was written (a migration does not
     * follow later changes of the code). `fiscal_receipts` and `audit_logs` are partitioned: the policy sits on the
     * parent and binds every query through it, also for partitions created or attached later; code never names a
     * partition.
     */
    private const TABLES = [
        'orders', 'order_bills', 'payments', 'fiscal_receipts', 'pos_shifts', 'cash_movements', 'pos_refunds', 'pos_tips',
        'pos_qr_payments', 'customer_accounts', 'customer_account_entries', 'vouchers', 'voucher_entries',
        'booking_payments', 'banquet_payments', 'banquet_payment_entries', 'staff_pay_statements', 'store_transactions',
        'store_wallet_transactions', 'audit_logs',
    ];

    private const LOCK_RETRY_SECONDS = 60;

    private ?float $retryUntil = null;

    /** nullif: the setting reads '' (not NULL) on a pooled connection once any earlier transaction has set it */
    private const POLICY = "store_id = nullif(current_setting('app.store_id', true), '')::bigint";

    /**
     * Wave M4 stage 3 (docs/scale/M4_DESIGN.md 4.3): row-level security on the money tables. One policy `tenant` per
     * table, for every command: a row is visible and writable only when its store is the one in the setting
     * `app.store_id`. No FORCE: the tables' owner (migrations, partition commands, an install with one database role)
     * and roles with BYPASSRLS (the application's login role, the bootstrap superuser: backups, restore drill) are not
     * bound, so nothing changes for them. The policy binds the role `rizqim_tenant`, which the application takes with
     * SET LOCAL ROLE inside the transactions of a tenant request when TENANT_RLS is on (RevisionPostgresConnection).
     *
     * Metadata only. Per table one transaction: CREATE POLICY takes ACCESS EXCLUSIVE with its first statement (so no
     * lock is upgraded) and waits for it at most min(500 ms, deadlock_timeout / 2): a till transaction holding the
     * table makes the migration give up, never the till. A lock timeout is tried again for up to a minute in all
     * (locked()); after that it fails the migration; run again, it goes on with the tables not done yet.
     *
     * Refused before anything is changed when the application's role would itself be bound by the policies (roles
     * split, but scripts/db-roles.sh of this stage has not given it BYPASSRLS yet): it would see no row of these
     * tables in any queue worker, webhook or report.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->refuseUnlessAppBypasses();

        foreach (self::TABLES as $table) {
            if ($this->done($table)) {
                continue;
            }
            $this->locked(function () use ($table) {
                DB::statement("DROP POLICY IF EXISTS tenant ON {$table}"); // ACCESS EXCLUSIVE, the only lock taken
                DB::statement("CREATE POLICY tenant ON {$table} USING (" . self::POLICY . ')');
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            });
        }

        // payments_store_id (BEFORE INSERT/UPDATE, stage 2) takes a payment's store from its order through
        // payments_parent_store(). As the invoker, the tenant role could not see another store's order there, so a row
        // "my store, your order" kept the store as sent and passed the policy. As the function's owner (the tables'
        // owner, whom the policies do not bind) it sees every order: the trigger sets the order's real store and the
        // policy refuses the row. Its relations are schema-qualified; the fixed search_path leaves nothing to shadow.
        // Every time (no lock; the stage 2 migration's up() recreates the function as it was)
        $this->parentStoreFunction('SECURITY DEFINER SET search_path = pg_catalog, pg_temp');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            $this->locked(function () use ($table) {
                DB::statement("DROP POLICY IF EXISTS tenant ON {$table}");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            });
        }
        $this->parentStoreFunction('SECURITY INVOKER RESET search_path');
    }

    private function parentStoreFunction(string $how): void
    {
        if (DB::selectOne("SELECT to_regprocedure('payments_parent_store(bigint, jsonb)') IS NOT NULL AS e")->e) {
            DB::statement("ALTER FUNCTION payments_parent_store(bigint, jsonb) {$how}");
        }
    }

    /**
     * The application's login role (DB_USERNAME, the `pgsql` connection; the migration itself runs on `pgsql_owner`
     * when the roles are split) must be the tables' owner, a superuser or have BYPASSRLS.
     */
    private function refuseUnlessAppBypasses(): void
    {
        $app = (string) config('database.connections.pgsql.username');
        $row = DB::selectOne(
            "SELECT r.rolsuper OR r.rolbypassrls OR pg_has_role(r.oid, c.relowner, 'USAGE') AS free
               FROM pg_roles r, pg_class c WHERE r.rolname = ? AND c.oid = 'orders'::regclass",
            [$app],
        );
        if ($row !== null && !$row->free) {
            throw new RuntimeException("Qator darajasidagi himoya (RLS) yoqilmadi: ilova roli {$app} da BYPASSRLS yo'q, u bu jadvallardagi hech bir qatorni ko'rmay qolar edi. Avval rollarni yangilang: docker compose run --rm db-roles (docs/OPERATIONS.md, 24-bo'lim), so'ng migratsiyani qayta ishga tushiring. Hech narsa o'zgartirilmadi.");
        }
    }

    private function done(string $table): bool
    {
        return (bool) DB::selectOne(
            "SELECT c.relrowsecurity AND EXISTS (SELECT 1 FROM pg_policy p WHERE p.polrelid = c.oid AND p.polname = 'tenant') AS done
               FROM pg_class c WHERE c.oid = ?::regclass",
            [$table],
        )->done;
    }

    /**
     * $step in a transaction of its own, waiting at most half of deadlock_timeout (at most 500 ms) for its lock. A
     * lock timeout (55P03: usually a reader that holds every table for minutes, the nightly pg_dump) is tried again
     * after a pause, for LOCK_RETRY_SECONDS from the migration's first timeout in all; then it fails as before (the
     * container restarts and the migration goes on with the tables not done yet).
     */
    private function locked(callable $step): void
    {
        $deadlock = (int) DB::selectOne("SELECT setting::int AS ms FROM pg_settings WHERE name = 'deadlock_timeout'")->ms;
        $timeout = max(10, min(500, intdiv($deadlock, 2))) . 'ms';
        while (true) {
            try {
                DB::transaction(function () use ($step, $timeout) {
                    DB::statement("SET LOCAL lock_timeout = '{$timeout}'");
                    $step();
                });

                return;
            } catch (QueryException $e) {
                $this->retryUntil ??= microtime(true) + self::LOCK_RETRY_SECONDS;
                if ($e->getCode() !== '55P03' || microtime(true) >= $this->retryUntil) {
                    throw $e;
                }
                usleep(500_000);
            }
        }
    }
};
