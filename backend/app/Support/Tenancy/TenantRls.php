<?php

namespace App\Support\Tenancy;

use App\Domain\Realtime\RevisionPostgresConnection;
use App\Support\Database\OwnerConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M4 stage 3 (docs/scale/M4_DESIGN.md 4.3): row-level security for tenant requests.
 *
 * With TENANT_RLS on, every transaction of a tenant-bound request (TenantContext) runs as the database role
 * `rizqim_tenant` with the store in the setting `app.store_id` (RevisionPostgresConnection does the switch). On the
 * tables of TABLES the policy `tenant` then shows and accepts only that store's rows: a read of another store's row
 * returns nothing, an update or delete of it changes 0 rows, an insert for it fails (SQLSTATE 42501). Everything else
 * (console, queue workers, scheduler, webhooks, admin, marketplace, reads outside a transaction) runs as the login
 * role, which bypasses the policies.
 *
 * What the policy does NOT cover, by PostgreSQL's design:
 * - foreign-key actions. ON DELETE CASCADE / SET NULL run as the table's owner: deleting a row of a table that is not
 *   under the policy (a users row, a branch) deletes or changes the rows of these tables that reference it, in
 *   whichever store they are. The application must not hard-delete such a parent (OwnerEmployeeController::destroy);
 * - foreign-key checks. A row of the tenant's own store may reference another store's parent
 *   (order_bills.order_id, pos_refunds.payment_id, cash_movements.shift_id): the policy looks at store_id only. The
 *   row cannot be used to read the parent. payments is the exception: its trigger takes the store from the order
 *   (payments_parent_store, SECURITY DEFINER), so "my store, your order" is refused;
 * - the children without a store_id of their own (order_items, order_bill_items, staff_pay_statement_lines,
 *   pos_tip_distribution_lines, ...): reached only through their parents, guarded by the application.
 */
final class TenantRls
{
    /**
     * The tables under the policy `tenant` (migration 2026_11_28_100000_enable_tenant_row_level_security): the money
     * and its journals, each with a NOT NULL store_id of its own. tests/Feature/Tenancy/TenantRlsInventoryTest.php
     * compares this list with the schema and holds the reason for every store table left out.
     */
    public const TABLES = [
        'orders', 'order_bills', 'payments', 'fiscal_receipts', 'pos_shifts', 'cash_movements', 'pos_refunds', 'pos_tips',
        'pos_qr_payments', 'customer_accounts', 'customer_account_entries', 'vouchers', 'voucher_entries',
        'booking_payments', 'banquet_payments', 'banquet_payment_entries', 'staff_pay_statements', 'store_transactions',
        'store_wallet_transactions', 'audit_logs',
    ];

    /** The policy's expression, as the migration writes it (setupProblems() compares what the catalog holds with it) */
    public const POLICY = "store_id = nullif(current_setting('app.store_id', true), '')::bigint";

    public static function enabled(): bool
    {
        return (bool) config('database.tenant_rls.enabled', false);
    }

