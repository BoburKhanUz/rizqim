<?php

namespace Tests\Feature\Fiscal;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalReceiptLock;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalService;
use App\Models\FiscalReceipt;
use App\Support\Partitions\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Feature\FiscalFixtures;
use Tests\TestCase;

/**
 * Wave M3 (docs/scale/M3_DESIGN.md section 4): fiscal_receipts is partitioned by month and its uniques are
 * partition-local. One (store, idempotency key) and one uuid still give one receipt: FiscalService's advisory lock,
 * the model refusing a bare create, and fiscal_receipt_keys refusing a duplicate at the database. Refunds across
 * months and of a receipt in an archived (detached) month; the sweep window.
 */
class FiscalReceiptIdempotencyTest extends TestCase
{
    // M4: does DDL itself (tests/Concerns/UsesOwnerConnection.php): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;
    use FiscalFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 10:00:00');
        $this->setUpFiscal();
    }

    private function expectReason(string $reason, callable $call, string $message): FiscalException
    {
        try {
            $call();
        } catch (FiscalException $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());
            $this->assertStringContainsString($message, $e->getMessage());

            return $e;
        }
        $this->fail("Expected FiscalException {$reason}");
    }

    private function partitionOf(int $id): string
    {
        return (string) DB::selectOne('SELECT tableoid::regclass::text AS p FROM fiscal_receipts WHERE id = ?', [$id])->p;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rawRow(array $attributes): array
    {
        return $attributes + [
            'uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'cash',
            'status' => 'issued', 'driver' => 'sandbox', 'idempotency_key' => 'raw-' . Str::random(8), 'total' => 1000,
            'lines' => '[]', 'payments' => '[]', 'created_at' => now(),
        ];
    }

    private function detach(string $month): void
    {
        $name = PartitionManager::monthName('fiscal_receipts', CarbonImmutable::parse($month . '-01'));
        DB::statement("ALTER TABLE fiscal_receipts DETACH PARTITION {$name}");
    }

    public function test_a_bare_create_outside_the_issuer_is_refused_and_the_test_escape_hatch_is_explicit(): void
    {
        $attributes = [
            'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'cash', 'driver' => 'sandbox',
            'idempotency_key' => 'bare-1', 'total' => '1000', 'lines' => [], 'payments' => [],
        ];
        $paths = [
            'create' => fn () => FiscalReceipt::create($attributes),
            'forceCreate' => fn () => FiscalReceipt::forceCreate($attributes),
            'save' => fn () => (new FiscalReceipt($attributes))->save(),
            'firstOrCreate' => fn () => FiscalReceipt::firstOrCreate(['store_id' => $this->store->id, 'idempotency_key' => 'bare-1'], $attributes),
            'inside a transaction' => fn () => DB::transaction(fn () => FiscalReceipt::create($attributes)),
        ];
        foreach ($paths as $path => $call) {
            try {
                $call();
                $this->fail("{$path} wrote a fiscal receipt outside FiscalService");
            } catch (LogicException $e) {
                $this->assertStringContainsString('only through App\Domain\Fiscal\FiscalIssuer', $e->getMessage(), $path);
            }
        }
        $this->assertSame(0, FiscalReceipt::count());

        // A copy of an issued receipt (replicate) is refused too
        $issued = $this->receipt($this->issuer()->issue($this->document())->receiptUuid);
        $this->expectException(LogicException::class);
        try {
            $issued->replicate()->fill(['idempotency_key' => 'copy'])->save();
        } finally {
            $this->assertSame(1, FiscalReceipt::count());
            // The explicit, visibly named escape hatch for fixtures
            $row = FiscalReceiptLock::outsideIssuerForTests(fn () => FiscalReceipt::create($attributes));
            $this->assertTrue($row->exists);
        }
    }

    public function test_the_database_refuses_a_second_row_with_a_used_key_or_uuid_in_another_month(): void
    {
        $sale = $this->receipt($this->issuer()->issue($this->document(key: 'pos-bill:1:sale'))->receiptUuid);
        $this->assertSame('fiscal_receipts_2026_11', $this->partitionOf($sale->id));

        // The partition-local UNIQUE (store_id, idempotency_key, created_at) alone would let both of these in
        foreach ([
            'same key, next month' => ['idempotency_key' => 'pos-bill:1:sale', 'created_at' => '2026-12-01 00:00:01'],
            'same key, same month' => ['idempotency_key' => 'pos-bill:1:sale', 'created_at' => '2026-11-17 10:00:01'],
            'same key, DEFAULT partition' => ['idempotency_key' => 'pos-bill:1:sale', 'created_at' => '2031-01-01 00:00:00'],
            'same uuid, previous month' => ['uuid' => $sale->uuid, 'created_at' => '2026-10-31 23:59:59'],
        ] as $case => $attributes) {
            try {
                DB::transaction(fn () => DB::table('fiscal_receipts')->insert($this->rawRow($attributes)));
                $this->fail("{$case}: a duplicate fiscal receipt was written");
            } catch (UniqueConstraintViolationException $e) {
                $this->assertStringContainsString('fiscal_receipt_keys_', $e->getMessage(), $case);
            }
        }
        $this->assertSame(1, FiscalReceipt::count());
        $this->assertSame(1, DB::table('fiscal_receipt_keys')->count());

        // A key and a uuid never change; created_at moves only inside its month (the key row follows)
        foreach (['uuid' => (string) Str::uuid(), 'idempotency_key' => 'other', 'store_id' => $this->foreignStore->id] as $column => $value) {
            try {
                DB::transaction(fn () => DB::table('fiscal_receipts')->where('id', $sale->id)->update([$column => $value]));
                $this->fail("{$column} of a receipt changed");
            } catch (QueryException $e) {
                $this->assertStringContainsString('never change', $e->getMessage());
            }
        }
        DB::table('fiscal_receipts')->where('id', $sale->id)->update(['created_at' => '2026-11-02 08:00:00']);
        $this->assertSame('2026-11-02 08:00:00', DB::table('fiscal_receipt_keys')->where('receipt_id', $sale->id)->value('receipt_created_at'));
        try {
            DB::transaction(fn () => DB::table('fiscal_receipts')->where('id', $sale->id)->update(['created_at' => '2026-10-02 08:00:00']));
            $this->fail('a receipt moved to another month');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame('fiscal_receipts_2026_11', $this->partitionOf($sale->id));
        }

        // The issuer returns the stored receipt for the key, from any later month
        $this->travelTo('2027-01-03 09:00:00');
        $again = $this->issuer()->issue($this->document(key: 'pos-bill:1:sale'));
        $this->assertSame($sale->uuid, $again->receiptUuid);
        $this->assertSame(1, FiscalReceipt::count());
    }

    public function test_issuing_across_a_month_boundary_and_after_a_rollback_gives_one_receipt(): void
    {
        $this->travelTo('2026-11-30 23:59:59');
        $first = $this->issuer()->issue($this->document(key: 'boundary'));
        $this->travelTo('2026-12-01 00:00:01');
        $second = $this->issuer()->issue($this->document(key: 'boundary'));
        $this->assertSame($first->receiptUuid, $second->receiptUuid);
        $this->assertSame(['fiscal_receipts_2026_11'], array_map(fn ($id) => $this->partitionOf($id), FiscalReceipt::where('idempotency_key', 'boundary')->pluck('id')->all()));

        // A till transaction that rolls back takes its receipt (and its key, and its lock) with it; the retry writes it
        $locks = fn () => (int) DB::selectOne("SELECT count(*) AS n FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()")->n;
        $before = $locks();
        DB::beginTransaction();
        $rolledBack = $this->issuer()->issue($this->document(key: 'rollback'));
        $this->assertSame($before + 1, $locks(), 'the key stays locked after the savepoint, until the outer transaction ends');
        DB::rollBack();
        $this->assertSame($before, $locks(), 'a rolled back (savepoint) transaction releases it');
        $this->assertSame(0, FiscalReceipt::where('idempotency_key', 'rollback')->count());
        $this->assertSame(0, DB::table('fiscal_receipt_keys')->where('idempotency_key', 'rollback')->count());
        $retried = $this->issuer()->issue($this->document(key: 'rollback'));
        $this->assertNotSame($rolledBack->receiptUuid, $retried->receiptUuid);
        $this->assertSame(1, FiscalReceipt::where('idempotency_key', 'rollback')->count());
        $this->assertSame(FiscalReceiptStatus::ISSUED, $this->receipt($retried->receiptUuid)->status);
    }

    public function test_a_refund_of_a_previous_month_sale_counts_every_earlier_refund(): void
    {
        $this->travelTo('2026-10-31 23:59:59');
        $sale = $this->issuer()->issue($this->document(lines: [$this->line('100000')]));
        $this->assertTrue($sale->isIssued());

        $this->travelTo('2026-11-01 00:00:01');
        $this->assertTrue($this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('60000')], original: $sale->receiptUuid))->isIssued());
        $this->travelTo('2026-12-15 12:00:00');
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('50000')], original: $sale->receiptUuid)), 'qolgan summadan (40000.00)');
        $this->assertTrue($this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('40000')], original: $sale->receiptUuid))->isIssued());

        $original = $this->receipt($sale->receiptUuid);
        $this->assertSame(['fiscal_receipts_2026_10', 'fiscal_receipts_2026_11', 'fiscal_receipts_2026_12'], FiscalReceipt::orderBy('id')->pluck('id')->map(fn ($id) => $this->partitionOf($id))->unique()->values()->all());
        $this->assertSame(2, $original->linkedReceipts()->count());

        // The refund sum reads only the original's month and later (one day of margin), not every partition
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'sum(')) {
                $queries[] = $query;
            }
        });
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('1000')], original: $sale->receiptUuid)), 'qolgan summadan (0.00)');
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('"created_at" >= ?', $queries[0]->sql);
        $this->assertSame('2026-10-30 23:59:59', $queries[0]->bindings[1] instanceof \DateTimeInterface ? $queries[0]->bindings[1]->format('Y-m-d H:i:s') : (string) $queries[0]->bindings[1]);
    }

    public function test_a_refund_of_a_receipt_in_an_archived_month_fails_with_a_clear_message_and_never_crashes(): void
    {
        $this->travelTo('2026-01-15 12:00:00');
        $old = $this->issuer()->issue($this->document(key: 'jan-sale', lines: [$this->line('100000')]));
        $this->travelTo('2026-02-10 12:00:00');
        // A refund of it that the provider has not taken yet
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['fail_attempts' => 1]]])->save();
        $pendingRefund = $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('10000')], original: $old->receiptUuid));
        $this->assertSame('pending', $pendingRefund->status);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox']])->save();

        // January is taken offline (db:partitions:archive detaches it before the dump and the drop)
        $this->travelTo('2027-03-01 09:00:00');
        $this->detach('2026-01');
        $this->assertNull(FiscalReceipt::where('uuid', $old->receiptUuid)->first());

        $e = $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('10000')], original: $old->receiptUuid)), "2026-01 oyida berilgan va arxivga o'tkazilgan");
        $this->assertSame(['reason' => 'original_receipt_invalid', 'archived_month' => '2026-01'], $e->data());
        // The same through the API shape (422, Uzbek message)
        $this->assertSame(422, $e->render(request()->create('/api/v1/x', 'POST'))?->getStatusCode());

        // The key of the archived receipt is never used for a second receipt
        $this->expectReason(FiscalException::IDEMPOTENCY_CONFLICT, fn () => $this->issuer()->issue($this->document(key: 'jan-sale', lines: [$this->line('100000')])), 'arxivga');
        $this->assertSame(1, FiscalReceipt::count());

        // The pending refund is never sent without its original: it fails with a message, no exception
        $refund = app(FiscalService::class)->attempt($this->receipt($pendingRefund->receiptUuid)->id, force: true);
        $this->assertSame(FiscalReceiptStatus::FAILED, $refund?->status);
        $this->assertStringContainsString('arxivga', (string) $refund->error);
        $this->assertNull($refund->originalReceipt);
        $this->assertNotNull($refund->original_receipt_id);
        // A payment provider's own receipt (lenient) is still recorded
        $external = $this->issuer()->recordExternal($this->document(FiscalReceiptType::REFUND, [$this->line('10000')], key: 'payme:r1', original: $old->receiptUuid), 'payme', ['fiscal_sign' => '1']);
        $this->assertSame('issued', $external->status);
    }

    public function test_the_sweep_reads_its_window_only_and_fails_a_receipt_pending_beyond_it(): void
    {
        config(['fiscal.retry.max_age_days' => 30, 'fiscal.retry.max_attempts' => 1000]);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['fail_attempts' => 1]]])->save();
        $this->travelTo('2026-10-01 10:00:00');
        $old = $this->receipt($this->issuer()->issue($this->document())->receiptUuid);
        $this->travelTo('2026-10-20 10:00:00');
        $recent = $this->receipt($this->issuer()->issue($this->document())->receiptUuid);
        $this->assertSame(['pending', 'pending'], [$old->status->value, $recent->status->value]);

        // 31 days after the first one (the scheduler was down): it is failed with a message, the other one is sent
        $this->travelTo('2026-11-01 10:00:00');
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox']])->save();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'from "fiscal_receipts" where "status" = ?')) {
                $queries[] = $query->sql;
            }
        });
        $this->assertSame(1, app(FiscalService::class)->sweep());

        $old->refresh();
        $this->assertSame(FiscalReceiptStatus::FAILED, $old->status);
        $this->assertStringContainsString("30 kundan ortiq ro'yxatdan o'tmay turdi", (string) $old->error);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $recent->fresh()->status);
        $this->assertCount(3, $queries);
        foreach ($queries as $sql) {
            $this->assertStringContainsString('"created_at"', $sql, 'every sweep query is bounded by created_at (partition pruning)');
        }
        // Failed is final and visible: the owner's retry asks again
        $this->assertSame(FiscalReceiptStatus::ISSUED, app(FiscalService::class)->retryNow($old)->status);
    }

    public function test_the_migration_backfills_the_keys_of_receipts_written_before_it_and_can_run_again(): void
    {
        $this->travelTo('2026-03-10 10:00:00');
        $march = $this->issuer()->issue($this->document(key: 'march'));
        $this->travelTo('2026-11-17 10:00:00');
        $november = $this->issuer()->issue($this->document(key: 'november'));
        // As before the migration: no key rows, no trigger
        $migration = require database_path('migrations/2026_11_25_100300_create_fiscal_receipt_keys_table.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('fiscal_receipt_keys'));

        $migration->up();
        $migration->up();

        $this->assertSame(
            [['march', $march->receiptUuid, '2026-03-10 10:00:00'], ['november', $november->receiptUuid, '2026-11-17 10:00:00']],
            DB::table('fiscal_receipt_keys')->orderBy('receipt_id')->get()->map(fn ($row) => [$row->idempotency_key, $row->uuid, $row->receipt_created_at])->all(),
        );
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('fiscal_receipts')->insert($this->rawRow(['idempotency_key' => 'march', 'created_at' => '2026-04-01 00:00:00']));
    }
}
