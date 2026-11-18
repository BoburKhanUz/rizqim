<?php

namespace Tests\Feature\Partitions;

use App\Support\Partitions\PartitionConverter;
use App\Support\Partitions\PartitionManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave M3: the inline migration down/up of fiscal_receipts keeps EVERY user trigger of the table, in particular
 * fiscal_receipt_keys_insert/_update (migration 2026_11_25_100300: the global uniqueness of receipts across
 * partitions), fiscal_receipt_keys stays one row per receipt, and a missing key trigger is reported and repaired.
 * Committed data (the repair commits its backfill in batches): tearDown puts the schema back as migrated.
 */
class FiscalKeysConversionTest extends TestCase
{
    // M4: does DDL itself (tests/Concerns/UsesOwnerConnection.php): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    private const TRIGGERS = ['fiscal_receipt_keys_insert', 'fiscal_receipt_keys_update'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->restore();
        $this->store = $this->makeStore('Keys ' . Str::random(4));
        foreach (range(1, 40) as $i) {
            // Spread over three months: the keys must hold across partitions
            $this->receipt(['created_at' => sprintf('2026-%02d-10 10:00:00', 9 + $i % 3)]);
        }
        $this->assertKeysInStep(40);
    }

    protected function tearDown(): void
    {
        $this->restore();
        parent::tearDown();
    }

    public function test_the_inline_migration_down_and_up_keep_the_key_triggers_and_one_key_per_receipt(): void
    {
        $migration = require database_path('migrations/2026_11_25_100000_partition_journals.php');
        $migration->down();
        $this->assertTriggers();
        $this->assertKeysInStep(40);
        $migration->up();
        $this->assertTrue(app(PartitionManager::class)->isPartitioned('fiscal_receipts'));
        $this->assertTriggers();
        $this->assertKeysInStep(40);
        $this->assertDuplicateRefused();
    }

    public function test_a_missing_key_trigger_is_reported_and_ensure_repairs_it(): void
    {
        DB::statement('DROP TRIGGER fiscal_receipt_keys_insert ON fiscal_receipts');
        $unkeyed = $this->receipt([]);

        $status = app(PartitionManager::class)->status();
        $this->assertFalse($status['ok']);
        $problem = collect($status['problems'])->firstWhere('code', 'trigger_missing');
        $this->assertSame('fiscal_receipts', $problem['table']);
        $this->assertStringContainsString('php artisan db:partitions:ensure', $problem['message']);

        $this->assertSame(0, Artisan::call('db:partitions:ensure'));
        $this->assertTriggers();
        // The receipt written without the trigger got its key from the repair's backfill
        $this->assertSame(1, DB::table('fiscal_receipt_keys')->where('receipt_id', $unkeyed)->count());
        $this->assertKeysInStep(41);
        $this->assertNull(collect(app(PartitionManager::class)->status()['problems'])->firstWhere('code', 'trigger_missing'));
    }

    private function assertTriggers(): void
    {
        $names = array_column(DB::select("SELECT tgname FROM pg_trigger WHERE tgrelid = 'fiscal_receipts'::regclass AND NOT tgisinternal AND tgenabled = 'O' ORDER BY 1"), 'tgname');
        foreach (self::TRIGGERS as $trigger) {
            $this->assertContains($trigger, $names, "fiscal_receipts has {$trigger}");
        }
    }

    private function assertKeysInStep(int $receipts): void
    {
        $this->assertSame($receipts, DB::table('fiscal_receipts')->count());
        $this->assertSame($receipts, DB::table('fiscal_receipt_keys')->count(), 'one key row per receipt');
        $this->assertSame(0, (int) DB::selectOne('SELECT count(*) AS n FROM fiscal_receipts r WHERE NOT EXISTS (SELECT 1 FROM fiscal_receipt_keys k WHERE k.receipt_id = r.id AND k.store_id = r.store_id AND k.idempotency_key = r.idempotency_key AND k.uuid = r.uuid)')->n);
    }

    private function assertDuplicateRefused(): void
    {
        // Same store and key as a September receipt, in another month: only fiscal_receipt_keys can refuse it
        $first = DB::table('fiscal_receipts')->orderBy('id')->first();
        try {
            DB::transaction(fn () => $this->receipt(['idempotency_key' => $first->idempotency_key, 'created_at' => '2026-12-05 10:00:00']));
            $this->fail('a second receipt under the same key must be refused');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->getCode());
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function receipt(array $attributes): int
    {
        return DB::table('fiscal_receipts')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'cash', 'status' => 'sent',
            'driver' => 'fake', 'idempotency_key' => 'k-' . Str::random(10), 'total' => 1000, 'lines' => '[]', 'payments' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    /**
     * Back to the migrated state: fiscal_receipts partitioned, empty, with the key triggers.
     */
    private function restore(): void
    {
        $manager = app(PartitionManager::class);
        DB::statement('TRUNCATE fiscal_receipts');
        if ($manager->relkind('fiscal_receipts') === 'r') {
            app(PartitionConverter::class)->convertInline('fiscal_receipts');
        }
        DB::statement('TRUNCATE fiscal_receipts');
        (require database_path('migrations/2026_11_25_100300_create_fiscal_receipt_keys_table.php'))->up();
        DB::statement('TRUNCATE fiscal_receipt_keys');
        DB::table('stores')->where('name', 'like', 'Keys %')->delete();
    }
}
