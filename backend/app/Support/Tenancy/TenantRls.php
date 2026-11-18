<?php

namespace App\Support\Tenancy;

use App\Domain\Realtime\RevisionPostgresConnection;
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

    /** Cache keys of the last problem of each kind, kept for PROBLEM_TTL (read by the monitoring check `partitions`) */
    public const REFUSED = 'tenant_rls:refused';

    public const UNAVAILABLE = 'tenant_rls:unavailable';

    public const PROBLEM_TTL = 3600;

    /**
     * PostgreSQL refused a statement of a tenant transaction (SQLSTATE 42501: a row of another store on a table under
     * the policy, or a table the tenant role may not write). The till gets the usual server error; here the refusal
     * becomes visible at once: a critical log line (table, route, store: no row data) and a marker the monitoring
     * check turns into an alert. Never throws.
     */
    public static function refused(Throwable $e, ?int $storeId): void
    {
        preg_match('/for (?:table|relation|sequence|function|schema) "?([\w.]+)/', $e->getMessage(), $match);
        self::report(self::REFUSED, 'tenant_rls: PostgreSQL refused a write (row-level security)', [
            'table' => $match[1] ?? null,
            'store_id' => $storeId,
        ]);
    }

    /**
     * TENANT_RLS is on but the tenant role could not be taken (no such role, the login role is not its member): the
     * request went on as the login role, WITHOUT row-level security. Never throws.
     */
    public static function unavailable(Throwable $e, int $storeId): void
    {
        self::report(self::UNAVAILABLE, 'tenant_rls: cannot switch to the tenant role, this request runs WITHOUT row-level security (run db-roles, then app:check-production)', [
            'role' => (string) config('database.tenant_rls.role'),
            'store_id' => $storeId,
            'error' => mb_substr((string) preg_replace('/\s+/', ' ', $e->getMessage()), 0, 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function report(string $key, string $message, array $context): void
    {
        try {
            $route = request()->route();
            $context['route'] = $route instanceof \Illuminate\Routing\Route ? request()->method() . ' ' . $route->uri() : null;
            Log::critical($message, $context);
            $count = (int) (Cache::get($key)['count'] ?? 0) + 1;
            Cache::put($key, $context + ['count' => $count, 'at' => now()->toIso8601String()], self::PROBLEM_TTL);
        } catch (Throwable) {
            // Reporting must not turn one failure into another (a cache kept in the database cannot be written while
            // the failed statement's transaction is still open: the log line is then all there is)
        }
    }

    /**
     * Runs $callback as the application's login role, also inside a tenant transaction: the ONE way for a statement
     * that must read or write a row of another store or of the platform on a table of TABLES (the referrer's ledger,
     * "the customer's first delivery on the platform", an offline batch another store already sent). Under the tenant
     * role such a read finds nothing and such a write does nothing or fails, whatever the Eloquent scope says.
     *
     * Every use carries a comment saying which foreign rows it needs and why that is safe. Keep the callback to the
     * statements that need it and load their results inside it (a lazy cursor read later runs as the tenant role
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