    /** The role a tenant transaction switches to; null when the configured name is not a plain identifier */
    public static function role(): ?string
    {
        $role = (string) config('database.tenant_rls.role', 'rizqim_tenant');

        return preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $role) === 1 ? $role : null;
    }

    /** Cache key of the last refusal, kept for REFUSED_TTL seconds (read by the monitoring check `partitions`) */
    public const REFUSED = 'tenant_rls:refused';

    public const REFUSED_TTL = 3600;

    private static ?int $unavailableLoggedAt = null;

    /**
     * What is wrong with the database roles for row-level security right now, as this connection's login role sees it:
     * `login_bound` (whatever TENANT_RLS says) and, with TENANT_RLS on, `unavailable` and `unprotected`. Empty = nothing.
     *
     * Asked at start-up (`app:check-production` refuses to start: a wrong DB_TENANT_ROLE or a missing grant is met
     * there, not at the first sale) and every minute by the monitoring check `partitions`, which keeps a critical alert
     * open for as long as the state lasts. So the one way the protection is off while TENANT_RLS says on (the
     * connection's fail-open path: the role was lost while the application runs) cannot stay unseen.
     *
     * - login_bound: the policies bind the login role itself (no BYPASSRLS, not the tables' owner): outside tenant
     *   transactions every queue worker, webhook and report would see no row of the money tables.
     * - unavailable: the tenant role's name is not a plain identifier, the role does not exist, is not bound by the
     *   policies (superuser, BYPASSRLS, the tables' owner or its member), cannot be taken by the login role, or lacks
     *   a privilege the login role has (a statement that works as the application would fail with "permission
     *   denied" inside a tenant transaction).
     * - unprotected: a table of TABLES whose row-level security is off or whose policy is not the agreed one
     *   (unprotectedTables()).
     *
     * @return array<string, string> Uzbek texts for the operator
     *
     * @throws Throwable when the database cannot be asked
     */
    public static function setupProblems(): array
    {
        // The owner's connection never takes the tenant role (RevisionPostgresConnection) and owns the tables: nothing
        // to ask of it. The question is about the application's login role
        if (DB::connection()->getName() === OwnerConnection::NAME) {
            return [];
        }

        $problems = [];
        $bound = DB::select(
            "SELECT c.relname AS name FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
              WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p') AND c.relrowsecurity AND row_security_active(c.oid)
              ORDER BY c.relname",
        );
        if ($bound !== []) {
            $problems['login_bound'] = 'Ilova roli ' . DB::scalar('SELECT current_user') . " ga qator darajasidagi himoya (RLS) ta'sir qiladi (" . count($bound) . ' ta jadval: ' . self::names($bound)
                . "): navbat, vebhuk va hisobotlar bu jadvallarda hech narsa ko'rmaydi. Rolga BYPASSRLS bering: docker compose run --rm db-roles (docs/OPERATIONS.md, 24-bo'lim)";
        }
        if (!self::enabled()) {
            return $problems;
        }

        $fix = "Rollarni yangilang: docker compose run --rm db-roles, yoki himoyani o'chiring: TENANT_RLS=0 (docs/OPERATIONS.md, 24-bo'lim)";
        $role = self::role();
        if ($role === null) {
            $problems['unavailable'] = "TENANT_RLS=1, lekin DB_TENANT_ROLE noto'g'ri: rol nomi faqat kichik lotin harf, raqam va '_' dan iborat bo'lsin (standart: rizqim_tenant)";

            return $problems;
        }
        $row = DB::selectOne(
            "SELECT pg_has_role(current_user, r.oid, 'MEMBER') AS member,
                    r.rolsuper OR r.rolbypassrls OR COALESCE((SELECT bool_or(pg_has_role(r.oid, c.relowner, 'USAGE')) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                        WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p')), false) AS free
               FROM pg_roles r WHERE r.rolname = ?",
            [$role],
        );
        if ($row === null) {
            $problems['unavailable'] = "TENANT_RLS=1, lekin bazada {$role} roli yo'q: do'kon tranzaksiyalari himoyasiz ishlaydi. {$fix}";
        } elseif ($row->free) {
            $problems['unavailable'] = "TENANT_RLS=1, lekin {$role} roli himoyani chetlab o'tadi (superuser, BYPASSRLS yoki jadvallar egasi / uning a'zosi): do'kon tranzaksiyalari himoyasiz ishlaydi. {$fix}";
        } elseif (!$row->member || !self::canTake($role)) {
            $problems['unavailable'] = "TENANT_RLS=1, lekin ilova {$role} roliga o'ta olmaydi (SET ROLE): do'kon tranzaksiyalari himoyasiz ishlaydi. {$fix}";
        } elseif (($missing = self::missingGrants($role)) !== []) {
            $problems['unavailable'] = "TENANT_RLS=1, lekin {$role} rolida ilova rolidagi huquqlar yetishmaydi (" . count($missing) . ' ta obyekt: ' . self::names($missing)
                . "): do'kon so'rovlari «permission denied» xatosi bilan yiqiladi. {$fix}";
        }

        if (($open = self::unprotectedTables()) !== []) {
            $problems['unprotected'] = 'TENANT_RLS=1, lekin ' . count($open) . ' ta jadval himoyasiz (' . self::names($open) . "): unda qator darajasidagi himoya o'chirilgan yoki `tenant` siyosati "
                . "kelishilgan ko'rinishda emas (yo'q, o'zgartirilgan yoki yonida boshqa siyosat bor). Do'kon tranzaksiyasi bu jadvalda boshqa do'konlarning qatorlarini ko'radi va o'zgartira oladi. "
                . "Siyosatni tiklang (3-bosqich migratsiyasi hali yurmagan bo'lsa: avval TENANT_RLS'siz ishga tushiring) yoki himoyani o'chiring: TENANT_RLS=0 (docs/OPERATIONS.md, 24-bo'lim)";
        }

        return $problems;
    }

    /**
     * Tables of TABLES that exist and are not protected as agreed: row-level security off, or anything but exactly one
     * policy: `tenant`, permissive, for every command and every role, no separate check, with the expression of
     * POLICY. The roles can be right and the protection gone all the same: a second permissive policy `USING (true)`,
     * or the policy rewritten, opens the table to every tenant transaction without any error anywhere. The expression
     * is compared without blanks, parentheses and `::text` casts, which is all that differs between what was written
     * and what PostgreSQL 16 and 17 print. Partitions are not asked: a statement through the parent is bound by the
     * parent's policy alone, and nothing names a partition (TenantRlsStatementShapesTest).
     *
     * @return list<object{name: string}>
     */
    private static function unprotectedTables(): array
    {
        $plain = "regexp_replace(lower(%s), '\\s|\\(|\\)|::text', '', 'g')";

        return DB::select(
            "SELECT t.name FROM unnest(?::text[]) AS t(name)
               JOIN pg_class c ON c.oid = to_regclass(format('%I.%I', current_schema(), t.name))
              WHERE NOT c.relrowsecurity
                 OR (SELECT count(*) FROM pg_policy p WHERE p.polrelid = c.oid) <> 1
                 OR NOT EXISTS (SELECT 1 FROM pg_policy p WHERE p.polrelid = c.oid AND p.polname = 'tenant' AND p.polpermissive AND p.polcmd = '*'
                        AND p.polroles = '{0}' AND p.polwithcheck IS NULL AND " . sprintf($plain, 'pg_get_expr(p.polqual, p.polrelid)') . ' = ' . sprintf($plain, '?::text') . ')
              ORDER BY t.name',
            ['{' . implode(',', self::TABLES) . '}', self::POLICY],
        );
    }

    /**
     * SET LOCAL ROLE in a transaction of its own, as a tenant request does it (PostgreSQL 16+: a membership may forbid
     * SET while it exists).
     */
    private static function canTake(string $role): bool
    {
        DB::beginTransaction();
        try {
            DB::statement("SET LOCAL ROLE \"{$role}\"");

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Tables, views, sequences and functions of the schema (partitions left out: never named by a statement) that the
     * login role may use and the tenant role may not, and the schema itself.
     *
     * @return list<object{name: string}>
     */
    private static function missingGrants(string $role): array
    {
        return DB::select(
            "SELECT c.relname::text AS name FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
              WHERE n.nspname = current_schema() AND NOT c.relispartition AND (
                    (c.relkind IN ('r', 'p', 'v', 'm') AND EXISTS (SELECT 1 FROM unnest(ARRAY['SELECT', 'INSERT', 'UPDATE', 'DELETE']) p
                        WHERE has_table_privilege(current_user, c.oid, p) AND NOT has_table_privilege(?, c.oid, p)))
                 OR (c.relkind = 'S' AND has_sequence_privilege(current_user, c.oid, 'USAGE') AND NOT has_sequence_privilege(?, c.oid, 'USAGE')))
             UNION ALL
             SELECT p.oid::regprocedure::text FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
              WHERE n.nspname = current_schema() AND has_function_privilege(current_user, p.oid, 'EXECUTE') AND NOT has_function_privilege(?, p.oid, 'EXECUTE')
             UNION ALL
             SELECT 'schema ' || current_schema() WHERE NOT has_schema_privilege(?, current_schema(), 'USAGE')
             ORDER BY 1",
            [$role, $role, $role, $role],
        );
    }

    /**
     * @param  list<object>  $rows
     */
    private static function names(array $rows): string
    {
        return implode(', ', array_slice(array_map(fn (object $row) => (string) $row->name, $rows), 0, 5)) . (count($rows) > 5 ? ', ...' : '');
    }

    /**
     * PostgreSQL refused a statement of a tenant transaction (SQLSTATE 42501: a row of another store on a table under
     * the policy, or a table the tenant role may not write). The till gets the usual server error; here the refusal
     * becomes visible at once: a critical log line (table, route, store: no row data) and a marker the monitoring
     * check turns into an alert within its minute. Never throws.
     */
    public static function refused(Throwable $e, ?int $storeId): void
    {
        try {
            preg_match('/for (?:table|relation|sequence|function|schema) "?([\w.]+)/', $e->getMessage(), $match);
            $context = ['table' => $match[1] ?? null, 'store_id' => $storeId, 'route' => self::route()];
            Log::critical('tenant_rls: PostgreSQL refused a write (row-level security)', $context);
            $count = (int) (Cache::get(self::REFUSED)['count'] ?? 0) + 1;
            Cache::put(self::REFUSED, $context + ['count' => $count, 'at' => now()->toIso8601String()], self::REFUSED_TTL);
        } catch (Throwable) {
            // Reporting must not turn one failure into another (a cache kept in the database cannot be written while
            // the failed statement's transaction is still open: the log line is then all there is)
        }
    }

    /**
     * TENANT_RLS is on but the tenant role could not be taken: the request went on as the login role, WITHOUT
     * row-level security. Said in the log by a request that met it (at most once a minute per process); the alert
     * does not depend on this line: the monitoring check asks the database itself every minute (setupProblems()).
     * Never throws.
     */
    public static function unavailable(Throwable $e, int $storeId): void
    {
        // One line a minute per process, not one per request (a role lost at lunch would write thousands). No cache:
        // nothing here may fail the request. A clock set back (tests) counts as a minute gone
        $now = now()->getTimestamp();
        if (self::$unavailableLoggedAt !== null && $now >= self::$unavailableLoggedAt && $now - self::$unavailableLoggedAt < 60) {
            return;
        }
        self::$unavailableLoggedAt = $now;

        try {
            Log::critical('tenant_rls: cannot switch to the tenant role, this request runs WITHOUT row-level security (run db-roles, then app:check-production)', [
                'role' => (string) config('database.tenant_rls.role'),
                'store_id' => $storeId,
                'route' => self::route(),
                'error' => mb_substr((string) preg_replace('/\s+/', ' ', $e->getMessage()), 0, 200),
            ]);
        } catch (Throwable) {
            // as above
        }
    }

    private static function route(): ?string
    {
        $route = request()->route();

        return $route instanceof \Illuminate\Routing\Route ? request()->method() . ' ' . $route->uri() : null;
    }

    /**
     * Runs $callback as the application's login role, also inside a tenant transaction: the ONE way for a statement
     * that must read or write a row of another store or of the platform on a table of TABLES. Under the tenant role
     * such a read finds nothing and such a write does nothing or fails, whatever the Eloquent scope says.
     *
     * The uses (TenantRlsInventoryTest lists them: a new one is a decision, not a habit): the referral's "is this the
     * customer's first delivery at any store" (LedgerService::isFirstDelivery) and an audit line for no store
     * (AuditLog::performInsert). Every use carries a comment saying which rows it needs and why that is safe. Keep the
     * callback to the statements that need it and load their results inside it (a lazy cursor read later runs as the tenant role
     * again). Outside a tenant transaction, and with TENANT_RLS off, it only calls $callback. A transaction opened
     * inside it runs as the login role as a whole. Nested calls are fine.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function asApp(callable $callback): mixed
    {
        $connection = DB::connection();
        if (!$connection instanceof RevisionPostgresConnection) {
            return $callback();
        }

        $connection->suspendTenantRole();
        try {
            return $callback();
        } finally {
            $connection->resumeTenantRole();
        }
    }
}
