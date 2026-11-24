<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\RevisionHooks;
use App\Domain\Realtime\RevisionMap;
use App\Domain\Realtime\RevisionPostgresConnection;
use App\Domain\Realtime\RevisionQueryBuilder;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/**
 * Wave M2 guard: a table cannot exist without a decision in RevisionMap (which change token its writes move), every
 * Eloquent model's table is classified, every write goes through the hooked query builder, and no raw SQL write can
 * slip past it. A failure here is fixed by classifying the table in app/Domain/Realtime/RevisionMap.php, never by
 * loosening the test: a missing touch is a stale till or kitchen board.
 */
class RevisionMapCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_table_of_the_schema_is_classified_and_no_entry_is_stale(): void
    {
        // M3: the monthly partitions of audit_logs, fiscal_receipts and notification_logs (and their DEFAULT) are listed
        // by Schema::getTables() too; their parent is the table the hooks see and the map classifies. Strict for every
        // other table: only pg_class.relispartition rows are skipped
        $partitions = collect(DB::select('SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relispartition AND n.nspname = current_schema()'))->pluck('relname')->all();
        $this->assertNotSame([], $partitions, 'the journal tables are partitioned (migration 2026_11_25_100000): no partition found');
        $tables = array_values(array_diff(collect(Schema::getTables())->pluck('name')->all(), $partitions));

        $missing = array_values(array_diff($tables, array_keys(RevisionMap::TABLES)));
        $this->assertSame([], $missing, 'Tables without a RevisionMap rule (classify them: which token does a write move, or NONE with a reason): ' . implode(', ', $missing));

        $stale = array_values(array_diff(array_keys(RevisionMap::TABLES), $tables));
        $this->assertSame([], $stale, 'RevisionMap lists tables that do not exist: ' . implode(', ', $stale));
    }

    public function test_every_rule_names_real_columns_and_tracked_parents(): void
    {
        $hooks = app(RevisionHooks::class);
        foreach (RevisionMap::TABLES as $table => $rules) {
            $this->assertNotSame([], $rules, "{$table}: no rule");
            foreach ($rules as $rule) {
                $this->assertContains($rule[0], [RevisionMap::BRANCH, RevisionMap::STORE, RevisionMap::STORE_BRANCHES, RevisionMap::USER, RevisionMap::VIA, RevisionMap::ALL_STORES, RevisionMap::FEATURE_SCOPE, RevisionMap::NONE], "{$table}: unknown rule");
                if ($rule[0] === RevisionMap::NONE) {
                    $this->assertCount(1, $rules, "{$table}: NONE cannot be combined with other rules");
                    $this->assertGreaterThan(3, strlen($rule[1]), "{$table}: NONE needs a reason");
                }
                if ($rule[0] === RevisionMap::VIA) {
                    $this->assertTrue(RevisionMap::tracks($rule[2]), "{$table}: VIA parent {$rule[2]} must be tracked");
                    $this->assertTrue(Schema::hasColumn($rule[2], 'id'), "{$table}: VIA parent {$rule[2]} has no id");
                }
            }
            foreach ($hooks->columnsFor($table) ?? [] as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "{$table}: the rule column {$column} does not exist");
            }
        }

        // Narrow writes (M2 review R3-1): real columns, tracked tables, never a rule column (a write that moves a row to
        // another branch or store must apply the rules), and the inputs the decision reads exist
        foreach (RevisionMap::NARROW_COLUMNS as $table => $columns) {
            $this->assertTrue(RevisionMap::tracks($table), "narrow table {$table} is not tracked");
            $this->assertArrayHasKey($table, RevisionMap::NARROW_INPUTS, "narrow table {$table} has no inputs");
            $this->assertSame('id', RevisionMap::NARROW_INPUTS[$table][0], "{$table}: the narrow inputs start with id");
            foreach (array_merge($columns, RevisionMap::NARROW_INPUTS[$table]) as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "narrow column {$table}.{$column} does not exist");
            }
            $this->assertSame([], array_intersect($columns, $hooks->columnsFor($table) ?? []), "{$table}: a rule column cannot be narrow");
        }

        foreach (RevisionMap::QUIET_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "quiet column {$table}.{$column} does not exist");
            }
        }
    }

    public function test_a_table_with_a_branch_or_store_column_is_not_silently_untracked(): void
    {
        // A NONE table that carries a branch or store is one a screen of that branch could show: it must say why not
        $reviewed = [
            'alerts', 'audit_logs', 'device_pairing_codes', 'finance_adjustments', 'payout_requests', 'store_applications',
            'store_crm_profiles', 'store_health_scores', 'store_notes', 'store_status_logs', 'store_tasks',
            'store_topup_requests', 'subscription_dunning_events', 'menu_price_history',
            // M2 review R3-1: ledger rows, one per settled order; only the owner finance pages (read live, not
            // revision-gated) show them, and the debt lock they lead to is decided on the stores row (NARROW_COLUMNS)
            'store_transactions', 'store_wallet_transactions',
            // M3 A2: written only by the fiscal_receipts trigger, read only by FiscalService (no screen shows it); the
            // receipt row itself is BRANCH-tracked
            'fiscal_receipt_keys',
            // Wave P step 2: raw Mini App analytics events (store_id = the store the event was about); read only by the
            // aggregation jobs, never by a polled or live screen, and an ingestion must move no ETag
            'analytics_events',
        ];
        foreach (RevisionMap::TABLES as $table => $rules) {
            if ($rules[0][0] !== RevisionMap::NONE) {
                continue;
            }
            if (Schema::hasColumn($table, 'branch_id') || Schema::hasColumn($table, 'store_id')) {
                $this->assertContains($table, $reviewed, "{$table} has a branch/store column but is NONE: add it to the reviewed list only after checking no polled or live screen shows it");
            }
        }
    }

    public function test_every_eloquent_model_table_is_classified(): void
    {
        $models = 0;
        $root = app_path();
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $class = 'App\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($root) + 1));
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || !$reflection->isSubclassOf(Model::class)) {
                continue;
            }
            $models++;
            /** @var Model $model */
            $model = new $class;
            $table = $model->getTable();
            $this->assertArrayHasKey($table, RevisionMap::TABLES, "{$class} writes {$table}, which RevisionMap does not classify");
        }

        $this->assertGreaterThan(100, $models, 'the model scan found too few models');
    }

    public function test_every_query_goes_through_the_hooked_connection_and_builder(): void
    {
        $this->assertInstanceOf(RevisionPostgresConnection::class, DB::connection());
        $this->assertInstanceOf(RevisionQueryBuilder::class, DB::table('orders'));
        $this->assertInstanceOf(RevisionQueryBuilder::class, Order::query()->getQuery());
        $this->assertInstanceOf(RevisionQueryBuilder::class, (new Order)->newQuery()->toBase());
    }

    public function test_no_raw_sql_write_bypasses_the_query_builder(): void
    {
        // Raw statements are not seen by RevisionQueryBuilder: a write must use the query builder (or Eloquent)
        $allowed = [
            'Support/ConcurrentIndex.php', // index DDL in migrations only
            // M3: rows moved between partitions of one journal table, or copied between its old and new table during a
            // conversion: no row a screen shows changes (raw SQL on partition names is allowed only there)
            'Support/Partitions/PartitionManager.php',
            'Support/Partitions/PartitionConverter.php',
            // M4 stage 3: the connection itself sends SET LOCAL ROLE / SET LOCAL app.store_id around a tenant
            // transaction (straight to PDO, so no query event and no row is written)
            'Domain/Realtime/RevisionPostgresConnection.php',
        ];
        $offenders = [];
        $root = app_path();
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if (in_array($relative, $allowed, true)) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/DB::(insert|update|delete|unprepared|affectingStatement)\s*\(|->getPdo\(\)|DB::statement\s*\(\s*[\'"]\s*(insert|update|delete|merge|copy)/i', $source) === 1
                || preg_match('/->(statement|affectingStatement|unprepared)\s*\(\s*[\'"]\s*(insert|update|delete|merge)/i', $source) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Raw SQL writes bypass the change-revision hooks (use the query builder): ' . implode(', ', $offenders));
    }
}
