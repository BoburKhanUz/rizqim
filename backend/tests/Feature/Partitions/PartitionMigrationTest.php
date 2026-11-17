<?php

namespace Tests\Feature\Partitions;

use App\Models\AuditLog;
use App\Support\Partitions\PartitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave M3: the partition_journals migration down and up again on a database with rows (inside the test's transaction:
 * every statement of it is transactional in Postgres).
 */
class PartitionMigrationTest extends TestCase
{
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_25_100000_partition_journals.php');
    }

    public function test_down_and_up_keep_every_row_backfill_created_at_and_restore_the_old_constraints(): void
    {
        $this->setUpLifecycleStore();
        $manager = app(PartitionManager::class);
        // A database from before M3: the later fiscal keys migration (2026_11_25_100300) is not there either (its key
        // trigger needs created_at, which the pre-M3 rows below lack)
        (require database_path('migrations/2026_11_25_100300_create_fiscal_receipt_keys_table.php'))->down();
        $this->migration()->down();
        foreach (['audit_logs', 'fiscal_receipts', 'notification_logs'] as $table) {
            $this->assertSame('r', $manager->relkind($table), "{$table} is a plain table after down()");
        }
        // The old schema is back: global uniques, the self foreign key, created_at nullable, the append-only trigger
        $this->assertSame('UNIQUE (uuid)', DB::selectOne("SELECT pg_get_constraintdef(oid) AS d FROM pg_constraint WHERE conname = 'fiscal_receipts_uuid_unique'")->d);
        $this->assertTrue((bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fiscal_receipts_original_receipt_id_foreign') AS e")->e);
        $this->assertTrue((bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'audit_logs'::regclass AND tgname = 'audit_logs_append_only') AS e")->e);

        // Rows of an old database: an audit entry older than first_month, receipts without created_at, a refund
        $this->travelTo('2025-06-20 09:00:00');
        $old = AuditLog::create(['action' => 'partitions.migration']);
        $this->travelTo('2026-11-10 09:00:00');
        $recent = AuditLog::create(['action' => 'partitions.migration']);
        $sale = $this->receipt(['created_at' => null, 'sold_at' => '2026-10-05 12:00:00']);
        $refund = $this->receipt(['created_at' => null, 'sold_at' => null, 'issued_at' => null, 'updated_at' => '2026-11-02 08:00:00', 'type' => 'refund', 'original_receipt_id' => $sale]);
        $notification = DB::table('notification_logs')->insertGetId(['type' => 't', 'status' => 'sent', 'sent_at' => '2026-09-01 10:00:00']);

        $this->migration()->up();

        foreach (['audit_logs', 'fiscal_receipts', 'notification_logs'] as $table) {
            $this->assertTrue($manager->isPartitioned($table), "{$table} is partitioned after up()");
            $this->assertFalse($manager->exists("{$table}_m3_old"));
            $this->assertFalse($manager->exists("{$table}_m3_new"));
        }
        $this->assertSame('audit_logs_2025_06', $this->partitionOf('audit_logs', $old->id), 'months start at the oldest row');
        $this->assertSame('audit_logs_2026_11', $this->partitionOf('audit_logs', $recent->id));
        $this->assertSame('2026-10-05 12:00:00', DB::table('fiscal_receipts')->where('id', $sale)->value('created_at'));
        $this->assertSame('2026-11-02 08:00:00', DB::table('fiscal_receipts')->where('id', $refund)->value('created_at'));
        $this->assertSame($sale, (int) DB::table('fiscal_receipts')->where('id', $refund)->value('original_receipt_id'));
        $this->assertSame('notification_logs_2026_09', $this->partitionOf('notification_logs', $notification));

        // The sequences moved with the tables: the next ids continue
        $next = AuditLog::create(['action' => 'partitions.migration']);
        $this->assertGreaterThan($recent->id, $next->id);
        $this->assertGreaterThan($refund, $this->receipt([]));
        $this->assertSame(
            ['fiscal_receipts_pkey', 'fiscal_receipts_store_id_idempotency_key_unique', 'fiscal_receipts_uuid_unique'],
            array_column(DB::select("SELECT conname FROM pg_constraint WHERE conrelid = 'fiscal_receipts'::regclass AND contype IN ('p', 'u') ORDER BY 1"), 'conname'),
        );

        // And down again keeps the rows too
        $this->migration()->down();
        $this->assertSame(3, DB::table('audit_logs')->count());
        $this->assertSame(3, DB::table('fiscal_receipts')->count());
        $this->assertSame(1, DB::table('notification_logs')->count());
        $this->migration()->up();
    }

    public function test_rows_older_than_first_month_get_only_their_own_months(): void
    {
        (require database_path('migrations/2026_11_25_100300_create_fiscal_receipt_keys_table.php'))->down();
        $this->migration()->down();
        $this->travelTo('2019-03-01 10:00:00');
        AuditLog::create(['action' => 'partitions.ancient']);
        $this->travelBack();
        $this->migration()->up();

        $months = array_column(array_filter(app(PartitionManager::class)->partitions('audit_logs'), fn (array $p) => !$p['default']), 'name');
        $this->assertSame('audit_logs_2019_03', $months[0]);
        $this->assertSame('audit_logs_2026_01', $months[1], 'no empty month between the old row and first_month');
        $report = app(PartitionManager::class)->ensure('audit_logs');
        $this->assertSame([], $report['created'], 'ensure does not fill the gap either');
    }

    public function test_down_is_refused_while_a_month_is_detached_and_not_dropped(): void
    {
        $this->travelTo('2026-11-17 22:40:00');
        $this->artisan('db:partitions:archive')->assertSuccessful();
        $this->assertGreaterThan(0, DB::table('partition_archives')->where('status', 'detached')->count());

        foreach ([$this->migration(), require database_path('migrations/2026_11_25_100100_create_partition_archives_table.php')] as $migration) {
            try {
                $migration->down();
                $this->fail('down() must refuse while archived months are not dropped');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('audit_logs_2026_01', $e->getMessage());
            }
        }
        $this->assertTrue(app(PartitionManager::class)->isPartitioned('audit_logs'));
        $this->assertTrue(app(PartitionManager::class)->exists('partition_archives'));
    }

    public function test_up_refuses_a_table_above_the_inline_threshold_with_a_message_the_operator_can_act_on(): void
    {
        $this->migration()->down();
        foreach (range(1, 3) as $_) {
            AuditLog::create(['action' => 'partitions.migration']);
        }
        config(['partitions.inline_max_rows' => 2]);

        try {
            $this->migration()->up();
            $this->fail('up() must refuse a big table');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('php artisan db:partitions:convert audit_logs', $e->getMessage());
        }
        // notification_logs (empty) was converted, audit_logs left as it was
        $this->assertTrue(app(PartitionManager::class)->isPartitioned('notification_logs'));
        $this->assertSame('r', app(PartitionManager::class)->relkind('audit_logs'));
        $this->assertSame(3, DB::table('audit_logs')->count());
    }

    public function test_every_user_trigger_follows_the_table_and_a_swap_that_cannot_keep_one_is_refused(): void
    {
        $manager = app(PartitionManager::class);
        $converter = app(\App\Support\Partitions\PartitionConverter::class);
        $converter->revertInline('notification_logs');
        DB::unprepared("CREATE FUNCTION m3_probe_trigger() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RETURN NEW; END \$\$");
        // A trigger the converter does not know by name, one disabled: both follow, with their state
        DB::statement('CREATE TRIGGER m3_probe BEFORE UPDATE OF is_read ON notification_logs FOR EACH ROW WHEN (NEW.is_read) EXECUTE FUNCTION m3_probe_trigger()');
        DB::statement('CREATE TRIGGER m3_probe_off AFTER INSERT ON notification_logs FOR EACH ROW EXECUTE FUNCTION m3_probe_trigger()');
        DB::statement('ALTER TABLE notification_logs DISABLE TRIGGER m3_probe_off');
        $before = $manager->userTriggers('notification_logs');

        $converter->convertInline('notification_logs');
        $this->assertTrue($manager->isPartitioned('notification_logs'));
        $this->assertSame($before, $manager->userTriggers('notification_logs'));
        $converter->revertInline('notification_logs');
        $this->assertSame($before, $manager->userTriggers('notification_logs'));

        // A row trigger with a transition table cannot exist on a partitioned table: the conversion is refused whole
        DB::statement('CREATE TRIGGER m3_probe_transition AFTER INSERT ON notification_logs REFERENCING NEW TABLE AS fresh FOR EACH ROW EXECUTE FUNCTION m3_probe_trigger()');
        try {
            $converter->convertInline('notification_logs');
            $this->fail('the swap must be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("m3_probe_transition triggerini yangi jadvalda yaratib bo'lmadi", $e->getMessage());
        }
        $this->assertSame('r', $manager->relkind('notification_logs'), 'nothing changed');
        $this->assertArrayHasKey('m3_probe_transition', $manager->userTriggers('notification_logs'));
        $this->assertFalse($manager->exists('notification_logs_m3_new'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function receipt(array $attributes): int
    {
        return DB::table('fiscal_receipts')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'cash', 'status' => 'sent',
            'driver' => 'fake', 'idempotency_key' => 'k-' . Str::random(8), 'total' => 1000, 'lines' => '[]', 'payments' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function partitionOf(string $table, int $id): string
    {
        return (string) DB::selectOne("SELECT tableoid::regclass::text AS p FROM {$table} WHERE id = ?", [$id])->p;
    }
}
