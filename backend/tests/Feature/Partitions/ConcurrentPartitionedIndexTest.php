<?php

namespace Tests\Feature\Partitions;

use App\Support\ConcurrentIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M3: ConcurrentIndex::createPartitioned builds an index on a partitioned table partition by partition. CREATE INDEX
 * CONCURRENTLY cannot run inside a transaction, so this test commits: it works on a throw-away partitioned table of its
 * own and drops it at the end.
 */
class ConcurrentPartitionedIndexTest extends TestCase
{
    // M4: does DDL itself (tests/Concerns/UsesOwnerConnection.php): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;

    // No wrapping transaction: CONCURRENTLY refuses to run inside one
    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS m3_index_probe');
        parent::tearDown();
    }

    public function test_the_index_is_built_on_every_partition_attached_and_valid_and_reaches_new_months(): void
    {
        DB::statement('DROP TABLE IF EXISTS m3_index_probe');
        DB::statement('CREATE TABLE m3_index_probe (id bigint NOT NULL, created_at timestamp(0) NOT NULL, store_id bigint, is_read boolean NOT NULL DEFAULT false) PARTITION BY RANGE (created_at)');
        DB::statement("CREATE TABLE m3_index_probe_2026_10 PARTITION OF m3_index_probe FOR VALUES FROM ('2026-10-01') TO ('2026-11-01')");
        DB::statement("CREATE TABLE m3_index_probe_2026_11 PARTITION OF m3_index_probe FOR VALUES FROM ('2026-11-01') TO ('2026-12-01')");
        DB::statement('CREATE TABLE m3_index_probe_default PARTITION OF m3_index_probe DEFAULT');
        DB::statement("INSERT INTO m3_index_probe SELECT g, '2026-10-01'::timestamp + (g * 20 || ' minutes')::interval, g % 7, g % 2 = 0 FROM generate_series(1, 2000) g");

        ConcurrentIndex::createPartitioned('m3_index_probe_store_id_unread_index', 'm3_index_probe', ['store_id'], 'is_read = false');
        // Again: a no-op (everything attached)
        ConcurrentIndex::createPartitioned('m3_index_probe_store_id_unread_index', 'm3_index_probe', ['store_id'], 'is_read = false');

        $this->assertFalse(ConcurrentIndex::isInvalid('m3_index_probe_store_id_unread_index'), 'the parent index is valid once every partition has it');
        $children = DB::select("SELECT c.relname FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid WHERE i.inhparent = 'm3_index_probe_store_id_unread_index'::regclass ORDER BY 1");
        $this->assertSame(
            ['m3_index_probe_2026_10_store_id_unread_index', 'm3_index_probe_2026_11_store_id_unread_index', 'm3_index_probe_default_store_id_unread_index'],
            array_column($children, 'relname'),
        );
        $this->assertStringContainsString('WHERE (is_read = false)', DB::selectOne("SELECT pg_get_indexdef('m3_index_probe_2026_10_store_id_unread_index'::regclass) AS d")->d);

        // A month attached later (as db:partitions:ensure does) gets the index from the parent
        DB::statement('CREATE TABLE m3_index_probe_2026_12 (LIKE m3_index_probe INCLUDING DEFAULTS INCLUDING CONSTRAINTS)');
        DB::statement("ALTER TABLE m3_index_probe ATTACH PARTITION m3_index_probe_2026_12 FOR VALUES FROM ('2026-12-01') TO ('2027-01-01')");
        $this->assertSame(4, (int) DB::selectOne("SELECT count(*) AS n FROM pg_inherits WHERE inhparent = 'm3_index_probe_store_id_unread_index'::regclass")->n);
    }

    public function test_long_names_are_shortened_to_postgres_limit(): void
    {
        $name = ConcurrentIndex::partitionIndexName('fiscal_receipts_store_id_idempotency_key_created_at_index', 'fiscal_receipts', 'fiscal_receipts_2026_11');
        $this->assertLessThanOrEqual(63, strlen($name));
        $this->assertStringStartsWith('fiscal_receipts_2026_11_store_id', $name);
    }
}
