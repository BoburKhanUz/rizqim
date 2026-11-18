<?php

namespace Tests\Feature\Tenancy;

use App\Support\Partitions\PartitionManager;
use App\Support\Tenancy\TenantRls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M4 stage 3 (docs/scale/M4_DESIGN.md 4.3), from the migrated schema: every table with a `store_id` column is under
 * the policy `tenant` (TenantRls::TABLES) or listed here with the reason it is not; the policy is on exactly the listed
 * tables, in exactly the agreed shape. A table added later with a store_id fails here until somebody decides.
 */
class TenantRlsInventoryTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC = 'marketplace-public: customers read it across stores, and a policy cannot hide columns (design 4.3)';

    private const CONFIG = "a store's settings and reference rows, no money: the application guard, the store scope (stage 2) and the cross-tenant route suite (stage 1)";

    private const WORK = 'working rows of the hall, kitchen, bookings, queue and staff, no money: the application guard, the store scope and the route suite';

    private const PLATFORM = "RIZQIM's own rows about a store (billing, CRM, status history), written by admin, console and webhooks, which never take the tenant role";

    private const REGISTRY = 'looked up across stores on purpose (uniqueness over all stores is what it is for)';

    private const LATER = "a money journal outside the design's list of high-value tables (4.3): the application guard, the store scope and the route suite; a candidate once stage 3 has run in production";

    private const MIXED = 'store_id NULL is a platform or customer row that every store may meet: no store policy fits (design 4.3)';

    /** Tables with a `store_id` column that are not under the policy, each with its reason. */
    public const NOT_UNDER_RLS = [
        // NOT NULL store_id
        'auto_guest_items' => self::CONFIG,
        'bank_terminal_reports' => self::LATER,
        'banquet_cancellations' => self::LATER,
        'banquet_events' => self::WORK,
        'banquet_extras' => self::CONFIG,
        'banquet_hall_rates' => self::CONFIG,
        'banquet_ops_settings' => self::CONFIG,
        'banquet_packages' => self::CONFIG,
        'banquet_payment_settings' => self::CONFIG,
        'banquet_settings' => self::CONFIG,
        'banquet_settlements' => self::LATER,
        'bookings' => self::WORK,
        'branch_booking_rules' => self::CONFIG,
        'branch_brands' => self::PUBLIC,
        'branch_label_settings' => self::CONFIG,
        'branch_lan_settings' => self::CONFIG,
        'branch_packaging_rules' => self::CONFIG,
        'branch_takeaway_settings' => self::CONFIG,
        'branch_terminal_setups' => self::CONFIG,
        'branches' => self::PUBLIC,
        'brand_channels' => self::CONFIG,
        'brands' => self::PUBLIC,
        'channel_commissions' => self::CONFIG,
        'channel_prices' => self::CONFIG,
        'combos' => self::PUBLIC,
        'customer_account_members' => self::CONFIG,
        'daily_menus' => self::PUBLIC,
        'daily_sets' => self::PUBLIC,
        'delivery_zones' => self::PUBLIC,
        'device_pairing_codes' => self::REGISTRY,
        'dining_tables' => self::CONFIG,
        'finance_adjustments' => self::PLATFORM,
        'fiscal_receipt_keys' => self::REGISTRY,
        'halls' => self::CONFIG,
        'ingredients' => self::CONFIG,
        'integration_connections' => self::CONFIG,
        'kitchen_station_routes' => self::CONFIG,
        'kitchen_stations' => self::CONFIG,
        'menu_batch_plans' => self::CONFIG,
        'menu_batch_writeoffs' => self::WORK,
        'menu_batches' => self::PUBLIC,
        'menu_price_history' => self::CONFIG,
        'modifier_groups' => self::PUBLIC,
        'order_ledger_reversals' => self::LATER,
        'order_refund_requests' => self::LATER,
        'payout_requests' => self::LATER,
        'pos_approvals' => self::WORK,
        'pos_bill_print_requests' => self::WORK,
        'pos_qr_refund_attempts' => self::LATER,
        'pos_qr_settings' => self::CONFIG,
        'pos_queue_blocks' => self::WORK,
        'pos_sync_operations' => self::REGISTRY,
        'pos_tip_distributions' => self::LATER,
        'pos_tip_settings' => self::CONFIG,
        'pos_waitlist_entries' => self::WORK,
        'printers' => self::CONFIG,
        'products' => self::PUBLIC,
        'role_permission_overrides' => self::CONFIG,
        'room_tariff_calendars' => self::CONFIG,
        'room_tariffs' => self::CONFIG,
        'service_fee_settings' => self::CONFIG,
        'staff_pay_rules' => self::CONFIG,
        'staff_pay_settings' => self::CONFIG,
        'staff_work_sessions' => self::WORK,
        'stop_list_entries' => self::PUBLIC,
        'store_crm_profiles' => self::PLATFORM,
        'store_health_scores' => self::PLATFORM,
        'store_ratings' => self::PUBLIC,
        'store_status_logs' => self::PLATFORM,
        'store_subscriptions' => self::PLATFORM,
        'store_topup_requests' => self::LATER,
        'subscription_dunning_events' => self::PLATFORM,
        'subscription_invoices' => self::PLATFORM,
        // nullable store_id
        'alerts' => self::PLATFORM,
        'devices' => self::MIXED,
        'promo_codes' => self::MIXED,
        'promos' => self::MIXED,
        'store_applications' => self::PLATFORM,
        'store_notes' => self::PLATFORM,
        'store_tasks' => self::PLATFORM,
        'users' => self::MIXED,
    ];

    /** Under the policy although store_id is nullable: a NULL row (a platform-level entry) is simply no store's row. */
    private const NULLABLE_UNDER_RLS = ['audit_logs'];

    public const POLICY = "(store_id = (NULLIF(current_setting('app.store_id'::text, true), ''::text))::bigint)";

    /**
     * Tables (partitioned parents included, partitions not) with a store_id column => NOT NULL
     *
     * @return array<string, bool>
     */
    private function storeTables(): array
    {
        $rows = DB::select("SELECT c.relname AS name, a.attnotnull AS required FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_attribute a ON a.attrelid = c.oid AND a.attname = 'store_id' AND NOT a.attisdropped
            WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p') AND NOT c.relispartition ORDER BY c.relname");

        return array_column(array_map(fn (object $row) => [$row->name, (bool) $row->required], $rows), 1, 0);
    }

    public function test_every_table_with_a_store_is_under_the_policy_or_listed_with_its_reason(): void
    {
        $tables = $this->storeTables();
        $this->assertGreaterThan(80, count($tables), 'the schema query found the store tables');

        $problems = [];
        foreach ($tables as $table => $required) {
            $under = in_array($table, TenantRls::TABLES, true);
            $listed = isset(self::NOT_UNDER_RLS[$table]);
            if ($under === $listed) {
                $problems[] = $under
                    ? "{$table} is in TenantRls::TABLES and in NOT_UNDER_RLS: one of them"
                    : "{$table} has a store_id: put it under the policy (TenantRls::TABLES and a migration) or into NOT_UNDER_RLS with the reason";
            }
            if ($under && !$required && !in_array($table, self::NULLABLE_UNDER_RLS, true)) {
                $problems[] = "{$table} is under the policy with a nullable store_id: its NULL rows are invisible inside every tenant transaction";
            }
        }
        foreach (array_merge(TenantRls::TABLES, array_keys(self::NOT_UNDER_RLS)) as $table) {
            if (!isset($tables[$table])) {
                $problems[] = "{$table} is listed but the schema has no such table with a store_id";
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_the_policy_is_on_exactly_the_listed_tables_in_the_agreed_shape(): void
    {
        $this->assertPolicies();
    }

    /**
     * The journals are rebuilt as new tables when they are converted to partitions or back (the M3 migration's up() and
     * down(), PartitionConverter): the table that replaces one keeps its row-level security, like its triggers.
     */
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_a_journal_rebuilt_by_the_partition_conversion_keeps_its_policy(): void
    {
        $manager = app(PartitionManager::class);
        $conversion = require database_path('migrations/2026_11_25_100000_partition_journals.php');
        // As PartitionMigrationTest: the fiscal keys migration goes first (its trigger needs the partitioned table)
        $keys = require database_path('migrations/2026_11_25_100300_create_fiscal_receipt_keys_table.php');

        $keys->down();
        $conversion->down();
        $this->assertSame(['r', 'r'], [$manager->relkind('audit_logs'), $manager->relkind('fiscal_receipts')]);
        $this->assertPolicies();

        $conversion->up();
        $keys->up();
        $this->assertTrue($manager->isPartitioned('audit_logs') && $manager->isPartitioned('fiscal_receipts'));
        $this->assertPolicies();
    }

    private function assertPolicies(): void
    {
        $secured = DB::select("SELECT c.relname AS name, c.relforcerowsecurity AS forced FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relrowsecurity ORDER BY c.relname");
        $expected = TenantRls::TABLES;
        sort($expected);
        $this->assertSame($expected, array_map(fn (object $row) => $row->name, $secured), 'row-level security is enabled on TenantRls::TABLES and nowhere else (no partition, no other table)');
        // FORCE would bind the owner too: its pg_dump and the partition commands would see no rows
        $this->assertSame([], array_values(array_filter($secured, fn (object $row) => $row->forced)));

        $policies = DB::select("SELECT c.relname AS name, p.polname, p.polcmd, p.polpermissive, p.polroles::text AS roles,
                pg_get_expr(p.polqual, p.polrelid) AS qual, pg_get_expr(p.polwithcheck, p.polrelid) AS with_check
            FROM pg_policy p JOIN pg_class c ON c.oid = p.polrelid ORDER BY c.relname, p.polname");
        $this->assertSame($expected, array_map(fn (object $row) => $row->name, $policies), 'one policy per table');
        foreach ($policies as $policy) {
            $this->assertSame(
                ['tenant', '*', true, '{0}', self::POLICY, null],
                [$policy->polname, $policy->polcmd, (bool) $policy->polpermissive, $policy->roles, $policy->qual, $policy->with_check],
                "{$policy->name}: one permissive policy `tenant` for every command and every role, the same expression as check",
            );
        }
    }
}
